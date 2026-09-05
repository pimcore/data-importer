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

namespace Pimcore\Bundle\DataImporterBundle\Validation\Schema;

use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Bundles the service locators of every extension point, so the consumers that need all of
 * them do not each take eight constructor arguments.
 *
 * @internal
 */
final readonly class ConfigurationSchemaLocators
{
    public function __construct(
        private ServiceLocator $dataLoaderLocator,
        private ServiceLocator $interpreterLocator,
        private ServiceLocator $loadStrategyLocator,
        private ServiceLocator $locationStrategyLocator,
        private ServiceLocator $publishStrategyLocator,
        private ServiceLocator $operatorLocator,
        private ServiceLocator $dataTargetLocator,
        private ServiceLocator $cleanupStrategyLocator,
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
