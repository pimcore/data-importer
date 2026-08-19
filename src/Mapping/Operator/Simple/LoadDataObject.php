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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Pimcore\Bundle\DataImporterBundle\Settings\TransformationTypeAwareInterface;
use Pimcore\Bundle\DataImporterBundle\Tool\DataObjectLoader;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
final class LoadDataObject extends AbstractOperator implements SchemaAwareInterface, TransformationTypeAwareInterface
{
    private const LOAD_STRATEGY_ID = 'id';

    private const LOAD_STRATEGY_PATH = 'path';

    private const LOAD_STRATEGY_ATTRIBUTE = 'attribute';

    private string $loadStrategy;

    private string $attributeLanguage;

    private string $attributeName;

    private string $attributeDataObjectClassId;

    private bool $partialMatch;

    private bool $loadUnpublished;

    private DataObjectLoader $dataObjectLoader;

    protected TransformationDataTypeService $transformationDataTypeService;

    /**
     * @param DataObjectLoader $dataObjectLoader
     */
    #[Required]
    public function setDataObjectLoader(DataObjectLoader $dataObjectLoader)
    {
        $this->dataObjectLoader = $dataObjectLoader;
    }

    #[Required]
    public function setTransformationDataTypeService(
        TransformationDataTypeService $transformationDataTypeService
    ): void {
        $this->transformationDataTypeService =
            $transformationDataTypeService;
    }

    public function setSettings(array $settings): void
    {
        $this->loadStrategy = $settings['loadStrategy'] ?? self::LOAD_STRATEGY_ID;
        $this->attributeLanguage = $settings['attributeLanguage'] ?? '';
        $this->attributeName = $settings['attributeName'] ?? '';
        $this->attributeDataObjectClassId = $settings['attributeDataObjectClassId'] ?? '';
        $this->partialMatch = $settings['partialMatch'] ?? false;
        $this->loadUnpublished = $settings['loadUnpublished'] ?? false;

        if ($this->loadStrategy === self::LOAD_STRATEGY_ATTRIBUTE) {
            if (empty($this->attributeDataObjectClassId)) {
                throw new InvalidConfigurationException('The attributeDataObjectClassId attribute is required');
            }

            $attributeClass = ClassDefinition::getById($this->attributeDataObjectClassId);
            if (empty($attributeClass)) {
                throw new InvalidConfigurationException(
                    "Class `{$this->attributeDataObjectClassId}` not found. " .
                    'Make sure to use an existing data object class ID.'
                );
            }

            $this->dataObjectLoader->assertAttributeLoadable(
                $this->attributeDataObjectClassId,
                $this->attributeName
            );
        }
    }

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return array|false|mixed|null
     *
     * @throws InvalidConfigurationException
     */
    public function process($inputData, bool $dryRun = false)
    {
        $returnScalar = false;
        if (!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }

        $objects = [];
        $prevHideUnpublished = DataObject::getHideUnpublished();

        if ($this->loadUnpublished) {
            DataObject::setHideUnpublished(false);
        }

        foreach ($inputData as $data) {
            $object = null;
            $logMessage = '';
            if (empty($data) === false || $data === '0') {
                if ($this->loadStrategy === self::LOAD_STRATEGY_PATH) {
                    $object = $this->dataObjectLoader->loadByPath(trim($data));
                    $logMessage = 'by path `' . trim($data) . '`';
                } elseif ($this->loadStrategy === self::LOAD_STRATEGY_ID) {
                    $object = $this->dataObjectLoader->loadById(trim($data));
                    $logMessage = 'by id `' . trim($data) . '`';
                } elseif ($this->loadStrategy === self::LOAD_STRATEGY_ATTRIBUTE) {
                    if ($this->attributeName) {
                        $operator = '=';
                        $class = ClassDefinition::getById($this->attributeDataObjectClassId);
                        if (empty($class)) {
                            throw new InvalidConfigurationException(
                                "Class `{$this->attributeDataObjectClassId}` not found."
                            );
                        }
                        $className = '\\Pimcore\\Model\\DataObject\\' . ucfirst($class->getName());
                        if ($this->partialMatch) {
                            $data = "%$data%";
                            $operator = 'LIKE';

                            if ($this->attributeLanguage) {
                                $logMessage = 'by attribute partially `%s` (class `%s`, value `%s`, language `%s`)';
                                $logMessage = sprintf(
                                    $logMessage,
                                    $this->attributeName,
                                    ucfirst($class->getName()),
                                    $data,
                                    $this->attributeLanguage
                                );
                            } else {
                                $logMessage = 'by attribute partially `%s` (class `%s`, value `%s`)';
                                $logMessage = sprintf(
                                    $logMessage,
                                    $this->attributeName,
                                    ucfirst($class->getName()),
                                    $data
                                );
                            }
                        } else {
                            if ($this->attributeLanguage) {
                                $logMessage = 'by attribute `%s` (class `%s`, value `%s`, language `%s`)';
                                $logMessage = sprintf(
                                    $logMessage,
                                    $this->attributeName,
                                    ucfirst($class->getName()),
                                    $data,
                                    $this->attributeLanguage
                                );
                            } else {
                                $logMessage = 'by attribute `%s` (class `%s`, value `%s`)';
                                $logMessage = sprintf(
                                    $logMessage,
                                    $this->attributeName,
                                    ucfirst($class->getName()),
                                    $data
                                );
                            }
                        }
                        $object = $this->dataObjectLoader->loadByAttribute($className,
                            $this->attributeName,
                            $data,
                            $this->attributeLanguage,
                            $this->loadUnpublished,
                            1,
                            $operator);
                    }
                } else {
                    throw new InvalidConfigurationException("Unknown load strategy '{ $this->loadStrategy }'");
                }

                if ($object instanceof DataObject) {
                    $objects[] = $object;
                } elseif (!$dryRun && !empty($data)) {
                    if (empty($logMessage)) {
                        $logMessage = "Could not load data object from `$data`";
                    } else {
                        $logMessage = 'Could not load data object ' . $logMessage;
                    }
                    $this->applicationLogger->warning($logMessage . ' ', [
                        'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
                    ]);
                }
            }
        }
        if ($this->loadUnpublished) {
            DataObject::setHideUnpublished($prevHideUnpublished);
        }

        if ($returnScalar) {
            if (!empty($objects)) {
                return reset($objects);
            }

            return null;
        } else {
            return $objects;
        }
    }

    /**
     * @param string $inputType
     * @param int|null $index
     *
     * @return string
     *
     * @throws InvalidConfigurationException
     */
    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        if ($inputType === TransformationDataTypeService::DEFAULT_TYPE) {
            return TransformationDataTypeService::DATA_OBJECT;
        } elseif ($inputType === TransformationDataTypeService::DEFAULT_ARRAY) {
            return TransformationDataTypeService::DATA_OBJECT_ARRAY;
        } else {
            throw new InvalidConfigurationException(sprintf(
                "Unsupported input type '%s' for load data object operator at transformation position %s",
                $inputType,
                $index
            ));
        }
    }

    /**
     * @param mixed $inputData
     *
     * @return array|false|mixed
     */
    public function generateResultPreview($inputData)
    {
        $returnScalar = false;
        if (!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }

        foreach ($inputData as &$data) {
            if ($data instanceof DataObject) {
                $data = 'DataObject: ' . $data->getFullPath() . ' (ID: ' . $data->getId() . ')';
            }
        }

        if ($returnScalar) {
            return reset($inputData);
        } else {
            return $inputData;
        }
    }

    public function getSchemaDescription(): string
    {
        return 'Loads existing Pimcore data objects by ID, path, or attribute value. '
            . 'Supports partial matching and loading unpublished objects.';
    }

    public function getAcceptedInputTypes(): array
    {
        return [
            TransformationDataTypeService::DEFAULT_TYPE,
            TransformationDataTypeService::DEFAULT_ARRAY
        ];
    }

    public function getOutputTypes(): array
    {
        return [
            TransformationDataTypeService::DATA_OBJECT,
            TransformationDataTypeService::DATA_OBJECT_ARRAY
        ];
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('settings');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @phpstan-ignore-next-line */
        $rootNode
            ->children()
                ->enumNode('loadStrategy')
                    ->isRequired()
                    ->info(
                        'Strategy for loading objects: "id" (by numeric ID), '
                        . '"path" (by full path), or "attribute" (by field value)'
                    )
                    ->values([self::LOAD_STRATEGY_ID, self::LOAD_STRATEGY_PATH, self::LOAD_STRATEGY_ATTRIBUTE])
                ->end()
                ->scalarNode('attributeLanguage')
                    ->info('Language for localized attribute lookup (only for "attribute" load strategy)')
                ->end()
                ->scalarNode('attributeName')
                    ->info('Field name to search by (only for "attribute" load strategy)')
                ->end()
                ->scalarNode('attributeDataObjectClassId')
                    ->info('Data object class ID to limit search scope (only for "attribute" load strategy)')
                ->end()
                ->booleanNode('partialMatch')
                    ->info('If true, uses LIKE matching for attribute values (only for "attribute" load strategy)')
                ->end()
                ->booleanNode('loadUnpublished')
                    ->info('If true, also loads unpublished objects')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
