<?php

defined('TYPO3') || die();

call_user_func(static function () {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript(
        'hcaptcha',
        'setup',
        'module.tx_form {
          settings {
            yamlConfigurations {
              158329071148 = EXT:hcaptcha/Configuration/Form/Yaml/BaseSetup.yaml
            }
          }
        }'
    );
});
