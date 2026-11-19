<?php declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\Tests;

use Codeception\Test\Unit;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\ImportAsset;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\HttpStreamStub;
use Pimcore\Model\Asset;
use Pimcore\Tests\Support\Util\TestHelper;

class ImportAssetTest extends Unit
{
    private const PARENT_FOLDER = '/import-asset-test';

    /**
     * @var \Pimcore\Bundle\DataImporterBundle\Tests\UnitTester
     */
    protected $tester;

    /**
     * @var string
     */
    private $localFile;

    /**
     * @var string[]
     */
    private $stubbedSchemes = [];

    protected function _before()
    {
        $this->localFile = tempnam(sys_get_temp_dir(), 'import-asset-test-');
        file_put_contents($this->localFile, 'content of a file on the server');
    }

    protected function _after()
    {
        $this->restoreHttpWrappers();
        unlink($this->localFile);
        TestHelper::cleanUp();
    }

    /**
     * Built directly (not via the container) so the rejection's error log doesn't require the
     * ApplicationLoggerBundle's database table, which this test suite doesn't install.
     */
    private function createService(): ImportAsset
    {
        return new ImportAsset(new ApplicationLogger());
    }

    public function testProcessRejectsLocalFilesystemPath()
    {
        $service = $this->createService();
        $service->setSettings(['parentFolder' => self::PARENT_FOLDER]);

        $this->assertNull($service->process($this->localFile, true));
    }

    public function testProcessRejectsFileScheme()
    {
        $service = $this->createService();
        $service->setSettings(['parentFolder' => self::PARENT_FOLDER]);

        $this->assertNull($service->process('file://' . $this->localFile, true));
    }

    public function testProcessRejectsOtherStreamWrapperSchemes()
    {
        $service = $this->createService();
        $service->setSettings(['parentFolder' => self::PARENT_FOLDER]);

        $data = $service->process([
            'php://filter/resource=' . $this->localFile,
            'FILE://' . $this->localFile,
            'http:' . $this->localFile,
        ], true);

        $this->assertEquals([], $data);
    }

    public function testProcessDownloadsHttpAndHttpsUrls()
    {
        $urls = ['http://example.com/files/first.txt', 'https://example.com/files/second.txt'];

        // created up front, so nothing but the download itself runs while the wrappers are stubbed
        Asset\Service::createFolderByPath(self::PARENT_FOLDER);
        $service = $this->createService();
        $service->setSettings(['parentFolder' => self::PARENT_FOLDER]);

        $this->stubHttpWrappers();

        try {
            $assets = $service->process($urls, true);
        } finally {
            $this->restoreHttpWrappers();
        }

        static::assertSame($urls, HttpStreamStub::$requestedUrls);
        static::assertCount(2, $assets);
        static::assertSame(self::PARENT_FOLDER . '/first.txt', $assets[0]->getRealFullPath());
        static::assertSame(self::PARENT_FOLDER . '/second.txt', $assets[1]->getRealFullPath());
        static::assertSame(HttpStreamStub::BODY, $assets[0]->getData());
        static::assertSame(HttpStreamStub::BODY, $assets[1]->getData());
    }

    /**
     * Replaces PHP's http and https stream wrappers with HttpStreamStub, so the allowed download
     * path runs without a network.
     */
    private function stubHttpWrappers(): void
    {
        HttpStreamStub::$requestedUrls = [];

        foreach (['http', 'https'] as $scheme) {
            if (in_array($scheme, stream_get_wrappers(), true)) {
                stream_wrapper_unregister($scheme);
                $this->stubbedSchemes[] = $scheme;
            }

            stream_wrapper_register($scheme, HttpStreamStub::class);
        }
    }

    private function restoreHttpWrappers(): void
    {
        foreach ($this->stubbedSchemes as $scheme) {
            stream_wrapper_restore($scheme);
        }

        $this->stubbedSchemes = [];
    }
}
