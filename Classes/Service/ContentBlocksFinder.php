<?php
declare(strict_types=1);

namespace Gedankenfolger\GedankenfolgerSitepackageMin\Service;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Collects Content Blocks from active TYPO3 packages and enriches them with usage information.
 *
 * Discovery (folder layout):
 * - EXT:<any-extension>/ContentBlocks/ContentElements/<blockFolder>/config.yaml
 * - EXT:<any-extension>/ContentBlocks/PageTypes/<blockFolder>/config.yaml
 * - EXT:<any-extension>/ContentBlocks/RecordTypes/<blockFolder>/config.yaml
 *
 * Title resolution order:
 * 1) config.yaml key "title"
 * 2) language/<lang>.labels.xlf (trans-unit id="title"), fallback language/labels.xlf
 * 3) fallback to config.yaml key "name"
 *
 * Usage enrichment:
 * - ContentElements: reads tt_content where CType equals typeName, grouped by page (pid),
 *   provides tt_content records for display (uid, header, CType, sys_language_uid, l18n_parent)
 * - PageTypes: reads pages where doktype equals (int)typeName,
 *   provides page records (uid, title, sys_language_uid, l10n_parent) and additionally maps l18n_parent = l10n_parent
 *
 * Notes:
 * - RecordTypes are not resolved to pages here because references are project-specific.
 * - Backend context: only DeletedRestriction is applied.
 */
final class ContentBlocksFinder
{
    /**
     * Max number of tt_content records stored per page for one CType to keep the UI and memory bounded.
     */
    private const MAX_CONTENT_UIDS_PER_PAGE = 200;

    /**
     * Safety cap for fetched tt_content rows (across all matching CTypes) to prevent memory blowups.
     */
    private const MAX_TT_CONTENT_ROWS = 50000;

    private readonly ConnectionPool $connectionPool;

    public function __construct(
        private readonly PackageManager $packageManager,
        ?ConnectionPool $connectionPool = null
    ) {
        // Fallback keeps this service resilient if the cached DI container instantiates with an older signature.
        $this->connectionPool = $connectionPool ?? GeneralUtility::makeInstance(ConnectionPool::class);
    }

    /**
     * @return array<int, array{
     *   contentType: 'ContentElement'|'PageType'|'RecordType',
     *   extensionKey: string,
     *   vendor: string,
     *   blockName: string,
     *   fullName: string,
     *   title: string,
     *   table: string|null,
     *   typeName: string|null,
     *   configPath: string,
     *   usagePages: array<int, array{
     *     uid:int,
     *     title:string,
     *     count:int,
     *     sys_language_uid?:int,
     *     l18n_parent?:int,
     *     contentRecords?:array<int, array{uid:int,header:string,CType:string,sys_language_uid:int,l18n_parent:int}>
     *     uidsTruncated?:bool
     *   }>
     * }>
     */
    public function findAll(): array
    {
        $items = [];

        $typeMap = [
            'ContentElements' => [
                'label' => 'ContentElement',
                'defaultTable' => 'tt_content',
            ],
            'PageTypes' => [
                'label' => 'PageType',
                'defaultTable' => 'pages',
            ],
            'RecordTypes' => [
                'label' => 'RecordType',
                'defaultTable' => null,
            ],
        ];

        $backendLanguage = $this->resolveBackendLanguageKey();

        foreach ($this->packageManager->getActivePackages() as $extensionKey => $package) {
            $packagePath = rtrim((string)$package->getPackagePath(), '/\\') . DIRECTORY_SEPARATOR;

            foreach ($typeMap as $folder => $meta) {
                $basePath = $packagePath . 'ContentBlocks' . DIRECTORY_SEPARATOR . $folder;
                if (!is_dir($basePath)) {
                    continue;
                }

                foreach ($this->findConfigYamlFiles($basePath) as $configPath) {
                    $yaml = $this->safeParseYamlFile($configPath);
                    if ($yaml === null) {
                        continue;
                    }

                    $name = (string)($yaml['name'] ?? '');
                    if ($name === '') {
                        continue;
                    }

                    [$vendor, $blockName] = $this->splitBlockName($name);

                    $typeName = isset($yaml['typeName']) ? (string)$yaml['typeName'] : null;
                    $title = (string)($yaml['title'] ?? '');

                    $table = $meta['defaultTable'];
                    if ($folder === 'RecordTypes') {
                        $table = (string)($yaml['table'] ?? '');
                    }

                    if ($title === '') {
                        $title = $this->readTitleFromLabelsXlf(dirname($configPath), $backendLanguage);
                    }
                    if ($title === '') {
                        $title = $name;
                    }

                    $items[] = [
                        'contentType' => $meta['label'],
                        'extensionKey' => (string)$extensionKey,
                        'vendor' => $vendor,
                        'blockName' => $blockName,
                        'fullName' => $name,
                        'title' => $title,
                        'table' => $table,
                        'typeName' => $typeName,
                        'configPath' => $configPath,
                        'usagePages' => [],
                    ];
                }
            }
        }

        $this->enrichUsageOnPages($items);

        usort(
            $items,
            static fn(array $a, array $b): int
                => [$a['contentType'], $a['vendor'], $a['blockName'], $a['extensionKey']]
                <=> [$b['contentType'], $b['vendor'], $b['blockName'], $b['extensionKey']]
        );

        return $items;
    }

    /**
     * @return array<int, string> absolute config.yaml paths
     */
    private function findConfigYamlFiles(string $basePath): array
    {
        $finder = new Finder();
        $finder
            ->files()
            ->in($basePath)
            ->name('config.yaml')
            ->depth('== 1'); // <blockFolder>/config.yaml

        $paths = [];
        foreach ($finder as $file) {
            $realPath = $file->getRealPath();
            if ($realPath !== false) {
                $paths[] = $realPath;
            }
        }
        return $paths;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeParseYamlFile(string $path): ?array
    {
        try {
            $data = Yaml::parseFile($path);
            return is_array($data) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0:string,1:string} [vendor, blockName]
     */
    private function splitBlockName(string $fullName): array
    {
        if (!str_contains($fullName, '/')) {
            return ['', $fullName];
        }
        $parts = explode('/', $fullName, 2);
        return [(string)$parts[0], (string)$parts[1]];
    }

    /**
     * Adds "usagePages" for ContentElements and PageTypes.
     *
     * @param array<int, array<string, mixed>> $items
     */
    private function enrichUsageOnPages(array &$items): void
    {
        $ctypeToIndexes = [];
        $doktypeToIndexes = [];

        foreach ($items as $idx => $item) {
            $typeName = (string)($item['typeName'] ?? '');
            if ($typeName === '') {
                continue;
            }

            if (($item['contentType'] ?? '') === 'ContentElement') {
                $ctypeToIndexes[$typeName][] = $idx;
                continue;
            }

            if (($item['contentType'] ?? '') === 'PageType' && ctype_digit($typeName)) {
                $doktypeToIndexes[(int)$typeName][] = $idx;
            }
        }

        if ($ctypeToIndexes !== []) {
            $this->enrichContentElementUsage($items, $ctypeToIndexes);
        }

        if ($doktypeToIndexes !== []) {
            $this->enrichPageTypeUsage($items, $doktypeToIndexes);
        }
    }

    /**
     * Enriches ContentElements by scanning tt_content where CType IN (typeNames) and grouping by pid.
     * Provides per page a list of content records so Fluid can render icon + header.
     *
     * Fields provided for each tt_content record:
     * - uid
     * - header
     * - CType
     * - sys_language_uid
     * - l18n_parent
     *
     * Additionally for each page entry:
     * - sys_language_uid (from pages)
     * - l18n_parent (alias for pages.l10n_parent; pages does not have an l18n_parent column)
     *
     * @param array<int, array<string, mixed>> $items
     * @param array<string, array<int,int>> $ctypeToIndexes
     */
    private function enrichContentElementUsage(array &$items, array $ctypeToIndexes): void
    {
        $ctypes = array_keys($ctypeToIndexes);

        $qb = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $rows = $qb
            ->select('uid', 'pid', 'CType', 'header', 'sys_language_uid', 'l18n_parent')
            ->from('tt_content')
            ->where(
                $qb->expr()->in(
                    'CType',
                    $qb->createNamedParameter($ctypes, Connection::PARAM_STR_ARRAY)
                )
            )
            ->orderBy('pid', 'ASC')
            ->addOrderBy('uid', 'ASC')
            ->setMaxResults(self::MAX_TT_CONTENT_ROWS)
            ->executeQuery()
            ->fetchAllAssociative();

        if ($rows === []) {
            return;
        }

        // usageMap[CType][pid] => ['count'=>int,'records'=>array<int,array{...}>, 'truncated'=>bool]
        $usageMap = [];
        $pids = [];

        foreach ($rows as $row) {
            $uid = (int)$row['uid'];
            $pid = (int)$row['pid'];
            $ctype = (string)$row['CType'];

            $pids[] = $pid;

            if (!isset($usageMap[$ctype][$pid])) {
                $usageMap[$ctype][$pid] = [
                    'count' => 0,
                    'records' => [],
                    'truncated' => false,
                ];
            }

            $usageMap[$ctype][$pid]['count']++;

            if ($usageMap[$ctype][$pid]['truncated'] === false) {
                if (count($usageMap[$ctype][$pid]['records']) < self::MAX_CONTENT_UIDS_PER_PAGE) {
                    $usageMap[$ctype][$pid]['records'][] = [
                        'uid' => $uid,
                        'header' => (string)($row['header'] ?? ''),
                        'CType' => $ctype,
                        'sys_language_uid' => (int)($row['sys_language_uid'] ?? 0),
                        'l18n_parent' => (int)($row['l18n_parent'] ?? 0),
                    ];
                } else {
                    $usageMap[$ctype][$pid]['truncated'] = true;
                }
            }
        }

        $pids = array_values(array_unique(array_filter($pids)));
        $pageData = $this->fetchPageDataByUids($pids);

        foreach ($usageMap as $ctype => $pagesMap) {
            foreach ($pagesMap as $pid => $data) {
                $pid = (int)$pid;
                $pageTitle = $pageData[$pid]['title'] ?? '';
                $pageSysLanguageUid = $pageData[$pid]['sys_language_uid'] ?? 0;
                $pageL18nParent = $pageData[$pid]['l18n_parent'] ?? 0;

                foreach (($ctypeToIndexes[$ctype] ?? []) as $itemIndex) {
                    $items[$itemIndex]['usagePages'][] = [
                        'uid' => $pid,
                        'title' => $pageTitle,
                        'count' => (int)$data['count'],
                        'sys_language_uid' => (int)$pageSysLanguageUid,
                        'l18n_parent' => (int)$pageL18nParent,
                        'contentRecords' => $data['records'],
                        'uidsTruncated' => (bool)$data['truncated'],
                    ];
                }
            }
        }
    }

    /**
     * Enriches PageTypes by finding pages where doktype IN (typeNames).
     *
     * Fields provided per page entry:
     * - uid
     * - title
     * - sys_language_uid
     * - l18n_parent (alias for pages.l10n_parent; pages uses l10n_parent, not l18n_parent)
     *
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array<int,int>> $doktypeToIndexes
     */
    private function enrichPageTypeUsage(array &$items, array $doktypeToIndexes): void
    {
        $doktypes = array_keys($doktypeToIndexes);

        $qb = $this->connectionPool->getQueryBuilderForTable('pages');
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $rows = $qb
            ->select('uid', 'title', 'doktype', 'sys_language_uid', 'l10n_parent')
            ->from('pages')
            ->where(
                $qb->expr()->in(
                    'doktype',
                    $qb->createNamedParameter($doktypes, Connection::PARAM_INT_ARRAY)
                )
            )
            ->orderBy('uid', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $uid = (int)$row['uid'];
            $title = (string)$row['title'];
            $doktype = (int)$row['doktype'];

            foreach (($doktypeToIndexes[$doktype] ?? []) as $itemIndex) {
                $items[$itemIndex]['usagePages'][] = [
                    'uid' => $uid,
                    'title' => $title,
                    'count' => 1,
                    'sys_language_uid' => (int)($row['sys_language_uid'] ?? 0),
                    // pages has l10n_parent; provide l18n_parent as an alias for consistent Fluid usage
                    'l18n_parent' => (int)($row['l10n_parent'] ?? 0),
                ];
            }
        }
    }

    /**
     * Fetches page data for given page uids.
     *
     * pages table has:
     * - sys_language_uid
     * - l10n_parent (localization parent)
     *
     * For Fluid consistency this method returns:
     * - title
     * - sys_language_uid
     * - l18n_parent (alias of l10n_parent)
     *
     * @param int[] $uids
     * @return array<int, array{title:string, sys_language_uid:int, l18n_parent:int}>
     */
    private function fetchPageDataByUids(array $uids): array
    {
        if ($uids === []) {
            return [];
        }

        $qb = $this->connectionPool->getQueryBuilderForTable('pages');
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $rows = $qb
            ->select('uid', 'title', 'sys_language_uid', 'l10n_parent')
            ->from('pages')
            ->where(
                $qb->expr()->in(
                    'uid',
                    $qb->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $map = [];
        foreach ($rows as $row) {
            $uid = (int)$row['uid'];
            $map[$uid] = [
                'title' => (string)($row['title'] ?? ''),
                'sys_language_uid' => (int)($row['sys_language_uid'] ?? 0),
                // pages uses l10n_parent; expose it under l18n_parent to match tt_content naming
                'l18n_parent' => (int)($row['l10n_parent'] ?? 0),
            ];
        }

        return $map;
    }

    /**
     * Resolves the backend user language key.
     * Typical values are "en", "de", ...
     */
    private function resolveBackendLanguageKey(): string
    {
        $beUser = $GLOBALS['BE_USER'] ?? null;
        if ($beUser instanceof BackendUserAuthentication) {
            $lang = (string)($beUser->uc['lang'] ?? '');
            if ($lang !== '') {
                return $lang;
            }
        }

        if (isset($GLOBALS['LANG']) && is_object($GLOBALS['LANG']) && property_exists($GLOBALS['LANG'], 'lang')) {
            $lang = (string)$GLOBALS['LANG']->lang;
            if ($lang !== '') {
                return $lang;
            }
        }

        return 'en';
    }

    /**
     * Reads a Content Block title from XLF labels:
     * - language/<lang>.labels.xlf preferred (if lang != en)
     * - fallback: language/labels.xlf
     *
     * Expects trans-unit id="title" and reads <target> first, then <source>.
     */
    private function readTitleFromLabelsXlf(string $blockDir, string $languageKey): string
    {
        $languageDir = rtrim($blockDir, '/\\') . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR;

        $candidates = [];
        if ($languageKey !== '' && $languageKey !== 'en') {
            $candidates[] = $languageDir . $languageKey . '.labels.xlf';
        }
        $candidates[] = $languageDir . 'labels.xlf';

        foreach ($candidates as $path) {
            if (!is_file($path)) {
                continue;
            }
            $title = $this->extractXlfTransUnitValue($path, 'title');
            if ($title !== '') {
                return $title;
            }
        }

        return '';
    }

    /**
     * Extracts an XLIFF trans-unit value by id.
     *
     * @param string $xlfPath Absolute file path
     * @param string $transUnitId e.g. "title"
     */
    private function extractXlfTransUnitValue(string $xlfPath, string $transUnitId): string
    {
        $prev = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;

        $loaded = $dom->load($xlfPath);
        if ($loaded !== true) {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            return '';
        }

        $xpath = new \DOMXPath($dom);
        $idLiteral = $this->xpathLiteral($transUnitId);

        $nodes = $xpath->query('//trans-unit[@id=' . $idLiteral . ']');
        if ($nodes === false || $nodes->length === 0) {
            $xpath->registerNamespace('x', 'urn:oasis:names:tc:xliff:document:1.2');
            $nodes = $xpath->query('//x:trans-unit[@id=' . $idLiteral . ']');
        }

        if ($nodes === false || $nodes->length === 0) {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            return '';
        }

        /** @var \DOMElement $unit */
        $unit = $nodes->item(0);

        $target = $unit->getElementsByTagName('target')->item(0);
        if ($target instanceof \DOMNode) {
            $value = trim((string)$target->textContent);
            if ($value !== '') {
                libxml_clear_errors();
                libxml_use_internal_errors($prev);
                return $value;
            }
        }

        $source = $unit->getElementsByTagName('source')->item(0);
        if ($source instanceof \DOMNode) {
            $value = trim((string)$source->textContent);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            return $value;
        }

        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return '';
    }

    /**
     * Builds a safe XPath string literal.
     */
    private function xpathLiteral(string $value): string
    {
        if (!str_contains($value, "'")) {
            return "'" . $value . "'";
        }
        if (!str_contains($value, '"')) {
            return '"' . $value . '"';
        }

        $parts = preg_split('/(\'|")/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return "'" . $value . "'";
        }

        $chunks = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if ($part === "'") {
                $chunks[] = '"\'"';
            } elseif ($part === '"') {
                $chunks[] = "'\"'";
            } else {
                $chunks[] = "'" . $part . "'";
            }
        }

        return 'concat(' . implode(',', $chunks) . ')';
    }
}
