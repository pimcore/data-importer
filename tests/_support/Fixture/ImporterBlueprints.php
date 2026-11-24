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

namespace Pimcore\Bundle\DataImporterBundle\Tests\Fixture;

use Pimcore\Bundle\DataImporterBundle\Cleanup\CleanupStrategyFactory;
use Pimcore\Bundle\DataImporterBundle\Cleanup\DeleteStrategy;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\CsvFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\JsonFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\XmlFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\Classificationstore;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\Direct;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfiguration;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\Trim;
use ReflectionClass;

/**
 * The importer's factories with some of its components, as the container holds them.
 */
final class ImporterBlueprints
{
    public static function interpreters(): InterpreterFactory
    {
        return new InterpreterFactory([
            'csv' => self::blueprint(CsvFileInterpreter::class),
            'json' => self::blueprint(JsonFileInterpreter::class),
            'xml' => self::blueprint(XmlFileInterpreter::class),
        ]);
    }

    public static function mappings(): MappingConfigurationFactory
    {
        return new MappingConfigurationFactory(
            new MappingConfiguration(),
            ['trim' => self::blueprint(Trim::class)],
            ['direct' => new Direct(), 'classificationstore' => self::blueprint(Classificationstore::class)],
        );
    }

    public static function cleanupStrategies(): CleanupStrategyFactory
    {
        return new CleanupStrategyFactory(['delete' => self::blueprint(DeleteStrategy::class)]);
    }

    /**
     * A component without the services only an import uses.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public static function blueprint(string $class): object
    {
        return (new ReflectionClass($class))->newInstanceWithoutConstructor();
    }
}
