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
 * Class Pathgenerator
 *
 * @author  Daniel Pötzinger
 * @author  Tolleiv Nietsch
 * @package realurl
 * @subpackage realurl
 *
 * @todo check if internal cache array makes sense
 */
class Pathgenerator
{
    public $pidForCache;
    public $extconfArr; //ext_conf_template vars
    public $doktypeCache = [];

    /**
     * @var array ReaulUrl configuration (segTitleFieldList, ...)
     */
    protected $conf;

    /**
     * @var Realurl
     */
    protected $pObj;

    /**
     * @var integer
     */
    protected $rootPid;

    /**
     * @var \TYPO3\CMS\Frontend\Page\PageRepository
     */
    protected $sys_page;

        /**
         *
         * @param array $conf
         * @return void
         */
    public function init(array $conf)
    {
        $this->conf = $conf;
        $this->extconfArr = unserialize($GLOBALS ['TYPO3_CONF_VARS'] ['EXT'] ['extConf'] ['realurl']);
    }

    /**
     *
     * @param int $pid
     * @param int $langid
     * @param int $workspace
     * @return array buildPageArray
     */
    public function build($pid, $langid, $workspace)
    {
        if ($shortCutPid = $this->_checkForShortCutPageAndGetTarget($pid, $langid, $workspace)) {
            if (is_array($shortCutPid) && array_key_exists('path', $shortCutPid) && array_key_exists('rootPid', $shortCutPid)) {
                return $shortCutPid;
            }
            $pid = $shortCutPid;
        }
        $this->pidForCache = $pid;
        $rootline = $this->getRootLine($pid, $langid, $workspace);
        $firstPage = $rootline [0];
        $rootPid = $firstPage ['uid'];
        $lastPage = $rootline [count($rootline) - 1];

        $pathString = '';
        $external = false;

        if ($lastPage ['doktype'] == 3) {
            $pathString = $this->_buildExternalURL($lastPage, $langid, $workspace);
            $external = true;
        } elseif ($lastPage ['tx_realurl_pathoverride'] && $overridePath = $this->_stripSlashes($lastPage ['tx_realurl_pathsegment'])) {
            $parts = explode('/', $overridePath);
            $cleanParts = array_map([
                $this,
                'encodeTitle'
            ], $parts);
            $nonEmptyParts = array_filter($cleanParts);
            $pathString = implode('/', $nonEmptyParts);
        }
        if (! $pathString) {
            if ($this->_getDelegationFieldname($lastPage ['doktype'])) {
                $pathString = $this->_getDelegationTarget($lastPage);
                if (! preg_match('/^[a-z]+:\/\//', $pathString)) {
                    $pathString = 'http://' . $pathString;
                }
                $external = true;
            } else {
                $pathString = $this->_buildPath($this->conf ['segTitleFieldList'], $rootline);
            }
        }

        return [
            'path' => $pathString,
            'rootPid' => $rootPid,
            'external' => $external
        ];
    }

    /**
     *
     * @param string $str_org
     * @return string
     */
    public function _stripSlashes($str_org)
    {
        $str = $str_org;
        if (substr($str, - 1) == '/') {
            $str = substr($str, 0, - 1);
        }
        if (substr($str, 0, 1) == '/') {
            $str = substr($str, 1);
        }
        if ($str_org != $str) {
            return $this->_stripSlashes($str);
        } else {
            return $str;
        }
    }

    /**
     *
     * @return int Uid for Cache
     */
    public function getPidForCache()
    {
        return $this->pidForCache;
    }

    /**
     *
     * @param int $id
     * @param int $langid
     * @param int $workspace
     * @param int $reclevel
     * @return boolean
     */
    public function _checkForShortCutPageAndGetTarget($id, $langid = 0, $workspace = 0, $reclevel = 0)
    {
        if ($this->conf ['renderShortcuts']) {
            return false;
        } else {
            static $cache = [];
            $paramhash = intval($id) . '_' . intval($langid) . '_' . intval($workspace) . '_' . intval($reclevel);

            if (isset($cache[$paramhash])) {
                return $cache[$paramhash];
            }

            $returnValue = false;

            if ($reclevel > 20) {
                $returnValue =  false;
            }
            $this->_initSysPage(0, $workspace); // check defaultlang since overlays should not contain this (usually)
            $result = $this->sys_page->getPage($id);

                // if overlay for the of shortcuts is requested
            if ($this->extconfArr ['localizeShortcuts'] && \TYPO3\CMS\Core\Utility\GeneralUtility::inList($GLOBALS ['TYPO3_CONF_VARS'] ['FE'] ['pageOverlayFields'], 'shortcut') && $langid) {
                $resultOverlay = $this->_getPageOverlay($id, $langid);
                if ($resultOverlay ['shortcut']) {
                    $result ['shortcut'] = $resultOverlay ['shortcut'];
                }
            }

            if ($result ['doktype'] == 4) {
                switch ($result ['shortcut_mode']) {
                    case '1': //firstsubpage
                        if ($reclevel > 10) {
                            $returnValue = false;
                        }
                        $where = 'pid="' . $id . '"';
                        $query = $GLOBALS ['TYPO3_DB']->exec_SELECTquery('uid', 'pages', $where, '', 'sorting', '0,1');
                        if ($query) {
                            $resultfirstpage = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc($query);
                        }
                        $subpageShortCut = $this->_checkForShortCutPageAndGetTarget($resultfirstpage ['uid'], $langid, $workspace, $reclevel+1);
                        if ($subpageShortCut !== false) {
                            $returnValue = $subpageShortCut;
                        } else {
                            $returnValue = $resultfirstpage ['uid'];
                        }
                        break;
                    case '2': //random
                        $returnValue = false;
                        break;
                    default:
                        if ($result ['shortcut'] == $id) {
                            $returnValue = false;
                        } else {
                            //look recursive:
                            $subpageShortCut = $this->_checkForShortCutPageAndGetTarget($result ['shortcut'], $langid, $workspace, $reclevel+1);
                            if ($subpageShortCut !== false) {
                                $returnValue = $subpageShortCut;
                            } else {
                                $returnValue = $result ['shortcut'];
                            }
                        }
                        break;
                }
            } elseif ($this->_getDelegationFieldname($result ['doktype'])) {
                $target = $this->_getDelegationTarget($result, $langid, $workspace);
                if (is_numeric($target)) {
                    $res = $this->_checkForShortCutPageAndGetTarget($target, $langid, $workspace, $reclevel-1);
                    //if the recursion fails we keep the original target
                    if ($res === false) {
                        $res = $target;
                    }
                } else {
                    $res = $result ['uid'];
                }
                $returnValue = $res;
            } else {
                $returnValue = false;
            }

            $cache[$paramhash] = $returnValue;
            return $returnValue;
        }
    }

    /**
     * set the rootpid that is used for generating the path. (used to stop rootline on that pid)
     *
     * @param int $id
     * @return void
     */
    public function setRootPid($id)
    {
        $this->rootPid = $id;
    }

    /**
     * @param Realurl $pObj
     * @return void
     */
    public function setParentObject(Realurl $pObj)
    {
        $this->pObj = $pObj;
    }

    /**
     *
     * @param integer $pid UID of the page where the rootline should be retrieved
     * @param integer $langID
     * @param integer $wsId
     * @param string $mpvar Comma separated list of mount point parameters
     * @return array array with rootline for pid
     * @throws RootlineException
     */
    public function getRootLine($pid, $langID, $wsId, $mpvar = '')
    {
        // Get rootLine for current site (overlaid with any language overlay records).
        $this->_initSysPage($langID, $wsId);
        $rootLine = $this->sys_page->getRootLine($pid, $mpvar);

        // Only return rootline to the given rootpid
        $rootPidFound = false;
        while (!$rootPidFound && count($rootLine) > 0) {
            $last = array_pop($rootLine);
            if ($last['uid'] == $this->rootPid) {
                $rootPidFound = true;
                $rootLine[] = $last;
                break;
            }
        }
        if (!$rootPidFound) {
            throw new RootlineException(
                'The configured root pid ' . $this->rootPid . ' could not be found in the rootline of page ' . $pid,
                1481273270
            );
        }

        $siteRootLine = [];
        $c = count($rootLine);
        foreach ($rootLine as $val) {
            $c--;
            $siteRootLine[$c] = $val;
        }

        return $siteRootLine;
    }

    /**
     * Builds the path based on the rootline
     *
     * @param string $segment configuration wich field from database should use
     * @param array $rootline The rootLine  from the actual page
     * @return string
     **/
    public function _buildPath($segment, $rootline)
    {
        $path = [];
        $rootline = array_reverse($rootline);
        $segment = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $segment);

        // Do not include rootpage itself, except it is only the root and filename is set
        if (count($rootline) > 1 || $rootline[0]['tx_realurl_pathsegment'] === '') {
            array_shift($rootline);
        }

        $i = 0;
        foreach ($rootline as $page) {
            // Continue if page should be excluded from path (if not last)
            if (++$i !== count($rootline) && $page['tx_realurl_exclude']) {
                continue;
            }

            // (1) Language Overlay
            $pathSegment = $this->_getPathSeg($page, $segment);

            // (2) Default Language
            if (empty($pathSegment) && $page['_PAGES_OVERLAY']) {
                $pathSegment = $this->_getPathSeg($this->_getDefaultRecord($page), $segment);
            }

            // (3) Fallback
            if (empty($pathSegment)) {
                $pathSegment = 'page_' . $page['uid'];
            }

            $path [] = $pathSegment;
        }

        return implode('/', $path);
    }

    /**
     *
     * @param array $pageRec
     * @param array $segments
     * @return string
     */
    public function _getPathSeg($pageRec, $segments)
    {
        $retVal = '';
        foreach ($segments as $segmentName) {
            if ($this->encodeTitle($pageRec [$segmentName]) != '') {
                $retVal = $this->encodeTitle($pageRec [$segmentName]);
                break;
            }
        }
        return $retVal;
    }

    /**
     *
     * @param array $l10nrec
     * @return array
     */
    public function _getDefaultRecord(array $l10nrec)
    {
        $lang = $this->sys_page->sys_language_uid;
        $this->sys_page->sys_language_uid = 0;
        $rec = $this->sys_page->getPage($l10nrec['uid']);
        $this->sys_page->sys_language_uid = $lang;

        return $rec;
    }

    /**
     *
     * @param int $doktype
     * @return boolean
     */
    public function isDelegationDoktype($doktype)
    {
        if (! array_key_exists($doktype, $this->doktypeCache)) {
            $this->doktypeCache [$doktype] = ($this->_getDelegationFieldname($doktype)) ? true : false;
        }
        return $this->doktypeCache [$doktype];
    }

    /**
     *
     * @param int $doktype
     * @return string
     */
    public function _getDelegationFieldname($doktype)
    {
        if (is_array($this->conf ['delegation']) && array_key_exists($doktype, $this->conf ['delegation'])) {
            return $this->conf ['delegation'] [$doktype];
        } elseif (is_array($GLOBALS ['TYPO3_CONF_VARS'] ['EXTCONF'] ['realurl'] ['delegate']) && array_key_exists($doktype, $GLOBALS ['TYPO3_CONF_VARS'] ['EXTCONF'] ['realurl'] ['delegate'])) {
            return $GLOBALS ['TYPO3_CONF_VARS'] ['EXTCONF'] ['realurl'] ['delegate'] [$doktype];
        } else {
            return false;
        }
    }

    /**
     *
     * @param array $record
     * @param int $langid
     * @param int $workspace
     * @return int
     */
    public function _getDelegationTarget($record, $langid = 0, $workspace = 0)
    {
        $fieldname = $this->_getDelegationFieldname($record ['doktype']);

        if (! array_key_exists($fieldname, $record)) {
            $this->_initSysPage($langid, $workspace);
            $record = $this->sys_page->getPage($record ['uid']);
        }

        $parts = explode(' ', $record [$fieldname]);

        return $parts [0];
    }

    /*******************************
     *
     * Helper functions
     *
     ******************************/
    /**
     * Convert a title to something that can be used in an page path:
     * - Convert spaces to underscores
     * - Convert non A-Z characters to ASCII equivalents
     * - Convert some special things like the 'ae'-character
     * - Strip off all other symbols
     * Works with the character set defined as "forceCharset"
     *
     * @param string $title Input title to clean
     * @return string Encoded title, passed through rawurlencode() = ready to put in the URL.
     * @see rootLineToPath()
     */
    public function encodeTitle($title)
    {
        // Fetch character set:
        $charset = $GLOBALS ['TYPO3_CONF_VARS'] ['BE'] ['forceCharset'] ? $GLOBALS ['TYPO3_CONF_VARS'] ['BE'] ['forceCharset'] : $GLOBALS ['TSFE']->defaultCharSet;
            // Convert to lowercase:
        $processedTitle = $GLOBALS ['TSFE']->csConvObj->conv_case($charset, $title, 'toLower');
            // Convert some special tokens to the space character:
        $space = isset($this->conf ['spaceCharacter']) ? $this->conf ['spaceCharacter'] : '-';
        $processedTitle = preg_replace('/[\s+]+/', $space, $processedTitle); // convert spaces
            // Convert extended letters to ascii equivalents:
        $processedTitle = $GLOBALS ['TSFE']->csConvObj->specCharsToASCII($charset, $processedTitle);
            // Strip the rest
        if ($this->pObj->extConf['init']['enableAllUnicodeLetters']) {
            // Warning: slow!!!
            $processedTitle = preg_replace('/[^\p{L}0-9' . ($space ? preg_quote($space) : '') . ']/u', $space, $processedTitle);
        } else {
            $processedTitle = preg_replace('/[^a-zA-Z0-9' . ($space ? preg_quote($space) : '') . ']/', $space, $processedTitle);
        }
        $processedTitle = preg_replace('/\\' . $space . '+/', $space, $processedTitle);
        $processedTitle = trim($processedTitle, $space);
        if ($this->conf ['encodeTitle_userProc']) {
            $params = [
                'pObj' => &$this,
                'title' => $title,
                'processedTitle' => $processedTitle
            ];
            $processedTitle = \TYPO3\CMS\Core\Utility\GeneralUtility::callUserFunction($this->conf ['encodeTitle_userProc'], $params, $this);
        }
            // Return encoded URL:
        return rawurlencode($processedTitle);
    }

    /**
     *
     *
     * @param int $langID
     * @param int $workspace
     * @return void
     */
    public function _initSysPage($langID, $workspace)
    {
        if (! is_object($this->sys_page)) {
            /**
             * Initialize the page-select functions.
             * don't use $GLOBALS['TSFE']->sys_page here this might
             * lead to strange side-effects due to the fact that some
             * members of sys_page are modified.
             *
             * I also opted against "clone $GLOBALS['TSFE']->sys_page"
             * since this might still cause race conditions on the object
             **/
            $this->sys_page = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Page\PageRepository::class);
        }
        $this->sys_page->sys_language_uid = $langID;
        if ($workspace != 0 && is_numeric($workspace)) {
            $this->sys_page->versioningWorkspaceId = $workspace;
            $this->sys_page->versioningPreview = 1;
        } else {
            $this->sys_page->versioningWorkspaceId = 0;
            $this->sys_page->versioningPreview = false;
        }
    }

    /**
     *
     * @param array $page
     * @param int $langid
     * @param int $workspace
     * @return string
     */
    public function _buildExternalURL($page, $langid = 0, $workspace = 0)
    {
        $this->_initSysPage(0, $workspace); // check defaultlang since overlays should not contain this (usually)
        $fullPageArr = $this->sys_page->getPage($page ['uid']);
        if ($langid) {
            $fullPageArr = array_merge($fullPageArr, $this->_getPageOverlay($page ['uid'], $langid));
        }

        $prefix = false;
        $prefixItems = $GLOBALS ['TCA'] ['pages'] ['columns'] ['urltype'] ['config'] ['items'];
        if (is_array($prefixItems)) {
            foreach ($prefixItems as $prefixItem) {
                if (intval($prefixItem ['1']) == intval($fullPageArr ['urltype'])) {
                    $prefix = $prefixItem ['0'];
                    break;
                }
            }
        }

        if (! $prefix) {
            $prefix = 'http://';
        }
        return $prefix . $fullPageArr ['url'];
    }

    /**
     *
     * @param int $id
     * @param int $langid
     * @return array
     */
    public function _getPageOverlay($id, $langid = 0)
    {
        $relevantLangId = $langid;
        if ($this->extconfArr['useLanguagevisibility']
            && \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('languagevisibility')
        ) {
            require_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('languagevisibility') . 'class.tx_languagevisibility_feservices.php');
            $relevantLangId = tx_languagevisibility_feservices::getOverlayLanguageIdForElementRecord($id, 'pages', $langid);
        }
        return $this->sys_page->getPageOverlay($id, $relevantLangId);
    }
}
