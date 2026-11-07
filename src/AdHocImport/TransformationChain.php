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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\OperatorInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportTransformationCell;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportTransformationResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Model\DataObject\Data\Hotspotimage;
use Pimcore\Model\DataObject\Data\ImageGallery;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\User;

/**
 * Runs the transformations of one column on its values in the first rows, adds the conversion to the field's type and
 * says which transformations can come next.
 *
 * @internal
 */
final readonly class TransformationChain
{
    // operators log under their configuration's name; an ad hoc import has none
    private const string CONFIG_NAME = '';

    // what the transformations of one column can lead to; a field takes those of them its type is listed for
    private const array RESULT_TYPES = [
        TransformationDataTypeService::DEFAULT_TYPE,
        TransformationDataTypeService::NUMERIC,
        TransformationDataTypeService::BOOLEAN,
        TransformationDataTypeService::DEFAULT_ARRAY,
        TransformationDataTypeService::DATE,
        TransformationDataTypeService::DATE_ARRAY,
        TransformationDataTypeService::ASSET,
        TransformationDataTypeService::ASSET_ARRAY,
        TransformationDataTypeService::GALLERY,
        TransformationDataTypeService::DATA_OBJECT,
        TransformationDataTypeService::DATA_OBJECT_ARRAY,
    ];

    private const string DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private MappingConfigurationFactory $mappingConfigurationFactory,
        private DataTypeServiceInterface $dataTypeService,
    ) {
    }

    /**
     * Per field of the class the result types it takes.
     *
     * @return array<string, list<string>>
     */
    public function acceptedTypes(string $classId): array
    {
        $accepted = [];
        foreach (self::RESULT_TYPES as $type) {
            $attributes = $this->dataTypeService->loadClassAttributes($classId, $type, false, true, false);
            foreach ($attributes->getAttributes() as $attribute) {
                $accepted[$attribute['key']][] = $type;
            }
        }

        return $accepted;
    }

    /**
     * Whether a column goes into a field taking these types without a transformation.
     *
     * @param list<string> $accepted
     */
    public function takesColumnAsIs(array $accepted): bool
    {
        return $this->conversionFor(TransformationDataTypeService::DEFAULT_TYPE, $accepted) !== false;
    }

    /**
     * @param list<string|null> $values the column's values in the first rows
     * @param list<array{type: string, settings: array<string, string>}> $steps checked against the allow list
     * @param list<string> $accepted the result types the field takes
     * @param User $user loaded objects and assets the user may not see are not found
     *
     * @throws InvalidConfigurationException
     */
    public function run(array $values, array $steps, array $accepted, User $user): AdHocImportTransformationResponse
    {
        $operators = $this->operators($steps);

        $type = TransformationDataTypeService::DEFAULT_TYPE;
        $data = $values;
        $failed = array_fill(0, count($values), false);
        $stepResults = [];
        foreach ($operators as $position => $operator) {
            $error = null;
            if ($type !== null) {
                try {
                    $type = $operator->evaluateReturnType($type, $position + 1);
                } catch (InvalidConfigurationException $exception) {
                    $type = null;
                    $error = $exception->getMessage();
                }
            }

            if ($type !== null) {
                [$data, $failed] = $this->process($operator, $data, $failed, $user);
            }

            $stepResults[] = [
                'type' => $steps[$position]['type'],
                'resultType' => $type,
                'error' => $error,
                'values' => $type === null ? [] : $this->cells($data, $failed),
            ];
        }

        // what comes next takes the steps' result, not its conversion
        $offered = $type === null ? [] : $this->offered($type, $accepted);
        $conversion = $type === null ? false : $this->conversionFor($type, $accepted);
        $results = [];
        if ($conversion !== false) {
            if ($conversion !== null) {
                $converter = $this->operators([['type' => $conversion, 'settings' => []]])[0];
                $type = $converter->evaluateReturnType($type, count($operators) + 1);
                [$data, $failed] = $this->process($converter, $data, $failed, $user);
            }
            $results = $this->cells($data, $failed);
        }

        return new AdHocImportTransformationResponse(
            $this->cells($values, array_fill(0, count($values), false)),
            $stepResults,
            $conversion === false ? null : $conversion,
            $type,
            $conversion !== false,
            $results,
            $offered
        );
    }

    /**
     * The operator that converts a result of this type into the field, null if none is needed, false if none can.
     *
     * @param list<string> $accepted
     */
    private function conversionFor(string $type, array $accepted): string|null|false
    {
        if (in_array($type, $accepted, true)) {
            return null;
        }

        foreach (TransformationAllowList::CONVERSIONS as $conversion) {
            if (in_array($this->resultOf($conversion, $type), $accepted, true)) {
                return $conversion;
            }
        }

        return false;
    }

    /**
     * The transformations that take a result of this type and after which the field can still be reached.
     *
     * @param list<string> $accepted
     *
     * @return list<string>
     */
    private function offered(string $type, array $accepted): array
    {
        return array_values(array_filter(
            TransformationAllowList::types(),
            function (string $operator) use ($type, $accepted): bool {
                $next = $this->resultOf($operator, $type);

                return $next !== null && $this->reaches($next, $accepted);
            }
        ));
    }

    /**
     * @param list<string> $accepted
     * @param array<string, true> $seen
     */
    private function reaches(string $type, array $accepted, array $seen = []): bool
    {
        if ($this->conversionFor($type, $accepted) !== false) {
            return true;
        }
        $seen[$type] = true;

        foreach (TransformationAllowList::types() as $operator) {
            $next = $this->resultOf($operator, $type);
            if ($next !== null && !isset($seen[$next]) && $this->reaches($next, $accepted, $seen)) {
                return true;
            }
        }

        return false;
    }

    private function resultOf(string $operatorType, string $type): ?string
    {
        // a conversion takes no settings
        $step = in_array($operatorType, TransformationAllowList::types(), true)
            ? TransformationAllowList::step(['type' => $operatorType], 0)
            : ['type' => $operatorType, 'settings' => []];

        try {
            return $this->operators([$step])[0]->evaluateReturnType($type);
        } catch (InvalidConfigurationException) {
            return null;
        }
    }

    /**
     * @param list<array{type: string, settings: array<string, string>}> $steps
     *
     * @return list<OperatorInterface>
     *
     * @throws InvalidConfigurationException
     */
    private function operators(array $steps): array
    {
        return array_values($this->mappingConfigurationFactory->loadMappingConfigurationItem(
            self::CONFIG_NAME,
            ['transformationPipeline' => $steps],
            true
        )->getTransformationPipeline());
    }

    /**
     * Runs one step on every row that has not failed yet.
     *
     * @param list<mixed> $data
     * @param list<bool> $failed
     *
     * @return array{0: list<mixed>, 1: list<bool>}
     */
    private function process(OperatorInterface $operator, array $data, array $failed, User $user): array
    {
        foreach ($data as $row => $value) {
            if ($failed[$row]) {
                continue;
            }

            try {
                // a dry run logs no misses
                $data[$row] = $this->visibleTo($user, $operator->process($value, true));
            } catch (\Throwable) { // NOSONAR php:S2221 an operator may fail in any way on a value from the file
                $data[$row] = null;
                $failed[$row] = true;
            }
        }

        return [$data, $failed];
    }

    /**
     * A loaded element the user may not see counts as not found, so the preview tells nothing about it.
     */
    private function visibleTo(User $user, mixed $value): mixed
    {
        if ($value instanceof ElementInterface) {
            return $value->isAllowed('view', $user) ? $value : null;
        }
        if (is_array($value)) {
            $visible = [];
            foreach ($value as $key => $item) {
                $item = $this->visibleTo($user, $item);
                if ($item !== null) {
                    $visible[$key] = $item;
                }
            }

            return array_is_list($value) ? array_values($visible) : $visible;
        }

        return $value;
    }

    /**
     * @param list<mixed> $data
     * @param list<bool> $failed
     *
     * @return list<AdHocImportTransformationCell>
     */
    private function cells(array $data, array $failed): array
    {
        $cells = [];
        foreach ($data as $row => $value) {
            $cells[] = new AdHocImportTransformationCell($failed[$row] ? [] : $this->items($value), $failed[$row]);
        }

        return $cells;
    }

    /**
     * @return list<string>
     */
    private function items(mixed $value): array
    {
        if ($value instanceof ImageGallery) {
            $value = array_map(
                static fn (?Hotspotimage $item): mixed => $item?->getImage(),
                $value->getItems()
            );
        }

        if (is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                $text = $this->text($item);
                if ($text !== null) {
                    $items[] = $text;
                }
            }

            return $items;
        }

        $text = $this->text($value);

        return $text === null ? [] : [$text];
    }

    private function text(mixed $value): ?string
    {
        return match (true) {
            $value === null, $value === '' => null,
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            $value instanceof ElementInterface => $value->getRealFullPath(),
            $value instanceof \DateTimeInterface => $value->format(self::DATE_FORMAT),
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null,
            // nothing an allowed operator returns; named so a preview still shows there is a value
            default => get_debug_type($value),
        };
    }
}
