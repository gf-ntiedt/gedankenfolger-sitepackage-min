<?php

defined('TYPO3') or die('Access denied.');

// Add default RTE configuration
$GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['gedankenfolger_sitepackage_min'] = 'EXT:gedankenfolger_sitepackage_min/Configuration/RTE/Default.yaml';

// Register the form framework configuration (extension form path) for both
// the backend form editor (module.tx_form) and the frontend (plugin.tx_form).
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup(
    'module.tx_form.settings.yamlConfigurations {
    1783939996 = EXT:gedankenfolger_sitepackage_min/Configuration/Form/Setup.yaml
}
plugin.tx_form.settings.yamlConfigurations {
    1783939996 = EXT:gedankenfolger_sitepackage_min/Configuration/Form/Setup.yaml
}'
);
