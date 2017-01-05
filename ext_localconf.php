<?php
defined('TYPO3_MODE') or die();

if (TYPO3_MODE === 'FE') {
    //hook to force regeneration if crawler is active:
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['insertPageIncache']['tx_realurl'] =
        'EXT:realurl/class.tx_realurl_crawler.php:tx_realurl_crawler';
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['headerNoCache']['tx_realurl'] =
        'EXT:realurl/class.tx_realurl_crawler.php:tx_realurl_crawler->headerNoCache';
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearPageCacheEval']['tx_realurl'] =
    'EXT:realurl/class.tx_realurl.php:&tx_realurl->clearPageCacheMgm';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearAllCache_additionalTables']['tx_realurl_urldecodecache'] =
    'tx_realurl_urldecodecache';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearAllCache_additionalTables']['tx_realurl_urlencodecache'] =
    'tx_realurl_urlencodecache';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['tx_realurl'] =
    'EXT:realurl/class.tx_realurl_tcemain.php:&tx_realurl_tcemain';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['tx_realurl'] =
    'EXT:realurl/class.tx_realurl_tcemain.php:&tx_realurl_tcemain';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamap_preProcessFieldArray']['tx_realurl'] =
    'EXT:realurl/class.tx_realurl_processdatamap.php:&tx_realurl_processdatamap';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tstemplate.php']['linkData-PostProc']['tx_realurl'] =
    'EXT:realurl/class.tx_realurl.php:&tx_realurl->encodeSpURL';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content.php']['typoLink_PostProc']['tx_realurl'] =
    'EXT:realurl/class.tx_realurl.php:&tx_realurl->encodeSpURL_urlPrepend';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['checkAlternativeIdMethods-PostProc']['tx_realurl'] =
    'EXT:realurl/class.tx_realurl.php:&tx_realurl->decodeSpURL';

$GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] .= ',tx_realurl_pathsegment,tx_realurl_exclude,tx_realurl_pathoverride';

// Include configuration file
$_realurl_conf = @unserialize($_EXTCONF);
if (is_array($_realurl_conf)) {
    $_realurl_conf_file = trim($_realurl_conf['configFile']);
    if ($_realurl_conf_file && @file_exists(PATH_site . $_realurl_conf_file)) {
        require_once(PATH_site . $_realurl_conf_file);
    }
    unset($_realurl_conf_file);
}

unset($_realurl_conf);

// TODO this can never be true after unsetting $_realurl_conf
if ($_realurl_conf['addpageOverlayFields'] !== 0) {
    $GLOBALS['TYPO3_CONF_VARS']['FE']['pageOverlayFields'] .= ',tx_realurl_pathsegment,tx_realurl_exclude,tx_realurl_pathoverride';
}
