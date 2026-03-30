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

namespace Pimcore\Bundle\DataImporterBundle\Resolver;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Resolver\Factory\FactoryInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Load\LoadStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Location\LocationStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Publish\PublishStrategyInterface;

/**
 * @internal
 */
final class ResolverFactory
{
    /**
     * ResolverFactory constructor.
     *
     * @param Resolver $resolverBlueprint
     * @param LoadStrategyInterface[] $loadingStrategyBlueprints
     * @param LocationStrategyInterface[] $locationStrategyBlueprints
     * @param PublishStrategyInterface[] $publishingStrategyBlueprints
     * @param FactoryInterface[] $factoryBlueprints
     */
    public function __construct(
        private readonly Resolver $resolverBlueprint,
        private readonly array $loadingStrategyBlueprints,
        private readonly array $locationStrategyBlueprints,
        private readonly array $publishingStrategyBlueprints,
        private readonly array $factoryBlueprints,
    ) {
    }

    /**
     * @param array $config
     * @param string $classId
     *
     * @return LoadStrategyInterface
     *
     * @throws InvalidConfigurationException
     */
    private function buildLoadingStrategy(array $config, $classId): LoadStrategyInterface
    {
        if (empty($config['type']) || !array_key_exists($config['type'], $this->loadingStrategyBlueprints)) {
            throw new InvalidConfigurationException('Unknown loading strategy type `' . ($config['type'] ?? '') . '`');
        }

        $loadingStrategy = clone $this->loadingStrategyBlueprints[$config['type']];
        $loadingStrategy->setSettings($config['settings'] ?? []);
        $loadingStrategy->setDataObjectClassId($classId);

        return $loadingStrategy;
    }

    private function buildLocationStrategy(array $config): LocationStrategyInterface
    {
        if (empty($config['type']) || !array_key_exists($config['type'], $this->locationStrategyBlueprints)) {
            throw new InvalidConfigurationException('Unknown location strategy type `' . ($config['type'] ?? '') . '`');
        }

        $locationStrategy = clone $this->locationStrategyBlueprints[$config['type']];
        $locationStrategy->setSettings($config['settings'] ?? []);

        return $locationStrategy;
    }

    private function buildPublishingStrategy(array $config): PublishStrategyInterface
    {
        if (empty($config['type']) || !array_key_exists($config['type'], $this->publishingStrategyBlueprints)) {
            throw new InvalidConfigurationException('Unknown publishing strategy type `' . ($config['type'] ?? '') . '`');
        }

        $publishStrategy = clone $this->publishingStrategyBlueprints[$config['type']];
        $publishStrategy->setSettings($config['settings'] ?? []);

        return $publishStrategy;
    }

    private function buildElementFactory(string $type, ?string $subType = null): FactoryInterface
    {
        if (empty($type) || !array_key_exists($type, $this->factoryBlueprints)) {
            throw new InvalidConfigurationException('Unknown publishing strategy type `' . $type . '`');
        }

        $factory = clone $this->factoryBlueprints[$type];
        $factory->setSubType($subType);

        return $factory;
    }

    public function loadResolver(array $configuration): Resolver
    {
        $resolver = clone $this->resolverBlueprint;

        $resolver->setDataObjectClassId($configuration['dataObjectClassId'] ?? null);
        $resolver->setLoadingStrategy($this->buildLoadingStrategy($configuration['loadingStrategy'] ?? [], $resolver->getDataObjectClassId()));
        $resolver->setCreateLocationStrategy($this->buildLocationStrategy($configuration['createLocationStrategy'] ?? []));
        $resolver->setLocationUpdateStrategy($this->buildLocationStrategy($configuration['locationUpdateStrategy'] ?? []));
        $resolver->setPublishingStrategy($this->buildPublishingStrategy($configuration['publishingStrategy']));
        $resolver->setElementFactory($this->buildElementFactory($configuration['elementType'] ?? '', $resolver->getDataObjectClassId()));

        return $resolver;
    }
}
