<?php
declare(strict_types=1);

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
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\AbstractAssetOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\ImportAsset;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\LoadAsset;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Model\Asset;

class AssetOperatorTest extends Unit
{
    public function testBothAssetOperatorsReturnAssets(): void
    {
        foreach ($this->operators() as $operator) {
            $this->assertSame(
                TransformationDataTypeService::ASSET,
                $operator->evaluateReturnType(TransformationDataTypeService::DEFAULT_TYPE)
            );
            $this->assertSame(
                TransformationDataTypeService::ASSET_ARRAY,
                $operator->evaluateReturnType(TransformationDataTypeService::DEFAULT_ARRAY)
            );
        }
    }

    public function testUnsupportedInputTypeIsRefused(): void
    {
        foreach ($this->operators() as $operator) {
            try {
                $operator->evaluateReturnType(TransformationDataTypeService::DATA_OBJECT, 2);
                $this->fail($operator::class . ' accepted a data object');
            } catch (InvalidConfigurationException $exception) {
                $this->assertStringContainsString('at transformation position 2', $exception->getMessage());
            }
        }
    }

    public function testPreviewNamesTheAsset(): void
    {
        $asset = new Asset();
        $asset->setPath('/images/');
        $asset->setFilename('a.jpg');

        foreach ($this->operators() as $operator) {
            $this->assertSame('Asset: /images/a.jpg', $operator->generateResultPreview($asset));
            $this->assertSame(['Asset: /images/a.jpg', 'text'], $operator->generateResultPreview([$asset, 'text']));
        }
    }

    /**
     * @return list<AbstractAssetOperator>
     */
    private function operators(): array
    {
        $applicationLogger = $this->createMock(ApplicationLogger::class);

        return [new LoadAsset($applicationLogger), new ImportAsset($applicationLogger)];
    }
}
