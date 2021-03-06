<?php
namespace AOE\Realurl;

use AOE\Realurl\Exception\RootlineException;

/***************************************************************
 * Copyright notice
 *
 * (c) 2008 AOE media
 * All rights reserved
 *
 * This script is part of the Typo3 project. The Typo3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the textfile GPL.txt and important notices to the license
 * from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Class Pagepath
 *
 * @author    Daniel Pötzinger
 * @package realurl
 * @subpackage realurl
 *
 * @todo    check if internal cache array can improve speed
 * @todo    check last updatetime of pages
 */
class Pagepath
{
    /**
     * @var array $conf
     */
    protected $conf;

    /**
     * @var Pathgenerator $generator
     */
    protected $generator;

    /**
     * @var Realurl $pObj
     */
    protected $pObj;

    /**
     * @var Cachemgmt $cachemgmt
     */
    protected $cachemgmt;

    /** Main function -> is called from real_url
     * parameters and results are in $params (some by reference)
     *
     * @param    array        Parameters passed from parent object, "tx_realurl". Some values are passed by reference! (paramKeyValues, pathParts and pObj)
     * @param    Realurl        Copy of parent object.
     * @return    mixed        Depends on branching.
     */
    public function main($params, $ref)
    {
        // Setting internal variables:
        $this->_setParent($ref);
        $this->_setConf($params ['conf']);
        //TODO is this needed ??
        srand(); //init rand for cache

        $this->initGenerator();

        switch ((string) $params ['mode']) {
            case 'encode':
                $this->initCacheMgm($this->_getLanguageVarEncode());
                $path = $this->_id2alias($params ['paramKeyValues']);
                $params ['pathParts'] = array_merge($params ['pathParts'], $path);
                unset($params ['paramKeyValues'] ['id']);

                return;
                break;
            case 'decode':
                $this->initCacheMgm($this->_getLanguageVarDecode());
                $id = $this->_alias2id($params ['pathParts']);

                return [
                    $id,
                    []
                ];
                break;
        }
    }

    /**
     * gets the path for a pageid, must store and check the generated path in cache
     * (should be aware of workspace)
     *
     * @param array $paramKeyValues from real_url
     * @return string with path
     */
    protected function _id2alias($paramKeyValues)
    {
        $pageId = $paramKeyValues['id'];
        if (!is_numeric($pageId) && is_object($GLOBALS ['TSFE']->sys_page)) {
            $pageId = $GLOBALS['TSFE']->sys_page->getPageIdFromAlias($pageId);
        }
        if ($this->_isCrawlerRun() && $GLOBALS['TSFE']->id == $pageId) {
            $GLOBALS['TSFE']->applicationData['tx_crawler']['log'][] = 'realurl: _id2alias ' . $pageId . '/' . $this->_getLanguageVarEncode() . '/' . $this->_getWorkspaceId();
            //clear this page cache:
            $this->cachemgmt->markAsDirtyCompletePid($pageId);
        }

        $buildedPath = $this->cachemgmt->isInCache($pageId);

        if (!$buildedPath) {
            $buildPageArray = $this->generator->build($pageId, $this->_getLanguageVarEncode(), $this->_getWorkspaceId());
            $buildedPath = $buildPageArray['path'];
            $buildedPath = $this->cachemgmt->storeUniqueInCache(
                $this->generator->getPidForCache(),
                $buildedPath,
                $buildPageArray['external']
            );
            if ($this->_isCrawlerRun() && $GLOBALS['TSFE']->id == $pageId) {
                $GLOBALS['TSFE']->applicationData['tx_crawler']['log'][] = 'created: ' . $buildedPath . ' pid:' . $pageId . '/' . $this->generator->getPidForCache();
            }
        }
        if ($buildedPath) {
            $pagePath_exploded = explode('/', $buildedPath);

            return $pagePath_exploded;
        } else {
            return [];
        }
    }

    /**
     * Gets the pageid from a pagepath, needs to check the cache
     *
     * @param    array        Array of segments from virtual path
     * @return    integer        Page ID
     */
    protected function _alias2id(&$pagePath)
    {
        if (0 === count($pagePath)) {
            return false;
        }

        $pagePathOrigin = $pagePath;

        // Page path is urlencoded in cache tables, so make sure path segments are encoded the same way, otherwise cache will miss
        if ($this->pObj->extConf['init']['enableAllUnicodeLetters']) {
            array_walk(
                $pagePathOrigin,
                function (&$pathSegment) { $pathSegment = mb_detect_encoding($pathSegment, "ASCII", TRUE) ? $pathSegment : rawurlencode($pathSegment); }
            );
        }

        $keepPath = [];
        //Check for redirect
        $this->_checkAndDoRedirect($pagePathOrigin);
        //read cache with the path you get, decrease path if nothing is found
        $pageId = $this->cachemgmt->checkCacheWithDecreasingPath($pagePathOrigin, $keepPath);
        //fallback 1 - use unstrict cache where
        /**
         * @todo
         * @issue http://bugs.aoedev.com/view.php?id=19834
         */
        if (false === $pageId) {
            $this->cachemgmt->useUnstrictCacheWhere();
            $keepPath = [];
            $pageId = $this->cachemgmt->checkCacheWithDecreasingPath($pagePathOrigin, $keepPath);
            $this->cachemgmt->doNotUseUnstrictCacheWhere();
        }
        //fallback 2 - look in history
        if (false === $pageId) {
            $keepPath = [];
            $pageId = $this->cachemgmt->checkHistoryCacheWithDecreasingPath($pagePathOrigin, $keepPath);
        }

        // Fallback 3 - Reverse lookup (default language only)
        if (false === $pageId && true === (bool) $this->generator->extconfArr['enablePagesReverseLookup']) {
            $lastPathSegment = end($pagePath);
            $possiblePageIds = $this->findPossiblePageIds($lastPathSegment);
            $pageId = $this->findFirstMatchingPageId($possiblePageIds, $pagePath);
            if (is_numeric($pageId)) {
                $keepPath = [];
            }
        }

        $pagePath = $keepPath;

        return $pageId;
    }

    /**
     * Returns an array of page ids probably matching a given path segment
     *
     * @param string $pathSegment
     * @return array
     */
    private function findPossiblePageIds($pathSegment)
    {
        $possiblePageRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'uid,pid,doktype',
            'pages',
            $this->createWildcardWhereClause($pathSegment)
        );

        $possiblePageIds = [];
        foreach ($possiblePageRecords as $possiblePageRecord) {
            // Prevent assigning a path segment to a shortcut, which would cause a redirect loop
            // if the shortcut has a lower page id and the target's page path is not available
            if (\TYPO3\CMS\Frontend\Page\PageRepository::DOKTYPE_SHORTCUT === (integer) $possiblePageRecord['doktype']) {
                continue;
            }

            // Exclude workspace records as these are neither accessible nor connected to any root line
            if (0 > (integer) $possiblePageRecord['pid']) {
                continue;
            }

            $possiblePageIds[] = $possiblePageRecord['uid'];
        }
        $possiblePageIds = $this->filterByConfiguredRootPageId($possiblePageIds);

        return $possiblePageIds;
    }

    /**
     * Creates a wildcard WHERE clause, replacing the configured space character with MySQL wildcards
     *
     * @param string $pathSegment
     * @return string
     */
    private function createWildcardWhereClause($pathSegment)
    {
        $spaceCharacter = isset($this->conf['spaceCharacter']) ? $this->conf['spaceCharacter'] : '-';
        $titleFieldList = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $this->conf['segTitleFieldList']);

        $whereClause = [];
        foreach ($titleFieldList as $titleField) {
            $whereClause[] = $titleField . ' LIKE ' . $GLOBALS['TYPO3_DB']->fullQuotestr('%' . str_replace($spaceCharacter,
                        '%', $pathSegment) . '%', 'pages)');
        }

        return implode('OR ', $whereClause);
    }

    /**
     * Filters an array of page ids by the configured root page id
     *
     * @param array $pageIds
     * @return array
     */
    private function filterByConfiguredRootPageId(array $pageIds)
    {
        $filteredPageIds = [];
        foreach ($pageIds as $pageId) {
            $rootLine = \TYPO3\CMS\Backend\Utility\BackendUtility::BEgetRootLine($pageId);
            foreach ($rootLine as $pageInRootLine) {
                if ((int) $pageInRootLine['uid'] === (int) $this->conf['rootpage_id']) {
                    $filteredPageIds[] = $pageId;
                    break;
                }
            }
        }

        return $filteredPageIds;
    }

    /**
     * Returns the page id matching a given page path by generating the RealURL path
     * for each potential match and comparing it against the actual path
     *
     * @param array $possiblePageIds
     * @param string $pagePath
     * @return integer|boolean false if no matching page id was found
     */
    private function findFirstMatchingPageId(array $possiblePageIds, $pagePath)
    {
        foreach ($possiblePageIds as $possiblePageId) {
            try {
                $possiblePagePath = $this->_id2alias(['id' => $possiblePageId]);
            } catch (RootlineException $e) {
                continue;
            }

            if ($possiblePagePath === $pagePath) {
                return $possiblePageId;
            }
        }

        return false;
    }

    /**
     *
     * @param string $path
     * @return void
     */
    protected function _checkAndDoRedirect($path)
    {
        $_params = [];
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][__CLASS__]['checkAndDoRedirect'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][__CLASS__]['checkAndDoRedirect'] as $_funcRef) {
                \TYPO3\CMS\Core\Utility\GeneralUtility::callUserFunction($_funcRef, $_params, $this);
            }
        }
    }

    /**
     *
     * @return int
     */
    protected function _getRootPid()
    {
        // Find the PID where to begin the resolve:
        if ($this->conf ['rootpage_id']) { // Take PID from rootpage_id if any:
            $pid = intval($this->conf ['rootpage_id']);
        } else {
            //if not defined in realUrlConfig get 0
            $pid = 0;
        }

        return $pid;
    }

    /**
     * DECODE
     * Find the current language id.
     *
     * The languageid is used by cachemgmt in order to retrieve the correct pid for the given path
     * -that means it needs to return the languageid of the current context:
     * (means the L parameter value after realurl processing)
     *
     * @return integer Current language id
     *
     * @author Michael Klapper <michael.klapper@aoe.com>
     */
    public function _getLanguageVarDecode()
    {
        $getVarName = $this->conf['languageGetVar'] ? $this->conf['languageGetVar'] : 'L';
        $lang = $this->pObj->getRetrievedPreGetVar($getVarName);

        if ($this->conf['languageGetVarPostFunc']) {
            $lang = \TYPO3\CMS\Core\Utility\GeneralUtility::callUserFunction($this->conf['languageGetVarPostFunc'], $lang, $this);
        }

        return (int) $lang;
    }

    /**
     * ENCODE
     * Find the current language id.
     *
     * The langugeid is used to build the path + to cache the path
     * - if in the url parameters it is forced to generate the url in a specific language it needs to use this (L parameter defined in typolink)
     *
     * - orig_paramKeyValues is set by realurl during encoding, and it has the L paremeter value that is passed to typolink
     *
     * @return integer Current language id
     *
     * @author Michael Klapper <michael.klapper@aoe.com>
     */
    public function _getLanguageVarEncode()
    {
        $lang = false;
        $getVarName = $this->conf ['languageGetVar'] ? $this->conf ['languageGetVar'] : 'L';
        // $orig_paramKeyValues  Contains the index of GETvars that the URL had when the encoding began.
        // Setting the language variable based on GETvar in URL which has been configured to carry the language uid:
        if ($getVarName && array_key_exists($getVarName, $this->pObj->orig_paramKeyValues)) {
            $lang = intval($this->pObj->orig_paramKeyValues[$getVarName]);
            // Might be excepted (like you should for CJK cases which does not translate to ASCII equivalents)
            if (\TYPO3\CMS\Core\Utility\GeneralUtility::inList($this->conf['languageExceptionUids'], $lang)) {
                $lang = 0;
            }
        }

        if ($this->conf['languageGetVarPostFunc']) {
            $lang = \TYPO3\CMS\Core\Utility\GeneralUtility::callUserFunction($this->conf['languageGetVarPostFunc'], $lang, $this);
        }

        return (int) $lang;
    }

    /**
     * if workspace preview in FE return that workspace
     *
     * @return int
     */
    public function _getWorkspaceId()
    {
        if (is_object($GLOBALS ['BE_USER']) && \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('ADMCMD_noBeUser') != 1) {
            if (is_object($GLOBALS ['TSFE']->sys_page)) {
                if ($GLOBALS ['TSFE']->sys_page->versioningPreview == 1) {
                    return $GLOBALS ['TSFE']->sys_page->versioningWorkspaceId;
                }
            } else {
                if ($GLOBALS ['BE_USER']->user ['workspace_preview'] == 1) {
                    return $GLOBALS ['BE_USER']->workspace;
                }
            }
        }

        return 0;
    }

    /**
     * returns true/false if the current context is within a crawler call
     * This is used for some logging. The status is cached for performance reasons
     *
     * @return boolean
     */
    public function _isCrawlerRun()
    {
        if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('crawler')
            && $GLOBALS['TSFE']->applicationData['tx_crawler']['running']
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * assigns the configuration
     *
     * @param $conf
     * @return void
     */
    public function _setConf($conf)
    {
        //TODO: validate the incoming conf
        $this->conf = $conf;
    }

    /**
     * assigns the parent object
     *
     * @param tx_realurl $ref : the parent object
     * @return void
     */
    public function _setParent($ref)
    {
        $this->pObj = &$ref;
    }

    /**
     * Initialize the pathgenerator
     *
     */
    public function initGenerator()
    {
        $this->generator = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(Pathgenerator::class);
        $this->generator->init($this->conf);
        $this->generator->setRootPid($this->_getRootPid());
        $this->generator->setParentObject($this->pObj);
    }

    /**
     * Initialize the Cache-Layer
     *
     * @param integer $lang Current language value
     * @return void
     */
    public function initCacheMgm($lang)
    {
        $this->cachemgmt = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(Cachemgmt::class, $this->_getWorkspaceId(), $lang);
        $this->cachemgmt->setCacheTimeOut($this->conf['cacheTimeOut']);
        $this->cachemgmt->setRootPid($this->_getRootPid());
    }
}
