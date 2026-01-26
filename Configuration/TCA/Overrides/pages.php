<?php
defined('TYPO3') or die();

$tempColumnsAfterTitle = [
    'gedankenfolger_nav_main' => [
        'exclude' => 1,
        'label'   => 'LLL:EXT:gedankenfolger_sitepackage_min/Resources/Private/Language/locallang_db.xlf:pages.gedankenfolger_nav_main',
        'config'  => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'items' => [
                [
                    'label' => 'Aktivieren',
                ],
            ],
            'default' => 0,
        ],
    ],
];
$tempColumnsAfterMedia = [
    'gedankenfolger_iconclass' => [
        'label' => 'LLL:EXT:gedankenfolger_sitepackage_min/Resources/Private/Language/locallang_db.xlf:pages.gedankenfolger_iconclass',
        'description' => 'LLL:EXT:gedankenfolger_sitepackage_min/Resources/Private/Language/locallang_db.xlf:pages.gedankenfolger_iconclass.description',
        'exclude' => 1,
        'config' => [
            'type' => 'input',
            'size' => 50,
            'nullable' => true,
            'default' => null,
            'behaviour' => [
                'allowLanguageSynchronization' => true,
            ],
        ],
    ],
    'gedankenfolger_icon' => [
        'label' => 'LLL:EXT:gedankenfolger_sitepackage_min/Resources/Private/Language/locallang_db.xlf:pages.gedankenfolger_icon',
        'description' => 'LLL:EXT:gedankenfolger_sitepackage_min/Resources/Private/Language/locallang_db.xlf:pages.gedankenfolger_icon.description',
        'exclude' => 1,
        'config' => [
            'type' => 'file',
            'maxitems' => 1,
            'allowed' => 'svg,png,jpg',
            'behaviour' => [
                'allowLanguageSynchronization' => true,
            ],
        ],
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages', $tempColumnsAfterTitle);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    'gedankenfolger_linktext, gedankenfolger_nav_main',
    '',
    'after:title'
);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages', $tempColumnsAfterMedia);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    'gedankenfolger_iconclass, gedankenfolger_icon',
    '',
    'after:media'
);


/* T3AI */
$GLOBALS['TCA']['pages']['columns']['title']['l10n_mode'] = 'prefixLangTitle';
