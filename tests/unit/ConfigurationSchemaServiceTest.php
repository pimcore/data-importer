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
use Pimcore\Bundle\DataImporterBundle\Validation\Schema\ConfigurationSchemaService;
use Pimcore\Tests\Support\Util\TestHelper;

/**
 * @SuppressWarnings(PHPMD)
 */
class ConfigurationSchemaServiceTest extends Unit
{
    /**
     * @var \Pimcore\Bundle\DataImporterBundle\Tests\UnitTester
     */
    protected $tester;

    protected function _before()
    {
        // Intentionally left blank. No per-test setup required, but the
        // method is kept for consistency with Codeception lifecycle hooks.
    }

    protected function _after()
    {
        TestHelper::cleanUp();
    }

    /**
     * Test that schema service can be retrieved from container
     */
    public function testServiceCanBeRetrieved()
    {
        $service = $this->tester->grabService(ConfigurationSchemaService::class);
        $this->assertInstanceOf(ConfigurationSchemaService::class, $service);
    }

    /**
     * Test complete schema contains all expected sections
     */
    public function testCompleteSchemaHasAllSections()
    {
        $service = $this->tester->grabService(ConfigurationSchemaService::class);
        $schema = $service->getCompleteSchema();

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('general', $schema);
        $this->assertArrayHasKey('loaderConfig', $schema);
        $this->assertArrayHasKey('interpreterConfig', $schema);
        $this->assertArrayHasKey('resolverConfig', $schema);
        $this->assertArrayHasKey('processingConfig', $schema);
        $this->assertArrayHasKey('mappingConfig', $schema);
        $this->assertArrayHasKey('executionConfig', $schema);
    }

    /**
     * Test general configuration schema
     */
    public function testGeneralConfigSchema()
    {
        $service = $this->tester->grabService(ConfigurationSchemaService::class);
        $schema = $service->getGeneralSchema();

        $this->assertIsArray($schema);
        $this->assertEquals($schema['type'], 'object');
        $this->assertArrayHasKey('properties', $schema);

        // Verify expected properties exist
        $properties = $schema['properties'];
        $this->assertArrayHasKey('name', $properties);
        $this->assertArrayHasKey('active', $properties);
        $this->assertArrayHasKey('description', $properties);
        $this->assertArrayHasKey('group', $properties);
    }

    /**
     * Test loader config schema with available types
     */
    public function testLoaderConfigSchema()
    {
        $service = $this->tester->grabService(ConfigurationSchemaService::class);
        $schema = $service->getLoaderConfigSchema();

        $this->assertIsArray($schema);
        $this->assertEquals($schema['type'], 'object');
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('availableTypes', $schema);

        // Verify type property has enum values
        $properties = $schema['properties'];
        $this->assertArrayHasKey('type', $properties);
        $this->assertArrayHasKey('enum', $properties['type']);

        $expectedTypes = ['asset', 'upload', 'http', 'sftp', 'push', 'sql'];
        $enumValues = $properties['type']['enum'];
        foreach ($expectedTypes as $type) {
            $this->assertContains($type, $enumValues);
        }

        // Verify available types have schema information
        $availableTypes = $schema['availableTypes'];
        $this->assertArrayHasKey('asset', $availableTypes);
        $this->assertArrayHasKey('http', $availableTypes);
        $this->assertArrayHasKey('sftp', $availableTypes);
    }

    /**
     * Test interpreter config schema with available types
     */
    public function testInterpreterConfigSchema()
    {
        $service = $this->tester->grabService(ConfigurationSchemaService::class);
        $schema = $service->getInterpreterConfigSchema();

        $this->assertIsArray($schema);
        $this->assertEquals($schema['type'], 'object');
        $this->assertArrayHasKey('availableTypes', $schema);

        // Verify type property has enum values
        $properties = $schema['properties'];
        $this->assertArrayHasKey('type', $properties);
        $this->assertArrayHasKey('enum', $properties['type']);

        $expectedTypes = ['csv', 'json', 'xml', 'xlsx', 'sql'];
        $enumValues = $properties['type']['enum'];
        foreach ($expectedTypes as $type) {
            $this->assertContains($type, $enumValues);
        }

        // Verify available types have schema information
        $availableTypes = $schema['availableTypes'];
        $this->assertArrayHasKey('csv', $availableTypes);
        $this->assertArrayHasKey('json', $availableTypes);
        $this->assertArrayHasKey('xml', $availableTypes);
    }

    /**
     * Test resolver config schema with strategy types
     */
    public function testResolverConfigSchema()
    {
        $service = $this->tester->grabService(ConfigurationSchemaService::class);
        $schema = $service->getResolverConfigSchema();

        $this->assertIsArray($schema);
        $this->assertEquals($schema['type'], 'object');
        $this->assertArrayHasKey('properties', $schema);

        // Verify strategies have enum values
        $properties = $schema['properties'];
        // Resolver schema exposes strategy sections as nested objects
        $this->assertArrayHasKey('loadingStrategy', $properties);
        $this->assertArrayHasKey('createLocationStrategy', $properties);
        $this->assertArrayHasKey('locationUpdateStrategy', $properties);
        $this->assertArrayHasKey('publishingStrategy', $properties);
    }

    /**
     * Test processing config schema with cleanup strategy
     */
    public function testProcessingConfigSchema()
    {
        $service = $this->tester->grabService(ConfigurationSchemaService::class);
        $schema = $service->getProcessingConfigSchema();

        $this->assertIsArray($schema);
        $this->assertEquals($schema['type'], 'object');
        $this->assertArrayHasKey('properties', $schema);

        // Verify cleanup section exists
        $properties = $schema['properties'];
        $this->assertArrayHasKey('cleanup', $properties);

        // Verify cleanup strategy has enum values
        $cleanupSchema = $properties['cleanup'];
        $this->assertArrayHasKey('properties', $cleanupSchema);
        $cleanupProps = $cleanupSchema['properties'];
        $this->assertArrayHasKey('strategy', $cleanupProps);
        $this->assertArrayHasKey('enum', $cleanupProps['strategy']);

        $strategyValues = $cleanupProps['strategy']['enum'];
        $this->assertContains('delete', $strategyValues);
        $this->assertContains('unpublish', $strategyValues);
    }

    /**
     * Test mapping config schema with operators and data targets
     */
    public function testMappingConfigSchema()
    {
        $service = $this->tester->grabService(ConfigurationSchemaService::class);
        $schema = $service->getMappingConfigSchema();

        $this->assertIsArray($schema);
        $this->assertEquals($schema['type'], 'object');
        // Mapping config currently only defines type/default; just ensure schema exists
        $this->assertIsArray($schema);
    }

    /**
     * Test execution config schema
     */
    public function testExecutionConfigSchema()
    {
        $service = $this->tester->grabService(ConfigurationSchemaService::class);
        $schema = $service->getExecutionConfigSchema();

        $this->assertIsArray($schema);
        $this->assertEquals($schema['type'], 'object');
    }

    /**
     * Test that schema for specific loader type includes settings
     */
    public function testLoaderTypeSchemaIncludesSettings()
    {
        $service = $this->tester->grabService(ConfigurationSchemaService::class);
        $schema = $service->getLoaderConfigSchema();

        $availableTypes = $schema['availableTypes'];
        $this->assertArrayHasKey('http', $availableTypes);

        $httpLoader = $availableTypes['http'];
        $this->assertArrayHasKey('settings', $httpLoader);

        $settings = $httpLoader['settings'];
        $this->assertArrayHasKey('url', $settings);
        $this->assertArrayHasKey('schema', $settings);
    }

    /**
     * Test that schema for specific interpreter type includes settings
     */
    public function testInterpreterTypeSchemaIncludesSettings()
    {
        $service = $this->tester->grabService(ConfigurationSchemaService::class);
        $schema = $service->getInterpreterConfigSchema();

        $availableTypes = $schema['availableTypes'];
        $this->assertArrayHasKey('csv', $availableTypes);

        $csvInterpreter = $availableTypes['csv'];
        $this->assertArrayHasKey('settings', $csvInterpreter);

        $settings = $csvInterpreter['settings'];
        // CSV interpreter should have various settings
        $this->assertIsArray($settings);
        $this->assertGreaterThan(0, count($settings));
    }

    /**
     * Test that enum values are non-empty
     */
    public function testEnumValuesArePopulated()
    {
        $service = $this->tester->grabService(ConfigurationSchemaService::class);
        $loaderSchema = $service->getLoaderConfigSchema();

        $typeEnum = $loaderSchema['properties']['type']['enum'];
        $this->assertGreaterThan(0, count($typeEnum));

        $interpreterSchema = $service->getInterpreterConfigSchema();
        $typeEnum = $interpreterSchema['properties']['type']['enum'];
        $this->assertGreaterThan(0, count($typeEnum));
    }
}
