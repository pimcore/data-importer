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

namespace Pimcore\Bundle\DataImporterBundle\Validation;

use Pimcore\Bundle\DataImporterBundle\Cleanup\CleanupStrategyFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\DataLoaderFactory;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Resolver\ResolverFactory;

/**
 * Bundles the factories the validation service instantiates through to check that a
 * configuration's services can actually be built, so it does not take them one by one.
 *
 * @internal
 */
final readonly class ConfigurationFactories
{
    public function __construct(
        private DataLoaderFactory $dataLoaderFactory,
        private InterpreterFactory $interpreterFactory,
        private ResolverFactory $resolverFactory,
        private MappingConfigurationFactory $mappingConfigurationFactory,
        private CleanupStrategyFactory $cleanupStrategyFactory,
    ) {
    }

    public function dataLoader(): DataLoaderFactory
    {
        return $this->dataLoaderFactory;
    }

    public function interpreter(): InterpreterFactory
    {
        return $this->interpreterFactory;
    }

    public function resolver(): ResolverFactory
    {
        return $this->resolverFactory;
    }

    public function mappingConfiguration(): MappingConfigurationFactory
    {
        return $this->mappingConfigurationFactory;
    }

    public function cleanupStrategy(): CleanupStrategyFactory
    {
        return $this->cleanupStrategyFactory;
    }
}
