<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Dmitry Dulepov (dmitry@typo3.org)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
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
 * TCEmain hook to update various caches when data is modified in TYPO3 Backend
 *
 * @author	Dmitry Dulepov <dmitry@typo3.org>
 */
class tx_realurl_tcemain
{

    /**
     * RealURL configuration for the current host
     *
     * @var array
     */
    protected $config;

    /**
     * Clears RealURL caches if necessary
     *
     * @param string $command
     * @param string $tableName
     * @param int $recordId
     * @return void
     */
    protected function clearCaches($command, $tableName, $recordId)
    {
        if ($this->isTableForCache($tableName)) {
            if ($command == 'delete' || $command == 'move') {
                list($pageId, ) = $this->getPageData($tableName, $recordId);
                $this->fetchRealURLConfiguration($pageId);
                if ($command == 'delete') {
                    $this->clearPathCache($pageId);
                } else {
                    $this->expirePathCacheForAllLanguages($pageId);
                }
                $this->clearOtherCaches($pageId);
            }
        }
    }

    /**
     * Clears URL decode and encode caches for the given page
     *
     * @param $pageId
     * @return void
     */
    protected function clearOtherCaches($pageId)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $GLOBALS['TYPO3_DB']->exec_DELETEquery(
            'tx_realurl_urldecodecache',
            'page_id=' . intval($pageId)
        );
        /** @noinspection PhpUndefinedMethodInspection */
        $GLOBALS['TYPO3_DB']->exec_DELETEquery(
            'tx_realurl_urlencodecache',
            'page_id=' . intval($pageId)
        );
    }

    /**
     * Clears path cache for the given page id
     *
     * @param int $pageId
     * @return void
     */
    protected function clearPathCache($pageId)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $GLOBALS['TYPO3_DB']->exec_DELETEquery(
            'tx_realurl_cache',
            'pageid=' . intval($pageId)
        );
    }

    /**
     * Removes unique alias in case if the record is deleted from the table
     *
     * @param string $command
     * @param string $tableName
     * @param mixed $recordId
     * @return void
     */
    protected function clearUniqueAlias($command, $tableName, $recordId)
    {
        if ($command == 'delete') {
            /** @noinspection PhpUndefinedMethodInspection */
            $GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_realurl_uniqalias',
                'tablename=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($tableName, 'tx_realurl_uniqalias') .
                ' AND value_id=' . intval($recordId));
        }
    }

    /**
     * Expires record in the path cache
     *
     * @param int $pageId
     * @param int $languageId
     * @return void
     */
    protected function expirePathCache($pageId, $languageId)
    {
        $pageIds = $this->getChildPages($pageId);
        $pageIds[] = intval($pageId);

        /** @noinspection PhpUndefinedMethodInspection */
        $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
            'tx_realurl_cache',
            'pageid IN (' . implode(',', $pageIds) . ') AND languageid=' . intval($languageId),
            array(
                'dirty' => '1'
            ));
    }

    /**
     * Expires record in the path cache
     *
     * @param int $pageId
     * @return void
     */
    protected function expirePathCacheForAllLanguages($pageId)
    {
        $pageIds = $this->getChildPages($pageId);
        $pageIds[] = intval($pageId);

        /** @noinspection PhpUndefinedMethodInspection */
        $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
            'tx_realurl_cache',
            'pageid IN (' . implode(',', $pageIds) . ')',
            array(
                'dirty' => '1'
            ));
    }

    /**
     * Fetches RealURl configuration for the given page
     *
     * @param int $pageId
     * @return void
     */
    protected function fetchRealURLConfiguration($pageId)
    {
        $rootLine = t3lib_BEfunc::BEgetRootLine($pageId);
        $rootPageId = $rootLine[1]['uid'];
        $this->config = array();
        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'] as $config) {
                if (is_array($config) && $config['pagePath']['rootpage_id'] == $rootPageId) {
                    $this->config = $config;
                    return;
                }
            }
            if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['_DEFAULT'])) {
                $this->config = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['_DEFAULT'];
            }
        } else {
            t3lib_div::sysLog('RealURL is not configured! Please, configure it or uninstall.', 'RealURL', 3);
        }
    }

    /**
     * Returns the IDs of all child pages of a given $pageID.
     *
     * @param $pageId integer Page ID to start searching
     * @return int[] Child pages
     */
    protected function getChildPages($pageId)
    {
        $children  = array();

        /** @var $tree t3lib_pageTree */
        $tree = t3lib_div::makeInstance('t3lib_pageTree');
        $tree->init('AND ' . $GLOBALS['BE_USER']->getPagePermsClause(1));
        $this->makeHTML = false;
        $tree->getTree($pageId, 99, '');

        foreach ($tree->tree as $data) {
            $children[] = intval($data['row']['uid']);
        }

        return $children;
    }

    /**
     * Obtains expiration time for the path cache records
     *
     * @return int
     */
    protected function getExpirationTime()
    {
        $timeOffset = (isset($this->config['pagePath']['expireDays']) ? $this->config['pagePath']['expireDays'] : 60) * 24 * 3600;
        $date = getdate(time() + $timeOffset);
        return mktime(0, 0, 0, $date['mon'], $date['mday'], $date['year']);
    }

    /**
     * Obtains real page id and language from the table name and passed id of the record in the table.
     *
     * @param $tableName
     * @param $id
     * @return array First member is page id, second is language
     */
    protected static function getPageData($tableName, $id)
    {
        if ($tableName == 'pages_language_overlay') {
            $result = self::getInfoFromOverlayPid($id);
        } else {
            $result = array($id, 0);
        }
        return $result;
    }

    /**
     * Retrieves field list to check for modification
     *
     * @param string $tableName
     * @return	array
     */
    protected function getFieldList($tableName)
    {
        if ($tableName == 'pages_language_overlay') {
            $fieldList = TX_REALURL_SEGTITLEFIELDLIST_PLO;
        } else {
            if (isset($this->config['pagePath']['segTitleFieldList'])) {
                $fieldList = $this->config['pagePath']['segTitleFieldList'];
            } else {
                $fieldList = TX_REALURL_SEGTITLEFIELDLIST_DEFAULT;
            }
        }
        $fieldList .= ',hidden';
        return array_unique(t3lib_div::trimExplode(',', $fieldList, true));
    }

    /**
     * Retrieves real page id given its overlay id
     *
     * @param	int		$pid	Page id
     * @return	array		Array with two members: real page uid and sys_language_uid
     */
    protected static function getInfoFromOverlayPid($pid)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        list($rec) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('pid,sys_language_uid',
            'pages_language_overlay', 'uid=' . intval($pid));
        return array($rec['pid'], $rec['sys_language_uid']);
    }

    /**
     * Checks if the update table can affect cache entries
     *
     * @param string $tableName
     * @return boolean
     */
    protected static function isTableForCache($tableName)
    {
        return ($tableName == 'pages' || $tableName == 'pages_language_overlay');
    }

    /**
     * A TCEMain hook to update caches when something happens to a page or
     * language overlay.
     *
     * @param string $command
     * @param string $tableName
     * @param mixed $recordId
     * @return void
     */
    public function processCmdmap_postProcess($command, $tableName, $recordId)
    {
        $this->clearCaches($command, $tableName, $recordId);
        $this->clearUniqueAlias($command, $tableName, $recordId);
    }

    /**
     * A TCEmain hook to expire old records and add new ones
     *
     * @param string $status 'new' (ignoring) or 'update'
     * @param string $tableName
     * @param int $recordId
     * @param array $databaseData
     * @param t3lib_tcemain $reference
     * @return void
     * @todo Expire unique alias cache: how to get the proper timeout value easily here?
     */
    public function processDatamap_afterDatabaseOperations($status, $tableName, $recordId, array $databaseData, t3lib_tcemain &$reference)
    {
        $this->processContentUpdates($status, $tableName, $recordId, $databaseData);
        $this->markCachesDirty($tableName, $recordId, $reference);
    }


    /**
     * In case an page-overlay is created automatically the excludeFromMiddle value needs to be copied
     * See issue #12007
     *
     * @author	Tolleiv Nietsch
     * @package realurl
     * @subpackage aoe_realurlpath
     * @param array $incomingFieldArray
     * @param string $table
     * @param string $id
     * @param object $ref
     * @return void
     */
    public function processDatamap_preProcessFieldArray(&$incomingFieldArray, $table, $id, t3lib_tcemain &$reference)
    {
        if ($table != 'pages_language_overlay' || $id != 'NEW') {
            return;
        }

        if (intval($incomingFieldArray['pid'])) {
            $parent = t3lib_BEfunc::getRecord('pages', intval($incomingFieldArray['pid']), 'uid,pid,tx_realurl_exclude');
            t3lib_BEfunc::workspaceOL('pages', $parent);
            if ($parent['tx_realurl_exclude']) {
                $incomingFieldArray['tx_realurl_exclude'] = $parent['tx_realurl_exclude'];
            }
        }
    }

    /**
     * Processes page and content changes in regard to RealURL caches.
     *
     * @param string $status
     * @param string $tableName
     * @param int $recordId
     * @param array $databaseData
     * @return void
     * @todo Handle changes to tx_realurl_exclude recursively
     */
    protected function processContentUpdates($status, $tableName, $recordId, array $databaseData)
    {
        if ($status == 'update' && tx_realurl::testInt($recordId)) {
            list($pageId, $languageId) = $this->getPageData($tableName, $recordId);
            $this->fetchRealURLConfiguration($pageId);
            if ($this->shouldFixCaches($tableName, $databaseData)) {
                if (isset($databaseData['alias'])) {
                    $this->expirePathCacheForAllLanguages($pageId);
                } else {
                    $this->expirePathCache($pageId, $languageId);
                }
                $this->clearOtherCaches($pageId);
            }
        }
    }

    /**
     * Checks if we need to fix caches
     *
     * @param string $tableName
     * @param array $databaseData
     * @return boolean
     */
    protected function shouldFixCaches($tableName, array $databaseData)
    {
        $result = false;
        if (self::isTableForCache($tableName)) {
            $interestingFields = $this->getFieldList($tableName);
            $result = count(array_intersect($interestingFields, array_keys($databaseData))) > 0;
        }
        return $result;
    }

    /**
     * @param string $tableName
     * @param  $recordId
     * @param  $reference
     * @return void
     */
    protected function markCachesDirty($tableName, $recordId, &$reference)
    {
        if ($tableName == 'pages') {
            $cache = t3lib_div::makeInstance('tx_realurl_cachemgmt', $GLOBALS ['BE_USER']->workspace, 0);
            $cache->markAsDirtyCompletePid($recordId);
        }
        if ($tableName == 'pages_language_overlay') {
            $pid = $reference->checkValue_currentRecord ['pid'];
            if ($pid) {
                $cache = t3lib_div::makeInstance('tx_realurl_cachemgmt', $GLOBALS ['BE_USER']->workspace, 0);
                $cache->markAsDirtyCompletePid($pid);
            }
        }
    }
}
