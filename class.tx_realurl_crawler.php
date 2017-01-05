<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009 AOE media
 *  All rights reserved
 *
 *  This script is part of the Typo3 project. The Typo3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Class tx_realurl_crawler
 */
class tx_realurl_crawler
{
    /**
     * Flushes the RealURL decode/encode caches if a page is being crawled
     * (called from TypoScriptFrontendController, see ext_localconf.php for configuration)
     *
     * @param \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $pObj
     * @param integer $timeOutTime
     * @return void
     */
    public function insertPageIncache(&$pObj, $timeOutTime)
    {
        if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('crawler')
            || !$pObj->applicationData['tx_crawler']['running']
        ) {
            return;
        }

        $GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_realurl_urlencodecache', 'page_id=' . intval($GLOBALS['TSFE']->id));
        $GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_realurl_urldecodecache', 'page_id=' . intval($GLOBALS['TSFE']->id));
        $GLOBALS['TSFE']->applicationData['tx_realurl']['_CACHE'] = [];

        $lconf = [];
        $lconf['parameter'] = $GLOBALS['TSFE']->id;
        $lconf['returnLast'] = 'url';

        $loginfos = '(lang: ' . $GLOBALS['TSFE']->sys_language_uid . ' langc:' . $GLOBALS['TSFE']->sys_language_content . ')';

        $pObj->applicationData['realurl']['crawlermode'] = true;
        $pObj->applicationData['tx_crawler']['log'][] = 'force link generation: ' . $GLOBALS['TSFE']->cObj->typolink('test', $lconf) . $loginfos;
        $pObj->applicationData['realurl']['crawlermode'] = false;
    }

    /**
     * Hook wich disable the page cache if the current request is made by tx_crawler
     *
     * @see TypoScriptFrontendController::headerNoCache
     *
     * @param array $params Frontend parameter data
     * @param tslib_fe $tsfe Current TSFE
     * @return void
     */
    public function headerNoCache(&$params, $tsfe)
    {
        if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('crawler')
            || !$params['pObj']->applicationData['tx_crawler']['running']
        ) {
            return;
        }

        // Disables a look-up for cached page data - thus resulting in re-generation of the page even if cached.
        $params['disableAcquireCacheData'] = true;
        $params['pObj']->applicationData['tx_crawler']['log'][] = 'Force page generation (realurl - rebuild)';
    }
}
