<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Gedankenfolger Sitepackage Min',
    'description' => 'Minimal sitepackage for TYPO3 14 projects',
    'category' => 'templates',
    'author' => 'Niels Tiedt, Gedankenfolger GmbH',
    'author_email' => 'niels.tiedt@gedankenfolger.de',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'version' => '14.0.4',
    'autoload' => [
        'psr-4' => [
            'Gedankenfolger\\GedankenfolgerSitepackageMin\\' => 'Classes/',
        ],
    ],
    'constraints' => [
        'depends' => [
            'typo3' => '14.0.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
