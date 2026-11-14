<?php declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Tests;

use Codeception\Test\Unit;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\ImportAsset;
use Pimcore\Tests\Support\Util\TestHelper;

class ImportAssetTest extends Unit
{
    /**
     * @var \Pimcore\Bundle\DataImporterBundle\Tests\UnitTester
     */
    protected $tester;

    /**
     * @var string
     */
    private $localFile;

    protected function _before()
    {
        $this->localFile = tempnam(sys_get_temp_dir(), 'import-asset-test-');
        file_put_contents($this->localFile, 'content of a file on the server');
    }

    protected function _after()
    {
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
        $service->setSettings(['parentFolder' => '/import-asset-test']);

        $this->assertNull($service->process($this->localFile, true));
    }

    public function testProcessRejectsFileScheme()
    {
        $service = $this->createService();
        $service->setSettings(['parentFolder' => '/import-asset-test']);

        $this->assertNull($service->process('file://' . $this->localFile, true));
    }

    public function testProcessRejectsOtherStreamWrapperSchemes()
    {
        $service = $this->createService();
        $service->setSettings(['parentFolder' => '/import-asset-test']);

        $data = $service->process([
            'php://filter/resource=' . $this->localFile,
            'FILE://' . $this->localFile,
            'http:' . $this->localFile,
        ], true);

        $this->assertEquals([], $data);
    }
}
