<?php

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('realurl');
return [
    'tx_realurl' => $extensionPath . 'class.tx_realurl.php',
    'tx_realurl_cachemgmt' => $extensionPath . 'class.tx_realurl_cachemgmt.php',
    'tx_realurl_configurationservice' => $extensionPath . 'class.tx_realurl_configurationService.php',
    'tx_realurl_configurationservice_exception' => $extensionPath . 'class.tx_realurl_configurationService_exception.php',
    'tx_realurl_pagepath' => $extensionPath . 'class.tx_realurl_pagepath.php',
    'tx_realurl_pathgenerator' => $extensionPath . 'class.tx_realurl_pathgenerator.php',
    'tx_realurl_rootlineException' => $extensionPath . 'class.tx_realurl_rootlineException.php',
    'tx_realurl_tcemain' => $extensionPath . 'class.tx_realurl_tcemain.php',

    'tx_realurl_modfunc1' => $extensionPath . 'modfunc1/class.tx_realurl_modfunc1.php',

    'tx_realurl_abstractdatabase_testcase' => $extensionPath . 'tests/class.tx_realurl_abstractDatabase_testcase.php',
    'tx_realurl_configurationservice_testcase' => $extensionPath . 'tests/class.tx_realurl_configurationService_testcase.php'
];
