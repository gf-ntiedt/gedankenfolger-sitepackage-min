<?php
defined('TYPO3') or die();

$containers = [
  'gedankenfolger-container-1' => [
      'label' => '1 Column Container',
      'description' => '',
      'columnConfiguration' => [
          [
              ['name' => 'Column', 'colPos' => 200]
          ]
      ], //grid configuration
      //optional keys:
      'icon' => 'EXT:container/Resources/Public/Icons/container-1col.svg',
      'group' => 'Gedankenfolger Container',
      'header' => true,
      'settings' => true,
  ],
  'gedankenfolger-container-2' => [
      'label' => '2 Column Container',
      'description' => '',
      'columnConfiguration' => [
          [
              ['name' => 'Left column', 'colPos' => 200],
              ['name' => 'Right column', 'colPos' => 210]
          ]
      ], //grid configuration
      //optional keys:
      'icon' => 'EXT:container/Resources/Public/Icons/container-2col.svg',
      'group' => 'Gedankenfolger Container',
      'header' => true,
      'settings' => true,
  ]
];

\TRAW\ContainerWrap\Configuration\Container::registerContainers($containers);