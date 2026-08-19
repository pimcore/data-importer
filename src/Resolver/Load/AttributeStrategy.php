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

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Load;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
final class AttributeStrategy extends AbstractLoad implements SchemaAwareInterface
{
    private string $attributeName;

    private string $attributeLanguage;

    private bool $includeUnpublished;

    protected TransformationDataTypeService $transformationDataTypeService;

    #[Required]
    public function setTransformationDataTypeService(
        TransformationDataTypeService $transformationDataTypeService
    ): void {
        $this->transformationDataTypeService =
            $transformationDataTypeService;
    }

    /**
     * @param array $settings
     *
     * @throws InvalidConfigurationException
     */
    public function setSettings(array $settings): void
    {
        parent::setSettings($settings);

        if (empty($settings['attributeName'])) {
            throw new InvalidConfigurationException('Empty attribute name.');
        }

        $this->attributeName = $settings['attributeName'];
        $this->attributeLanguage = $settings['language'] ?? '';
        $this->includeUnpublished = $settings['includeUnpublished'] ?? false;

        //to validate if an existing classId is set
        $this->getClassName();

        $this->dataObjectLoader->assertAttributeLoadable($this->dataObjectClassId, $this->attributeName);
    }

    /**
     * @param string $identifier
     *
     * @return ElementInterface|null
     *
     * @throws InvalidConfigurationException
     */
    public function loadElementByIdentifier($identifier): ?ElementInterface
    {
        return $this->dataObjectLoader->loadByAttribute($this->getClassName(),
            $this->attributeName,
            $identifier,
            $this->attributeLanguage,
            $this->includeUnpublished,
            1);
    }

    public function loadFullIdentifierList(): array
    {
        $tableName = 'object_' . $this->dataObjectClassId;
        if ($this->attributeLanguage) {
            $tableName = 'object_localized_' . $this->dataObjectClassId . '_' . $this->attributeLanguage;
        }

        $sql = sprintf('SELECT `%s` FROM %s', $this->attributeName, $tableName);

        return $this->db->fetchFirstColumn($sql);
    }

    public function getSchemaDescription(): string
    {
        return 'Loads data objects by matching a specific attribute value';
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = $this->getBaseConfigTreeBuilder();
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @phpstan-ignore-next-line */
        $rootNode
            ->children()
                ->scalarNode('attributeName')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Name of the attribute to search by')
                ->end()
                ->scalarNode('language')
                    ->info('Language code for localized attributes')
                ->end()
                ->booleanNode('includeUnpublished')
                    ->defaultValue(false)
                    ->info(
                        'Whether to include unpublished objects in the ' .
                        'search'
                    )
                ->end()
            ->end();

        return $treeBuilder;
    }
}
