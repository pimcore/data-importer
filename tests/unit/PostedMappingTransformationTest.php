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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\Mapping\CalculateTransformationResultTypeController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\Mapping\LoadTransformationResultController;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Schema\CalculateTransformationResultTypeParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\TransformationResultParameters;
use Pimcore\Bundle\DataImporterBundle\Tests\UnitTester;

class PostedMappingTransformationTest extends Unit
{
    protected UnitTester $tester;

    public function testThePreviewTransformsTheGivenRecord(): void
    {
        $response = $this->preview(
            [
                ['dataSourceIndex' => ['sku'], 'transformationPipeline' => [['type' => 'trim', 'settings' => ['mode' => 'both']]]],
                ['dataSourceIndex' => ['name', 'color'], 'transformationPipeline' => [['type' => 'combine', 'settings' => ['glue' => ' / ']]]],
                ['dataSourceIndex' => ['missing']],
            ],
            ['sku' => '  A-100 ', 'name' => 'Chair', 'color' => 'red']
        );

        $this->assertSame(['A-100', 'Chair / red', '-- EMPTY --'], $response['transformationResultPreviews']);
    }

    public function testTheTypeFollowsThePipeline(): void
    {
        $this->assertSame('default', $this->type(['dataSourceIndex' => ['sku']]));
        $this->assertSame('array', $this->type(['dataSourceIndex' => ['a', 'b']]));
        $this->assertSame('numeric', $this->type([
            'dataSourceIndex' => ['price'],
            'transformationPipeline' => [['type' => 'numeric']],
        ]));
    }

    public function testAnUnknownOperatorIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->type([
            'dataSourceIndex' => ['sku'],
            'transformationPipeline' => [['type' => 'no-such-operator']],
        ]);
    }

    private function preview(array $mappingConfig, array $dataRow): array
    {
        $controller = $this->tester->grabService(LoadTransformationResultController::class);
        $response = $controller->loadTransformationResult(new TransformationResultParameters($mappingConfig, $dataRow));

        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function type(array $mappingEntry): string
    {
        $controller = $this->tester->grabService(CalculateTransformationResultTypeController::class);
        $response = $controller->calculateTransformationResultType(new CalculateTransformationResultTypeParameters($mappingEntry));

        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR)['type'];
    }
}
