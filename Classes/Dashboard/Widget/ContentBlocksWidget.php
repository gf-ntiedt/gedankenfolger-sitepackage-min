<?php
// Classes/Dashboard/Widget/ContentBlocksWidget.php
declare(strict_types=1);

namespace Gedankenfolger\GedankenfolgerSitepackageMin\Dashboard\Widget;

use Gedankenfolger\GedankenfolgerSitepackageMin\Service\ContentBlocksFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

final class ContentBlocksWidget implements WidgetInterface
{
    public function __construct(
        private readonly WidgetConfigurationInterface $configuration,
        private readonly ContentBlocksFinder $finder,
        private readonly array $options = [],
    ) {}

    public function getOptions(): array
    {
        return $this->options;
    }

    public function renderWidgetContent(): string
    {
        $items = $this->finder->findAll();

        $grouped = [
            'ContentElements' => [],
            'PageTypes' => [],
            'RecordTypes' => [],
        ];

        foreach ($items as $item) {
            $type = (string)($item['contentType'] ?? '');
            if ($type === 'ContentElement') {
                $grouped['ContentElements'][] = $item;
            } elseif ($type === 'PageType') {
                $grouped['PageTypes'][] = $item;
            } elseif ($type === 'RecordType') {
                $grouped['RecordTypes'][] = $item;
            }
        }

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename(
            GeneralUtility::getFileAbsFileName(
                'EXT:gedankenfolger_sitepackage_min/Resources/Private/Templates/Dashboard/ContentBlocksWidget.html'
            )
        );

        $view->assignMultiple([
            'grouped' => $grouped,
            'count' => count($items),
            'configuration' => $this->configuration,
            'options' => $this->options,
        ]);

        return $view->render();
    }
}
