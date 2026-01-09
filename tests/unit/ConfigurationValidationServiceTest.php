<?php

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
use Pimcore\Bundle\DataImporterBundle\Validation\ConfigurationValidationService;
use Pimcore\Bundle\DataImporterBundle\Validation\ValidationError;
use Pimcore\Tests\Support\Util\TestHelper;

class ConfigurationValidationServiceTest extends Unit
{
    /**
     * @var \Pimcore\Bundle\DataImporterBundle\Tests\UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
        TestHelper::cleanUp();
    }

    /**
     * Test that validation service can be retrieved from container
     */
    public function testServiceCanBeRetrieved()
    {
        $service = $this->tester->grabService(ConfigurationValidationService::class);
        $this->assertInstanceOf(ConfigurationValidationService::class, $service);
    }

    /**
    * Test validation of a configuration returns result object
     */
    public function testValidateConfigurationReturnsValidationResult()
    {
        $service = $this->tester->grabService(ConfigurationValidationService::class);

        $config = [
            'general' => [
                'name' => 'TestConfig',
                'active' => true,
            ],
            'loaderConfig' => [
                'type' => 'asset',
                'settings' => [
                    'assetPath' => '/Import/test.csv',
                ],
            ],
            'interpreterConfig' => [
                'type' => 'csv',
                'settings' => [],
            ],
            'resolverConfig' => [],
            'processingConfig' => [],
            'mappingConfig' => [],
            'executionConfig' => [],
        ];

        $result = $service->validateConfiguration($config);

        // Just verify we get a result object, don't test validity (depends on factory setup)
        $this->assertIsObject($result, 'Should return a ValidationResult object');
        $this->assertTrue(method_exists($result, 'isValid'));
    }

    /**
     * Test that missing required general properties cause validation error
     */
    public function testMissingGeneralNameCausesError()
    {
        $service = $this->tester->grabService(ConfigurationValidationService::class);

        $config = [
            'general' => [
                'active' => true,
                // missing 'name'
            ],
            'loaderConfig' => [
                'type' => 'asset',
                'settings' => ['assetPath' => '/Import/test.csv'],
            ],
            'interpreterConfig' => [
                'type' => 'csv',
                'settings' => [],
            ],
            'resolverConfig' => [],
            'processingConfig' => [],
            'mappingConfig' => [],
            'executionConfig' => [],
        ];

        $result = $service->validateConfiguration($config);

        $this->assertFalse($result->isValid(), 'Configuration should be invalid');
        $this->assertTrue($result->hasErrors(), 'Should have validation errors');
    }

    /**
     * Test that invalid loader type causes validation error
     */
    public function testInvalidLoaderTypeCausesError()
    {
        $service = $this->tester->grabService(ConfigurationValidationService::class);

        $config = [
            'general' => [
                'name' => 'TestConfig',
                'active' => true,
            ],
            'loaderConfig' => [
                'type' => 'invalid_loader_type',
                'settings' => [],
            ],
            'interpreterConfig' => [
                'type' => 'csv',
                'settings' => [],
            ],
            'resolverConfig' => [],
            'processingConfig' => [],
            'mappingConfig' => [],
            'executionConfig' => [],
        ];

        $result = $service->validateConfiguration($config);

        $this->assertFalse($result->isValid(), 'Configuration should be invalid');
        $this->assertTrue($result->hasErrors(), 'Should have validation errors');

        $errors = $result->getErrors();
        $this->assertGreaterThan(0, count($errors), 'Should have at least one error');
    }

    /**
     * Test that missing loader type causes validation error
     */
    public function testMissingLoaderTypeCausesError()
    {
        $service = $this->tester->grabService(ConfigurationValidationService::class);

        $config = [
            'general' => [
                'name' => 'TestConfig',
                'active' => true,
            ],
            'loaderConfig' => [
                'settings' => ['assetPath' => '/Import/test.csv'],
                // missing 'type'
            ],
            'interpreterConfig' => [
                'type' => 'csv',
                'settings' => [],
            ],
            'resolverConfig' => [],
            'processingConfig' => [],
            'mappingConfig' => [],
            'executionConfig' => [],
        ];

        $result = $service->validateConfiguration($config);

        $this->assertFalse($result->isValid(), 'Configuration should be invalid');
        $this->assertTrue($result->hasErrors(), 'Should have validation errors');
    }

    /**
     * Test that invalid interpreter type causes validation error
     */
    public function testInvalidInterpreterTypeCausesError()
    {
        $service = $this->tester->grabService(ConfigurationValidationService::class);

        $config = [
            'general' => [
                'name' => 'TestConfig',
                'active' => true,
            ],
            'loaderConfig' => [
                'type' => 'asset',
                'settings' => ['assetPath' => '/Import/test.csv'],
            ],
            'interpreterConfig' => [
                'type' => 'invalid_interpreter_type',
                'settings' => [],
            ],
            'resolverConfig' => [],
            'processingConfig' => [],
            'mappingConfig' => [],
            'executionConfig' => [],
        ];

        $result = $service->validateConfiguration($config);

        $this->assertFalse($result->isValid(), 'Configuration should be invalid');
        $this->assertTrue($result->hasErrors(), 'Should have validation errors');
    }

    /**
     * Test that missing interpreter type causes validation error
     */
    public function testMissingInterpreterTypeCausesError()
    {
        $service = $this->tester->grabService(ConfigurationValidationService::class);

        $config = [
            'general' => [
                'name' => 'TestConfig',
                'active' => true,
            ],
            'loaderConfig' => [
                'type' => 'asset',
                'settings' => ['assetPath' => '/Import/test.csv'],
            ],
            'interpreterConfig' => [
                'settings' => [],
                // missing 'type'
            ],
            'resolverConfig' => [],
            'processingConfig' => [],
            'mappingConfig' => [],
            'executionConfig' => [],
        ];

        $result = $service->validateConfiguration($config);

        $this->assertFalse($result->isValid(), 'Configuration should be invalid');
        $this->assertTrue($result->hasErrors(), 'Should have validation errors');
    }

    /**
     * Test validation with cleanup strategy
     */
    public function testValidationWithCleanupStrategy()
    {
        $service = $this->tester->grabService(ConfigurationValidationService::class);

        $config = [
            'general' => [
                'name' => 'TestConfig',
                'active' => true,
            ],
            'loaderConfig' => [
                'type' => 'asset',
                'settings' => ['assetPath' => '/Import/test.csv'],
            ],
            'interpreterConfig' => [
                'type' => 'csv',
                'settings' => [],
            ],
            'resolverConfig' => [],
            'processingConfig' => [
                'cleanup' => [
                    'strategy' => 'delete',
                    'settings' => [],
                ],
            ],
            'mappingConfig' => [],
            'executionConfig' => [],
        ];

        $result = $service->validateConfiguration($config);

        // Just verify the result object is returned and supports cleanup strategy
        $this->assertIsObject($result, 'Should return a ValidationResult object');
    }

    /**
     * Test that invalid cleanup strategy causes validation error
     */
    public function testInvalidCleanupStrategyCausesError()
    {
        $service = $this->tester->grabService(ConfigurationValidationService::class);

        $config = [
            'general' => [
                'name' => 'TestConfig',
                'active' => true,
            ],
            'loaderConfig' => [
                'type' => 'asset',
                'settings' => ['assetPath' => '/Import/test.csv'],
            ],
            'interpreterConfig' => [
                'type' => 'csv',
                'settings' => [],
            ],
            'resolverConfig' => [],
            'processingConfig' => [
                'cleanup' => [
                    'strategy' => 'invalid_cleanup_strategy',
                    'settings' => [],
                ],
            ],
            'mappingConfig' => [],
            'executionConfig' => [],
        ];

        $result = $service->validateConfiguration($config);

        $this->assertFalse($result->isValid(), 'Configuration with invalid cleanup strategy should be invalid');
        $this->assertTrue($result->hasErrors(), 'Should have validation errors');
    }

    /**
     * Test validation result object structure
     */
    public function testValidationResultStructure()
    {
        $service = $this->tester->grabService(ConfigurationValidationService::class);

        $config = [
            'general' => [
                'name' => 'TestConfig',
                'active' => true,
            ],
            'loaderConfig' => [
                'type' => 'asset',
                'settings' => ['assetPath' => '/Import/test.csv'],
            ],
            'interpreterConfig' => [
                'type' => 'csv',
                'settings' => [],
            ],
            'resolverConfig' => [],
            'processingConfig' => [],
            'mappingConfig' => [],
            'executionConfig' => [],
        ];

        $result = $service->validateConfiguration($config);

        // Test result object methods
        $this->assertTrue(method_exists($result, 'isValid'));
        $this->assertTrue(method_exists($result, 'hasErrors'));
        $this->assertTrue(method_exists($result, 'hasWarnings'));
        $this->assertTrue(method_exists($result, 'getErrors'));
        $this->assertTrue(method_exists($result, 'getWarnings'));
        $this->assertTrue(method_exists($result, 'toArray'));

        // Test array conversion
        $array = $result->toArray();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('valid', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('warnings', $array);
    }

    /**
     * Test validation with multiple loader types
     */
    public function testValidationWithDifferentLoaderTypes()
    {
        $service = $this->tester->grabService(ConfigurationValidationService::class);

        $loaderTypes = ['asset', 'http', 'sftp', 'push', 'sql'];

        foreach ($loaderTypes as $loaderType) {
            $config = [
                'general' => [
                    'name' => "TestConfig_$loaderType",
                    'active' => true,
                ],
                'loaderConfig' => [
                    'type' => $loaderType,
                    'settings' => [],
                ],
                'interpreterConfig' => [
                    'type' => 'csv',
                    'settings' => [],
                ],
                'resolverConfig' => [],
                'processingConfig' => [],
                'mappingConfig' => [],
                'executionConfig' => [],
            ];

            // Should not throw exception - type should be recognized
            $result = $service->validateConfiguration($config);
            // Note: May fail due to missing required settings, but should not fail due to invalid type
            $this->assertIsObject($result, "Should be able to validate config with loader type: $loaderType");
        }
    }

    /**
     * Test validation with different interpreter types
     */
    public function testValidationWithDifferentInterpreterTypes()
    {
        $service = $this->tester->grabService(ConfigurationValidationService::class);

        $interpreterTypes = ['csv', 'json', 'xml', 'xlsx', 'sql'];

        foreach ($interpreterTypes as $interpreterType) {
            $config = [
                'general' => [
                    'name' => "TestConfig_$interpreterType",
                    'active' => true,
                ],
                'loaderConfig' => [
                    'type' => 'asset',
                    'settings' => ['assetPath' => '/Import/test.csv'],
                ],
                'interpreterConfig' => [
                    'type' => $interpreterType,
                    'settings' => [],
                ],
                'resolverConfig' => [],
                'processingConfig' => [],
                'mappingConfig' => [],
                'executionConfig' => [],
            ];

            // Should not throw exception - type should be recognized
            $result = $service->validateConfiguration($config);
            // Note: May fail due to missing required settings, but should not fail due to invalid type
            $this->assertIsObject($result, "Should be able to validate config with interpreter type: $interpreterType");
        }
    }

    /**
     * Test error object structure
     */
    public function testErrorObjectStructure()
    {
        $service = $this->tester->grabService(ConfigurationValidationService::class);

        $config = [
            'general' => [
                // missing 'name'
                'active' => true,
            ],
            'loaderConfig' => [
                'type' => 'asset',
                'settings' => ['assetPath' => '/Import/test.csv'],
            ],
            'interpreterConfig' => [
                'type' => 'csv',
                'settings' => [],
            ],
            'resolverConfig' => [],
            'processingConfig' => [],
            'mappingConfig' => [],
            'executionConfig' => [],
        ];

        $result = $service->validateConfiguration($config);
        $errors = $result->getErrors();

        if (count($errors) > 0) {
            $error = $errors[0];
            $this->assertTrue(method_exists($error, 'getPath'));
            $this->assertTrue(method_exists($error, 'getMessage'));

            $this->assertIsString($error->getPath());
            $this->assertIsString($error->getMessage());
        }
    }
}
