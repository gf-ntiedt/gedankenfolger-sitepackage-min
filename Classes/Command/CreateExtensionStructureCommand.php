<?php

declare(strict_types=1);

namespace Gedankenfolger\GedankenfolgerSitepackageMin\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Environment;

/**
 * Creates the basic folder structure and files for a TYPO3 extension.
 *
 * @author  Niels Tiedt <niels.tiedt@gedankenfolger.de>
 * @company Gedankenfolger GmbH
 */
#[AsCommand(
    name: 'extension:createstructure',
    description: 'Creates the basic folder structure and files for a TYPO3 extension',
)]
final class CreateExtensionStructureCommand extends Command
{
    /**
     * @var array<string> Directories to create
     */
    private array $directories = [
        'Classes',
        'Configuration',
        'Resources/Private/Language',
        'Resources/Public/Scss',
        'Resources/Public/Css',
        'Resources/Public/Icons',
        'Resources/Public/JavaScript',
    ];

    protected function configure(): void
    {
        $this
            ->setHelp(
                'This command creates the basic folder structure for a TYPO3 extension.' . "\n" .
                'Examples:' . "\n" .
                '  Create in current extension directory:' . "\n" .
                '    typo3 extension:createstructure my_extension .' . "\n\n" .
                '  Create in packages/ directory:' . "\n" .
                '    typo3 extension:createstructure my_extension packages/' . "\n\n" .
                '  Create with absolute path:' . "\n" .
                '    typo3 extension:createstructure my_extension /var/www/html/packages/'
            )
            ->addArgument(
                'extensionKey',
                InputArgument::REQUIRED,
                'The extension key (e.g., my_extension)'
            )
            ->addArgument(
                'targetPath',
                InputArgument::OPTIONAL,
                'Path where to create the extension. Use "." for current directory, or specify a path relative to project root',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $extensionKey = $input->getArgument('extensionKey');
        $targetPathArg = $input->getArgument('targetPath');

        // Validate extension key
        if (!preg_match('/^[a-z0-9_]+$/', $extensionKey)) {
            $io->error('Extension key must only contain lowercase letters, numbers and underscores.');
            return Command::FAILURE;
        }

        $projectRoot = Environment::getProjectPath();

        // Determine target path
        if ($targetPathArg === null) {
            // Interactive mode - ask user
            $targetPath = $this->askForTargetPath($io, $projectRoot);
            if ($targetPath === null) {
                return Command::SUCCESS;
            }
        } else {
            // Path was provided as argument
            $targetPath = $this->resolveTargetPath($targetPathArg, $projectRoot);
        }

        // Full extension path
        $extensionPath = rtrim($targetPath, '/') . '/' . $extensionKey;

        // Check if extension directory already exists
        if (is_dir($extensionPath)) {
            $io->warning(sprintf('Extension directory "%s" already exists.', $extensionPath));

            if (!$io->confirm('Do you want to create missing directories and files?', false)) {
                $io->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        } else {
            // Verify target directory is writable
            if (!is_dir($targetPath)) {
                $io->warning('Target directory does not exist: ' . $targetPath);
                if (!$io->confirm('Do you want to create it?', true)) {
                    $io->error('Operation cancelled.');
                    return Command::FAILURE;
                }
            }

            // Create base extension directory
            if (!mkdir($extensionPath, 0755, true)) {
                $io->error('Failed to create extension directory: ' . $extensionPath);
                return Command::FAILURE;
            }
        }

        $io->title('Creating extension structure for: ' . $extensionKey);
        $io->writeln('<info>Extension path:</info> ' . $extensionPath);
        $io->newLine();

        // Ask for vendor name
        $vendor = $io->ask('Enter vendor name (e.g., gedankenfolger)', 'vendor');

        // Create directories
        $createdDirs = 0;
        $existingDirs = 0;

        foreach ($this->directories as $directory) {
            $fullPath = $extensionPath . '/' . $directory;

            if (!is_dir($fullPath)) {
                if (mkdir($fullPath, 0755, true)) {
                    $io->writeln('<info>✓</info> Created: ' . $directory);
                    $createdDirs++;
                } else {
                    $io->error('Failed to create directory: ' . $directory);
                    return Command::FAILURE;
                }
            } else {
                $io->writeln('<comment>→</comment> Already exists: ' . $directory);
                $existingDirs++;
            }
        }

        // Create composer.json
        $composerPath = $extensionPath . '/composer.json';
        if (!file_exists($composerPath)) {
            $composerContent = $this->getComposerTemplate($vendor, $extensionKey);
            if (file_put_contents($composerPath, $composerContent) !== false) {
                $io->writeln('<info>✓</info> Created: composer.json');
            } else {
                $io->error('Failed to create composer.json');
                return Command::FAILURE;
            }
        } else {
            $io->writeln('<comment>→</comment> Already exists: composer.json');
        }

        // Create ext_emconf.php
        $emconfPath = $extensionPath . '/ext_emconf.php';
        if (!file_exists($emconfPath)) {
            $emconfContent = $this->getEmconfTemplate($vendor, $extensionKey);
            if (file_put_contents($emconfPath, $emconfContent) !== false) {
                $io->writeln('<info>✓</info> Created: ext_emconf.php');
            } else {
                $io->error('Failed to create ext_emconf.php');
                return Command::FAILURE;
            }
        } else {
            $io->writeln('<comment>→</comment> Already exists: ext_emconf.php');
        }

        // Create README.md
        $readmePath = $extensionPath . '/README.md';
        if (!file_exists($readmePath)) {
            $readmeContent = $this->getReadmeTemplate($extensionKey);
            if (file_put_contents($readmePath, $readmeContent) !== false) {
                $io->writeln('<info>✓</info> Created: README.md');
            } else {
                $io->error('Failed to create README.md');
                return Command::FAILURE;
            }
        } else {
            $io->writeln('<comment>→</comment> Already exists: README.md');
        }

        // Create .gitkeep files in empty directories
        $this->createGitkeepFiles($extensionPath, $io);

        // Ask if SiteSet should be created
        $io->newLine();
        if ($io->confirm('Do you want to create a SiteSet?', false)) {
            $this->createSiteSet($extensionPath, $extensionKey, $vendor, $io);
        }

        // Summary
        $io->newLine();
        $io->success('Extension structure created successfully!');
        $io->listing([
            sprintf('Created directories: %d', $createdDirs),
            sprintf('Existing directories: %d', $existingDirs),
            sprintf('Extension path: %s', $extensionPath),
        ]);

        $io->note([
            'Next steps:',
            '1. Update composer.json and ext_emconf.php (title, description, version, constraints)',
            '2. Create your ext_localconf.php if needed',
        ]);

        return Command::SUCCESS;
    }

    /**
     * Ask user interactively for target path
     */
    private function askForTargetPath(SymfonyStyle $io, string $projectRoot): ?string
    {
        $io->title('Extension Structure Generator');
        $io->section('Target Path Selection');

        // Show helpful info about paths
        $io->writeln([
            '<comment>Project root:</comment> ' . $projectRoot,
            '',
            '<info>Common locations:</info>',
            '  • packages/          - For custom packages',
            '  • src/extensions/    - Alternative location',
            '  • public/typo3conf/ext/ - Classic TYPO3 extensions folder',
            '',
        ]);

        $choice = $io->choice(
            'Where should the extension be created?',
            [
                'packages' => 'packages/ (recommended for custom extensions)',
                'src' => 'src/extensions/',
                'typo3conf' => 'public/typo3conf/ext/ (classic location)',
                'custom' => 'Enter custom path',
                'cancel' => 'Cancel',
            ],
            'packages'
        );

        switch ($choice) {
            case 'packages':
                return $projectRoot . '/packages';
            case 'src':
                return $projectRoot . '/src/extensions';
            case 'typo3conf':
                return $projectRoot . '/public/typo3conf/ext';
            case 'custom':
                $customPath = $io->ask(
                    'Enter path (absolute or relative to project root)',
                    'packages/'
                );
                return $this->resolveTargetPath($customPath, $projectRoot);
            case 'cancel':
                $io->info('Operation cancelled.');
                return null;
        }

        return null;
    }

    /**
     * Resolve target path from user input
     */
    private function resolveTargetPath(string $path, string $projectRoot): string
    {
        // Handle "." as current directory (project root in DDEV context)
        if ($path === '.') {
            return $projectRoot;
        }

        // If absolute path, use as-is
        if (str_starts_with($path, '/')) {
            return rtrim($path, '/');
        }

        // If relative, combine with project root
        return $projectRoot . '/' . ltrim($path, '/');
    }

    /**
     * Create .gitkeep files in directories to preserve them in Git
     */
    private function createGitkeepFiles(string $extensionPath, SymfonyStyle $io): void
    {
        $dirsForGitkeep = [
            'Classes',
            'Resources/Public/Css',
            'Resources/Public/Icons',
            'Resources/Public/JavaScript',
            'Resources/Public/Scss',
        ];

        foreach ($dirsForGitkeep as $dir) {
            $gitkeepPath = $extensionPath . '/' . $dir . '/.gitkeep';
            if (!file_exists($gitkeepPath)) {
                file_put_contents($gitkeepPath, '');
                $io->writeln('<info>✓</info> Created: ' . $dir . '/.gitkeep');
            }
        }
    }

    /**
     * Generate a basic README.md template
     */
    private function getReadmeTemplate(string $extensionKey): string
    {
        $extensionName = str_replace('_', ' ', ucwords($extensionKey, '_'));

        return <<<README
# {$extensionName}

## Description

Add your extension description here.

## Installation

### Via Composer (recommended)

```bash
composer require vendor/{$extensionKey}
```

### Manual Installation

1. Download the extension
2. Extract to your extensions directory
3. Activate in Extension Manager

## Configuration

Add configuration instructions here.

## Usage

Add usage instructions here.

## Requirements

- TYPO3 13.4 or higher
- PHP 8.2 or higher

## Support

For issues and feature requests, please use the issue tracker.

## License

Add your license information here.

README;
    }

    /**
     * Convert extension key to UpperCamelCase
     * Example: my_extension -> MyExtension
     */
    private function convertToUpperCamelCase(string $extensionKey): string
    {
        return str_replace('_', '', ucwords($extensionKey, '_'));
    }

    /**
     * Convert extension key to package name
     * Example: my_extension -> my-extension
     */
    private function convertToPackageName(string $extensionKey): string
    {
        return str_replace('_', '-', $extensionKey);
    }

    /**
     * Generate a composer.json template
     */
    private function getComposerTemplate(string $vendor, string $extensionKey): string
    {
        $packageName = $this->convertToPackageName($extensionKey);
        $upperCamelCaseName = $this->convertToUpperCamelCase($extensionKey);
        $vendorNamespace = ucfirst($vendor);

        return <<<JSON
{
    "name": "{$vendor}/{$packageName}",
    "description": "TYPO3 extension",
    "version": "1.0.0",
    "type": "typo3-cms-extension",
    "license": [
        "GPL-2.0-or-later"
    ],
    "require": {},
    "autoload": {
        "psr-4": {
            "{$vendorNamespace}\\\\{$upperCamelCaseName}\\\\": "Classes/"
        }
    },
    "extra": {
        "typo3/cms": {
            "extension-key": "{$extensionKey}"
        }
    }
}

JSON;
    }

    /**
     * Generate an ext_emconf.php template (no $_EXTKEY definition - TYPO3 and TER pre-set it)
     */
    private function getEmconfTemplate(string $vendor, string $extensionKey): string
    {
        $upperCamelCaseName = $this->convertToUpperCamelCase($extensionKey);
        $vendorNamespace = ucfirst($vendor);
        $title = str_replace('_', ' ', ucwords($extensionKey, '_'));

        return <<<PHP
<?php

\$EM_CONF[\$_EXTKEY] = [
    'title' => '{$title}',
    'description' => 'TYPO3 extension',
    'category' => 'misc',
    'author' => 'Author Name',
    'author_email' => 'author@example.com',
    'state' => 'alpha',
    'clearCacheOnLoad' => 1,
    'version' => '1.0.0',
    'autoload' => [
        'psr-4' => [
            '{$vendorNamespace}\\\\{$upperCamelCaseName}\\\\' => 'Classes',
        ],
    ],
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];

PHP;
    }

    /**
     * Create SiteSet structure and files
     */
    private function createSiteSet(string $extensionPath, string $extensionKey, string $vendor, SymfonyStyle $io): void
    {
        $io->section('Creating SiteSet');

        $upperCamelCaseName = $this->convertToUpperCamelCase($extensionKey);
        $siteSetPath = $extensionPath . '/Configuration/Sets/' . $upperCamelCaseName;

        // Create SiteSet directory
        if (!is_dir($siteSetPath)) {
            if (!mkdir($siteSetPath, 0755, true)) {
                $io->error('Failed to create SiteSet directory: ' . $siteSetPath);
                return;
            }
            $io->writeln('<info>✓</info> Created: Configuration/Sets/' . $upperCamelCaseName);
        } else {
            $io->writeln('<comment>→</comment> Already exists: Configuration/Sets/' . $upperCamelCaseName);
        }

        // Create config.yaml
        $this->createSiteSetFile($siteSetPath, 'config.yaml', $this->getConfigYamlTemplate($vendor, $upperCamelCaseName), $io);

        // Create page.tsconfig
        $this->createSiteSetFile($siteSetPath, 'page.tsconfig', $this->getPageTsconfigTemplate(), $io);

        // Create settings.definitions.yaml
        $this->createSiteSetFile($siteSetPath, 'settings.definitions.yaml', $this->getSettingsDefinitionsTemplate($vendor, $upperCamelCaseName), $io);

        // Create setup.typoscript
        $this->createSiteSetFile($siteSetPath, 'setup.typoscript', $this->getSetupTyposcriptTemplate($extensionKey), $io);

        $io->success('SiteSet created successfully!');
    }

    /**
     * Create a single SiteSet file
     */
    private function createSiteSetFile(string $siteSetPath, string $fileName, string $content, SymfonyStyle $io): void
    {
        $filePath = $siteSetPath . '/' . $fileName;

        if (!file_exists($filePath)) {
            if (file_put_contents($filePath, $content) !== false) {
                $io->writeln('<info>✓</info> Created: ' . $fileName);
            } else {
                $io->error('Failed to create: ' . $fileName);
            }
        } else {
            $io->writeln('<comment>→</comment> Already exists: ' . $fileName);
        }
    }

    /**
     * Generate config.yaml template
     */
    private function getConfigYamlTemplate(string $vendor, string $upperCamelCaseName): string
    {
        return <<<YAML
name: {$vendor}/{$upperCamelCaseName}
label: '{$upperCamelCaseName} Site Set'

YAML;
    }

    /**
     * Generate page.tsconfig template
     */
    private function getPageTsconfigTemplate(): string
    {
        return <<<TSCONFIG
# Page TSconfig for this site set
# Example:
# TCEMAIN {
#     table.pages {
#         disablePrependAtCopy = 1
#     }
# }

TSCONFIG;
    }

    /**
     * Generate settings.definitions.yaml template
     */
    private function getSettingsDefinitionsTemplate(string $vendor, string $upperCamelCaseName): string
    {
        return <<<YAML
# categories:
#   {$upperCamelCaseName}:
#     label: '{$vendor} {$upperCamelCaseName}'

# settings:
#   {$upperCamelCaseName}.myCustomSetting:
#     label: 'My Custom Setting'
#     description: 'Description of the setting'
#     type: string
#     default: 'default value'

YAML;
    }

    /**
     * Generate setup.typoscript template
     */
    private function getSetupTyposcriptTemplate(string $extensionKey): string
    {
        return '';
    }
}
