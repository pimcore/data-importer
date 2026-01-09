<?php

declare(strict_types=1);

namespace Pimcore\Bundle\DataImporterBundle\Validation\Schema;

use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Small value object bundling all service locators needed by ConfigurationSchemaService.
 */
class ConfigurationSchemaLocators
{
    public function __construct(
        private readonly ServiceLocator $dataLoaderLocator,
        private readonly ServiceLocator $interpreterLocator,
        private readonly ServiceLocator $loadStrategyLocator,
        private readonly ServiceLocator $locationStrategyLocator,
        private readonly ServiceLocator $publishStrategyLocator,
        private readonly ServiceLocator $operatorLocator,
        private readonly ServiceLocator $dataTargetLocator,
        private readonly ServiceLocator $cleanupStrategyLocator,
    ) {
    }

    public function dataLoader(): ServiceLocator
    {
        return $this->dataLoaderLocator;
    }

    public function interpreter(): ServiceLocator
    {
        return $this->interpreterLocator;
    }

    public function loadStrategy(): ServiceLocator
    {
        return $this->loadStrategyLocator;
    }

    public function locationStrategy(): ServiceLocator
    {
        return $this->locationStrategyLocator;
    }

    public function publishStrategy(): ServiceLocator
    {
        return $this->publishStrategyLocator;
    }

    public function operator(): ServiceLocator
    {
        return $this->operatorLocator;
    }

    public function dataTarget(): ServiceLocator
    {
        return $this->dataTargetLocator;
    }

    public function cleanupStrategy(): ServiceLocator
    {
        return $this->cleanupStrategyLocator;
    }
}
