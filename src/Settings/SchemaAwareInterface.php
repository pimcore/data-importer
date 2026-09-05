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

namespace Pimcore\Bundle\DataImporterBundle\Settings;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * Interface for services that can provide their own schema metadata
 * using Symfony Config TreeBuilder for validation and JSON Schema for AI agents
 */
interface SchemaAwareInterface
{
    /**
     * Get human-readable description of what this service does
     *
     * @return string Brief description of the service functionality
     */
    public function getSchemaDescription(): string;

    /**
     * Get Symfony Config TreeBuilder for settings validation
     *
     * The TreeBuilder defines the structure, types, constraints, and default values
     * for this service's settings. It will be used for:
     * - Runtime validation of configuration
     * - Automatic application of default values
     * - Export to JSON Schema for AI agents and tools
     *
     * @return TreeBuilder|null TreeBuilder with configuration schema, or null if service has no settings
     *
     * Example:
     * <code>
     * $treeBuilder = new TreeBuilder('settings');
     * $treeBuilder->getRootNode()
     *     ->children()
     *         ->scalarNode('fieldName')
     *             ->isRequired()
     *             ->cannotBeEmpty()
     *             ->info('Name of the field to map')
     *         ->end()
     *         ->integerNode('timeout')
     *             ->min(1)
     *             ->max(300)
     *             ->defaultValue(30)
     *             ->info('Timeout in seconds')
     *         ->end()
     *     ->end();
     * return $treeBuilder;
     * </code>
     * @return TreeBuilder|null Configuration tree for settings validation, or null if service has no settings
     */
    public function getConfigTreeBuilder(): ?TreeBuilder;
}
