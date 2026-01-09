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
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class ClassificationstoreBatch implements DataTargetInterface, SchemaAwareInterface
{
    /**
     * @var string
     */
    protected $fieldName;

    /**
     * @var string
     */
    protected $language;

    public function setSettings(array $settings): void
    {
        if (empty($settings['fieldName'])) {
            throw new InvalidConfigurationException('Empty field name.');
        }

        $this->fieldName = $settings['fieldName'];
        $this->language = $settings['language'] ?? null;
    }

    /**
     * @param ElementInterface $element
     * @param mixed $data
     *
     * @return void
     *
     * @throws InvalidConfigurationException
     * @throws InvalidInputException
     */
    public function assignData(ElementInterface $element, $data)
    {
        $getter = 'get' . ucfirst($this->fieldName);
        $classificationStore = $element->$getter();

        if ($classificationStore instanceof \Pimcore\Model\DataObject\Classificationstore) {
            if (!is_array($data)) {
                throw new InvalidInputException('Input data not an array');
            }

            $data = array_filter($data);
            if (!empty($data)) {
                foreach ($data as $key => $value) {
                    $keyParts = explode('-', $key);
                    if (count($keyParts) !== 2) {
                        throw new InvalidInputException('Key not format <GROUP_ID>-<KEY_ID>: ' . $key);
                    }

                    if (!is_numeric($keyParts[0])) {
                        throw new \Exception('groupId not valid');
                    }

                    if (!is_numeric($keyParts[1])) {
                        throw new \Exception('keyId not valid');
                    }

                    $classificationStore->setLocalizedKeyValue((int)$keyParts[0], (int)$keyParts[1], $value, $this->language);
                    $classificationStore->setActiveGroups($classificationStore->getActiveGroups() + [$keyParts[0] => true]);
                }
            }
        } else {
            throw new InvalidConfigurationException('Field ' . $this->fieldName . ' is not a classification store.');
        }
    }

    public function getSchemaDescription(): string
    {
        return 'Classification store batch mapping target for multiple keys at once';
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
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
                ->scalarNode('language')
                    ->info('Language for localized classification store values')
                    ->defaultValue(null)
                ->end()
            ->end();

        return $treeBuilder;
    }
}
