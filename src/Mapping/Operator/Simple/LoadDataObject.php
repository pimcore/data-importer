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
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplicationScopeAwareTrait;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceLoadStrategy;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceQuery;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceType;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Bundle\DataImporterBundle\Tool\DataObjectLoader;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
final class LoadDataObject extends AbstractOperator
{
    use MappingApplicationScopeAwareTrait;

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

    /**
     * @param DataObjectLoader $dataObjectLoader
     */
    #[Required]
    public function setDataObjectLoader(DataObjectLoader $dataObjectLoader)
    {
        $this->dataObjectLoader = $dataObjectLoader;
    }

    public function setSettings(array $settings): void
    {
        $this->loadStrategy = $settings['loadStrategy'] ?? self::LOAD_STRATEGY_ID;
        $this->attributeLanguage = $settings['attributeLanguage'] ?? '';
        $this->attributeName = $settings['attributeName'] ?? '';
        $this->attributeDataObjectClassId = $settings['attributeDataObjectClassId'] ?? '';
        $this->partialMatch = $settings['partialMatch'] ?? false;
        $this->loadUnpublished = $settings['loadUnpublished'] ?? false;
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

        $prevHideUnpublished = DataObject::getHideUnpublished();
        if ($this->loadUnpublished) {
            DataObject::setHideUnpublished(false);
        }

        try {
            $objects = $this->loadDataObjects($inputData, $dryRun);
        } finally {
            // the flag is global and a reference lookup may throw
            if ($this->loadUnpublished) {
                DataObject::setHideUnpublished($prevHideUnpublished);
            }
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
     * @param array<mixed> $inputData
     *
     * @return DataObject[]
     *
     * @throws InvalidConfigurationException
     */
    private function loadDataObjects(array $inputData, bool $dryRun): array
    {
        $objects = [];
        foreach ($inputData as $data) {
            if (empty($data) && $data !== '0') {
                continue;
            }

            $referencedObject = $this->lookupDataObject($data);
            if ($referencedObject !== null) {
                $objects[] = $referencedObject;

                continue;
            }

            [$object, $logMessage, $data] = $this->loadByStrategy($data);
            if ($object instanceof DataObject) {
                $objects[] = $object;
            } elseif (!$dryRun && !empty($data)) {
                $this->reportMiss($data, $logMessage);
            }
        }

        return $objects;
    }

    /**
     * @return array{0: ?ElementInterface, 1: string, 2: mixed} the element, what was tried, the value as searched
     *
     * @throws InvalidConfigurationException
     */
    private function loadByStrategy(mixed $data): array
    {
        if ($this->loadStrategy === self::LOAD_STRATEGY_PATH) {
            return [$this->dataObjectLoader->loadByPath(trim($data)), 'by path `' . trim($data) . '`', $data];
        }
        if ($this->loadStrategy === self::LOAD_STRATEGY_ID) {
            return [$this->dataObjectLoader->loadById(trim($data)), 'by id `' . trim($data) . '`', $data];
        }
        if ($this->loadStrategy === self::LOAD_STRATEGY_ATTRIBUTE) {
            return $this->attributeName ? $this->loadByAttribute($data) : [null, '', $data];
        }

        throw new InvalidConfigurationException("Unknown load strategy '{ $this->loadStrategy }'");
    }

    /**
     * @return array{0: ?ElementInterface, 1: string, 2: mixed}
     *
     * @throws InvalidConfigurationException
     */
    private function loadByAttribute(mixed $data): array
    {
        $operator = '=';
        $class = ClassDefinition::getById($this->attributeDataObjectClassId);
        if (empty($class)) {
            throw new InvalidConfigurationException("Class `{$this->attributeDataObjectClassId}` not found.");
        }
        $classFqcn = '\\Pimcore\\Model\\DataObject\\' . ucfirst($class->getName());
        $how = 'by attribute';
        if ($this->partialMatch) {
            $data = "%$data%";
            $operator = 'LIKE';
            $how = 'by attribute partially';
        }
        $className = ucfirst($class->getName());
        $logMessage = sprintf('%s `%s` (class `%s`, value `%s`', $how, $this->attributeName, $className, $data)
            . ($this->attributeLanguage ? sprintf(', language `%s`)', $this->attributeLanguage) : ')');

        $object = $this->dataObjectLoader->loadByAttribute($classFqcn,
            $this->attributeName,
            $data,
            $this->attributeLanguage,
            $this->loadUnpublished,
            1,
            $operator);

        return [$object, $logMessage, $data];
    }

    private function reportMiss(mixed $data, string $logMessage): void
    {
        $logMessage = $logMessage === ''
            ? "Could not load data object from `$data`"
            : 'Could not load data object ' . $logMessage;
        if (!$this->reportWarningIfAppliedWithoutSaving($logMessage)) {
            $this->applicationLogger->warning($logMessage . ' ', [
                'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
            ]);
        }
    }

    private function lookupDataObject(mixed $data): ?DataObject
    {
        $loadStrategy = ReferenceLoadStrategy::tryFrom($this->loadStrategy);
        if ($loadStrategy === null || !$this->hasReferenceLookup()) {
            return null;
        }

        if ($loadStrategy !== ReferenceLoadStrategy::Attribute) {
            $query = new ReferenceQuery(
                ReferenceType::DataObject,
                $loadStrategy,
                trim((string) $data),
                includeUnpublished: $this->loadUnpublished
            );
        } elseif ($this->attributeName !== '') {
            $query = new ReferenceQuery(
                ReferenceType::DataObject,
                $loadStrategy,
                (string) $data,
                $this->attributeDataObjectClassId,
                $this->attributeName,
                $this->attributeLanguage,
                $this->partialMatch,
                $this->loadUnpublished
            );
        } else {
            return null;
        }

        return $this->lookupReference($query, DataObject::class);
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
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for load data object operator at transformation position %s", $inputType, $index));
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
}
