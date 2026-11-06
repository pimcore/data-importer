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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfiguration;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\OperatorInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\WritesElementsInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\TransformationService;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use ReflectionClass;

/**
 * The posted preview refuses an operator by its marker, not by its type name: a third-party operator registered under
 * any name that writes elements never runs.
 */
class PostedPreviewWritingOperatorTest extends Unit
{
    public function testAWritingOperatorUnderAnotherNameIsRefusedBeforeItRuns(): void
    {
        $operator = new class() implements OperatorInterface, WritesElementsInterface {
            // the factory runs a clone of its blueprint
            public static bool $processed = false;

            public function setSettings(array $settings): void
            {
            }

            public function process($inputData, bool $dryRun = false)
            {
                self::$processed = true;

                return $inputData;
            }

            public function evaluateReturnType(string $inputType, ?int $index = null): string
            {
                return $inputType;
            }

            public function generateResultPreview($inputData)
            {
                return $inputData;
            }

            public function setConfigName(string $configName)
            {
            }
        };

        $service = $this->serviceWithOperators(['quietWriter' => $operator]);
        $assertNoSideEffects = (new ReflectionClass(TransformationService::class))->getMethod('assertNoSideEffects');

        try {
            $assertNoSideEffects->invoke($service, [[
                'dataSourceIndex' => ['image'],
                'transformationPipeline' => [['type' => 'quietWriter']],
            ]]);
            $this->fail('the posted preview is refused');
        } catch (InvalidArgumentException $refusal) {
            $this->assertStringContainsString('"quietWriter"', $refusal->getMessage());
        }

        $this->assertFalse($operator::$processed);
    }

    private function serviceWithOperators(array $operators): TransformationService
    {
        $class = new ReflectionClass(TransformationService::class);
        $service = $class->newInstanceWithoutConstructor();
        $class->getProperty('mappingConfigurationFactory')->setValue(
            $service,
            new MappingConfigurationFactory(new MappingConfiguration(), $operators, [])
        );

        return $service;
    }
}
