<?php
namespace AOE\Realurl\Tests\Unit;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 AOE GmbH <dev@aoe.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use AOE\Realurl\Exception\RootlineException;
use AOE\Realurl\Realurl;
use Nimut\TestingFramework\TestCase\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class tx_realurl_testcase
 */
class RealurlTest extends UnitTestCase
{
    /**
     * @test
     */
    public function decodeCacheReadsFromCache()
    {
        $pageId = 1234;
        $rootPageId = 9876;
        $speakingUrlPath = 'test';
        $cachedContent = [
            'id' => $pageId,
            'rootpage_id' => $rootPageId,
            'GET_VARS' => []
        ];
        $extConf = [
            'init' => [
                'enableUrlDecodeCache' => 1
            ],
            'pagePath' => [
                'rootpage_id' => $rootPageId
            ]
        ];

        $cacheFrontendMock = $this->getMockBuilder(VariableFrontend::class)
            ->disableOriginalConstructor()
            ->setMethods(['has', 'get'])
            ->getMock();
        $cacheFrontendMock->expects($this->once())
            ->method('has')
            ->with(sha1($speakingUrlPath . $rootPageId))
            ->willReturn(true);
        $cacheFrontendMock->expects($this->once())
            ->method('get')
            ->with(sha1($speakingUrlPath . $rootPageId))
            ->willReturn($cachedContent);

        $cacheManagerMock = $this->getMockBuilder(CacheManager::class)
            ->setMethods(['getCache'])
            ->getMock();
        $cacheManagerMock->expects($this->exactly(2))
            ->method('getCache')
            ->with(Realurl::CACHE_DECODE)
            ->willReturn($cacheFrontendMock);

        $tsfeMock = $this->createMock(TypoScriptFrontendController::class);

        /** @var Realurl|MockObject $subject */
        $subject = $this->getMockBuilder(Realurl::class)
            ->setMethods(['getCacheManager', 'getTypoScriptFrontendController'])
            ->getMock();
        $subject->expects($this->exactly(2))
            ->method('getCacheManager')
            ->willReturn($cacheManagerMock);
        $subject->expects(self::any())
            ->method('getTypoScriptFrontendController')
            ->willReturn($tsfeMock);
        $subject->extConf = $extConf;

        $this->assertSame(
            $cachedContent,
            $this->callInaccessibleMethod($subject, 'decodeSpURL_decodeCache', $speakingUrlPath)
        );
    }

    /**
     * @test
     */
    public function decodeCacheWritesToCache()
    {
        $pageId = 1234;
        $rootPageId = 9876;
        $speakingUrlPath = 'test';
        $cachedContent = [
            'id' => $pageId,
            'rootpage_id' => $rootPageId,
            'GET_VARS' => []
        ];
        $extConf = [
            'init' => [
                'enableUrlDecodeCache' => 1
            ],
            'pagePath' => [
                'rootpage_id' => $rootPageId
            ]
        ];

        $cacheFrontendMock = $this->getMockBuilder(VariableFrontend::class)
            ->disableOriginalConstructor()
            ->setMethods(['set'])
            ->getMock();
        $cacheFrontendMock->expects($this->once())
            ->method('set')
            ->with(sha1($speakingUrlPath . $rootPageId), $cachedContent, ['pageId_' . $pageId], 86400);

        $cacheManagerMock = $this->getMockBuilder(CacheManager::class)
            ->setMethods(['getCache'])
            ->getMock();
        $cacheManagerMock->expects($this->once())
            ->method('getCache')
            ->with(Realurl::CACHE_DECODE)
            ->willReturn($cacheFrontendMock);

        $tsfeMock = $this->createMock(TypoScriptFrontendController::class);

        /** @var Realurl|MockObject $subject */
        $subject = $this->getMockBuilder(Realurl::class)
            ->setMethods(['canCachePageURL', 'getCacheManager', 'getTypoScriptFrontendController'])
            ->getMock();
        $subject->expects($this->once())
            ->method('canCachePageURL')
            ->with($pageId)
            ->willReturn(true);
        $subject->expects($this->once())
            ->method('getCacheManager')
            ->willReturn($cacheManagerMock);
        $subject->expects(self::any())
            ->method('getTypoScriptFrontendController')
            ->willReturn($tsfeMock);
        $subject->extConf = $extConf;

        $this->callInaccessibleMethod($subject, 'decodeSpURL_decodeCache', $speakingUrlPath, $cachedContent);
    }

    /**
     * @test
     */
    public function encodeCacheUsesCacheArrayFirst()
    {
        $encodedUrl = 'test/';
        $urlData = '| id=12345';
        $internalExtras = [];
        $hash = sha1($urlData . '///' . serialize($internalExtras));
        $extConf = [
            'init' => [
                'enableUrlEncodeCache' => 1
            ]
        ];

        /** @var MockObject|TypoScriptFrontendController $tsfeMock */
        $tsfeMock = $this->createMock(TypoScriptFrontendController::class);
        $tsfeMock->applicationData['tx_realurl']['_CACHE'][$hash] = $encodedUrl;

        /** @var Realurl|MockObject $subject */
        $subject = $this->getMockBuilder(Realurl::class)
            ->setMethods(['getCacheManager', 'getTypoScriptFrontendController'])
            ->getMock();
        $subject->expects($this->never())
            ->method('getCacheManager');
        $subject->expects(self::any())
            ->method('getTypoScriptFrontendController')
            ->willReturn($tsfeMock);
        $subject->extConf = $extConf;

        $this->assertSame(
            $encodedUrl,
            $this->callInaccessibleMethod($subject, 'encodeSpURL_encodeCache', $urlData, $internalExtras)
        );
    }

    /**
     * @test
     */
    public function encodeCacheReadsFromCache()
    {
        $encodedUrl = 'test/';
        $urlData = '| id=12345';
        $internalExtras = [];
        $hash = sha1($urlData . '///' . serialize($internalExtras));
        $extConf = [
            'init' => [
                'enableUrlEncodeCache' => 1
            ]
        ];

        $cacheFrontendMock = $this->getMockBuilder(VariableFrontend::class)
            ->disableOriginalConstructor()
            ->setMethods(['get'])
            ->getMock();
        $cacheFrontendMock->expects($this->once())
            ->method('get')
            ->with($hash)
            ->willReturn($encodedUrl);

        $cacheManagerMock = $this->getMockBuilder(CacheManager::class)
            ->setMethods(['getCache'])
            ->getMock();
        $cacheManagerMock->expects($this->once())
            ->method('getCache')
            ->with(Realurl::CACHE_ENCODE)
            ->willReturn($cacheFrontendMock);

        /** @var MockObject|TypoScriptFrontendController $tsfeMock */
        $tsfeMock = $this->createMock(TypoScriptFrontendController::class);
        $tsfeMock->applicationData = [
            'tx_realurl' => [
                '_CACHE' => []
            ]
        ];

        /** @var Realurl|MockObject $subject */
        $subject = $this->getMockBuilder(Realurl::class)
            ->setMethods(['getCacheManager', 'getTypoScriptFrontendController'])
            ->getMock();
        $subject->expects($this->once())
            ->method('getCacheManager')
            ->willReturn($cacheManagerMock);
        $subject->expects(self::any())
            ->method('getTypoScriptFrontendController')
            ->willReturn($tsfeMock);
        $subject->extConf = $extConf;

        $this->assertSame(
            $encodedUrl,
            $this->callInaccessibleMethod($subject, 'encodeSpURL_encodeCache', $urlData, $internalExtras)
        );
    }

    /**
     * @test
     */
    public function encodeCacheWritesToCache()
    {
        $encodePageId = 12345;
        $encodedUrl = 'test/';
        $urlData = '| id=12345';
        $internalExtras = [];
        $hash = sha1($urlData . '///' . serialize($internalExtras));
        $extConf = [
            'init' => [
                'enableUrlEncodeCache' => 1
            ]
        ];

        $cacheFrontendMock = $this->getMockBuilder(VariableFrontend::class)
            ->disableOriginalConstructor()
            ->setMethods(['set'])
            ->getMock();
        $cacheFrontendMock->expects($this->once())
            ->method('set')
            ->with($hash, $encodedUrl, ['pageId_' . $encodePageId], 86400);

        $cacheManagerMock = $this->getMockBuilder(\TYPO3\CMS\Core\Cache\CacheManager::class)
            ->setMethods(['getCache'])
            ->getMock();
        $cacheManagerMock->expects($this->once())
            ->method('getCache')
            ->with(Realurl::CACHE_ENCODE)
            ->willReturn($cacheFrontendMock);

        /** @var MockObject|TypoScriptFrontendController $tsfeMock */
        $tsfeMock = $this->createMock(TypoScriptFrontendController::class);
        $tsfeMock->applicationData = [
            'tx_realurl' => [
                '_CACHE' => []
            ]
        ];

        /** @var Realurl|MockObject $subject */
        $subject = $this->getMockBuilder(Realurl::class)
            ->setMethods(['canCachePageURL', 'getCacheManager', 'getTypoScriptFrontendController'])
            ->getMock();
        $subject->expects($this->once())
            ->method('canCachePageURL')
            ->with($encodePageId)
            ->willReturn(true);
        $subject->expects($this->once())
            ->method('getCacheManager')
            ->willReturn($cacheManagerMock);
        $subject->expects(self::any())
            ->method('getTypoScriptFrontendController')
            ->willReturn($tsfeMock);
        $subject->encodePageId = $encodePageId;
        $subject->extConf = $extConf;

        $this->callInaccessibleMethod($subject, 'encodeSpURL_encodeCache', $urlData, $internalExtras, $encodedUrl);
    }

    ////////////////////////////////////////////////////////////////////////
    //  Tests concerning tx_realurl::encodeSpURL()
    ////////////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function shouldExitOnRootlineException()
    {
        /** @var MockObject|TypoScriptFrontendController $tsfeMock */
        $tsfeMock = $this->createMock(TypoScriptFrontendController::class);
        $tsfeMock->absRefPrefix = '/';
        $tsfeMock->config = [
            'config' => [
                'tx_realurl_enable' => true
            ]
        ];

        $parameters = [
            'LD' => [
                'totalURL' => '/index.php'
            ]
        ];

        /** @var Realurl|MockObject $subject */
        $subject = $this->getMockBuilder(Realurl::class)
            ->setMethods(['encodeSpURL_doEncode', 'errorLog', 'getTypoScriptFrontendController'])
            ->getMock();
        $subject
            ->expects(self::once())
            ->method('encodeSpURL_doEncode')
            ->willThrowException(new RootlineException('Exception Test'));
        $subject
            ->expects(self::once())
            ->method('errorLog')
            ->with('Exception Test');
        $subject->expects(self::any())
            ->method('getTypoScriptFrontendController')
            ->willReturn($tsfeMock);

        self::assertEmpty($subject->encodeSpURL($parameters));
    }
}
