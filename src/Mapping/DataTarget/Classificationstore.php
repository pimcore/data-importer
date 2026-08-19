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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * @internal
 */
final class Classificationstore implements DataTargetInterface, SchemaAwareInterface
{
    private string $fieldName;

    private string $language;

    private int $keyId;

    private int $groupId;

    public function setSettings(array $settings): void
    {
        if (empty($settings['fieldName'])) {
            throw new InvalidConfigurationException('Empty field name.');
        }

        $keyParts = explode('-', ($settings['keyId'] ?? []));
        if (empty($keyParts[0]) || empty($keyParts[1])) {
            throw new InvalidConfigurationException('Empty or invalid keyId.');
        }

        $this->fieldName = $settings['fieldName'];
        $this->groupId = (int) $keyParts[0];
        $this->keyId = (int) $keyParts[1];
        $this->language = $settings['language'] ?? null;
    }

    /**
     * @param ElementInterface $element
     * @param mixed $data
     *
     * @return void
     *
     * @throws InvalidConfigurationException
     */
    public function assignData(ElementInterface $element, $data)
    {
        $getter = 'get' . ucfirst($this->fieldName);
        $classificationStore = $element->$getter();

        if ($classificationStore instanceof \Pimcore\Model\DataObject\Classificationstore) {
            $classificationStore->setLocalizedKeyValue($this->groupId, $this->keyId, $data, $this->language);
            $classificationStore->setActiveGroups($classificationStore->getActiveGroups() + [$this->groupId => true]);
        } else {
            throw new InvalidConfigurationException('Field ' . $this->fieldName . ' is not a classification store.');
        }
    }

    public function getSchemaDescription(): string
    {
        return 'Classification store field mapping target';
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('settings');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @phpstan-ignore-next-line */
        $rootNode
            ->children()
                ->scalarNode('fieldName')
                    ->info('Name of the classification store field')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('keyId')
                    ->info('Classification store key ID in format <GROUP_ID>-<KEY_ID>')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('language')
                    ->info('Language for localized classification store values')
                    ->defaultValue(null)
                ->end()
            ->end();

        return $treeBuilder;
    }
}
