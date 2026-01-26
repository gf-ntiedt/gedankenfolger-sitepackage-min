<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Gedankenfolger sitepackage min',
    'description' => 'For development',
    'category' => 'templates',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'fluid_styled_content' => '13.4.0-13.4.99',
            'rte_ckeditor' => '13.4.0-13.4.99',
        ],
        'conflicts' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Gedankenfolger\\GedankenfolgerSitepackageMin\\' => 'Classes',
        ],
    ],
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 1,
    'author' => 'Niels Tiedt',
    'author_email' => 'niels.tiedt@gedankenfolger.de',
    'author_company' => 'Gedankenfolger',
    'version' => '13.4.0',
];
