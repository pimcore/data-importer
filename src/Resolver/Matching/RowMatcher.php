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

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Matching;

use Collator;
use Pimcore\Bundle\DataImporterBundle\Resolver\Resolver;
use Pimcore\Bundle\DataImporterBundle\Tool\DataObjectLoader;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Numeric;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service;

/**
 * Resolves the rows of one file to their objects: it finds out which object a row means, or why it means none, and
 * leaves loading and creating to the resolver. Keeps what the file's earlier rows claimed, so use one per file and
 * pass the rows in the order of the file. Writes nothing: a new object stays unsaved, and an existing one is the
 * instance Pimcore keeps in its runtime cache, so copy it if other code must not see the mapping's changes.
 *
 * @internal use RowMatcherFactory::create()
 */
final class RowMatcher implements RowMatcherInterface
{
    // the resolver loads what this class found, so it reads an id from a row of its own, not from the file's row
    public const OBJECT_ID_INDEX = 'objectId';

    private const MAX_REPORTED_MATCHES = 5;

    /** @var array<string, int> */
    private array $rowByValue = [];

    /** @var array<int, int> */
    private array $rowByObjectId = [];

    /** @var array<string, int> */
    private array $rowByNewKey = [];

    private readonly Collator $valueCollator;

    // where new objects land, when that is not already part of the target folder
    private readonly ?string $newObjectPath;

    /**
     * @param string $targetPath the normalized path of the target folder, searched with what lies below it
     * @param Data|null $field the field to match by, whose rules the values are checked against; null is the Object ID
     */
    public function __construct(
        private readonly Resolver $resolver,
        private readonly DataObjectLoader $dataObjectLoader,
        private readonly MatchingConfiguration $configuration,
        private readonly string $className,
        private readonly string $targetPath,
        private readonly ElementInterface $newObjectFolder,
        private readonly ?Data $field = null,
    ) {
        $this->valueCollator = new Collator('root');
        $this->valueCollator->setStrength(Collator::PRIMARY);

        $newObjectPath = rtrim($newObjectFolder->getRealFullPath(), '/') . '/';
        $this->newObjectPath = str_starts_with($newObjectPath, $targetPath) ? null : $newObjectPath;
    }

    /**
     * @param array<int|string, mixed> $row import data row, keyed like the rows of the Data Importer
     * @param int $rowNumber the row's number in the file, reported back by later rows that refer to it
     */
    public function match(array $row, int $rowNumber): RowMatch
    {
        $cell = $row[$this->configuration->dataSourceIndex] ?? null;
        $value = is_scalar($cell) ? trim((string) $cell) : '';
        if ($value === '') {
            return new RowMatch(RowMatchOutcome::EmptyValue, '');
        }

        // the error row and a match report the file's spelling; the lookup and a new key use the number's own
        $lookupValue = $this->lookupValue($value);
        if ($lookupValue === null) {
            return new RowMatch(RowMatchOutcome::InvalidValue, $value);
        }

        // keys tell case and accents apart, so only the key check below finds a row creating what an earlier one does
        if ($this->configuration->createOnly) {
            return $this->newObject($value, $lookupValue, $rowNumber);
        }

        // the class tables' collation ignores case and accents, so `abc` and `ABC` would be one object after the import
        $valueKey = $this->valueCollator->getSortKey($lookupValue) ?: $lookupValue;
        if (isset($this->rowByValue[$valueKey])) {
            return new RowMatch(RowMatchOutcome::DuplicateInFile, $value, firstRow: $this->rowByValue[$valueKey]);
        }
        $this->rowByValue[$valueKey] = $rowNumber;

        $found = $this->configuration->attributeName === null
            ? $this->findById($value)
            : $this->findByAttribute($this->configuration->attributeName, $value, $lookupValue);

        if ($found instanceof RowMatch) {
            return $found;
        }

        return $found === null
            ? $this->newObject($value, $lookupValue, $rowNumber)
            : $this->existingObject($value, $found, $rowNumber);
    }

    private function lookupValue(string $value): ?string
    {
        if ($this->field instanceof Numeric) {
            return NumericMatchValue::normalize($value, $this->field);
        }

        return $this->field === null || TextMatchValue::fits($value, $this->field) ? $value : null;
    }

    private function findById(string $value): RowMatch|int
    {
        // not a cast: an import's (int) '12abc' would load object 12
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $object = $id === false ? null : DataObject::getById($id);
        if ($object === null) {
            // Pimcore assigns ids itself, so a new object could never take this one
            return new RowMatch(RowMatchOutcome::UnknownObjectId, $value);
        }

        $path = $object->getRealPath();
        if (!str_starts_with($path, $this->targetPath) && $path !== $this->newObjectPath) {
            return new RowMatch(RowMatchOutcome::OutsideTargetFolder, $value, objectIds: [$id]);
        }

        if (!$object instanceof Concrete || $object->getClassId() !== $this->configuration->classId) {
            return new RowMatch(RowMatchOutcome::OtherClass, $value, objectIds: [$id]);
        }

        return $id;
    }

    private function findByAttribute(string $attributeName, string $value, string $lookupValue): RowMatch|int|null
    {
        $ids = $this->findIds($attributeName, $lookupValue, $this->targetPath, self::MAX_REPORTED_MATCHES);
        if ($this->newObjectPath !== null) {
            array_push(
                $ids,
                ...$this->findIds($attributeName, $lookupValue, $this->newObjectPath, self::MAX_REPORTED_MATCHES, true)
            );
        }
        $ids = array_slice($ids, 0, self::MAX_REPORTED_MATCHES);

        if (count($ids) > 1) {
            return new RowMatch(RowMatchOutcome::SeveralMatches, $value, objectIds: $ids);
        }

        if ($ids !== []) {
            return $ids[0];
        }

        $elsewhere = $this->findIds($attributeName, $lookupValue, '', 1);

        return $elsewhere === []
            ? null
            : new RowMatch(RowMatchOutcome::OutsideTargetFolder, $value, objectIds: $elsewhere);
    }

    /**
     * @return list<int>
     */
    private function findIds(
        string $attributeName,
        string $value,
        string $path,
        int $limit,
        bool $directlyInPath = false
    ): array {
        $find = $this->field instanceof Numeric
            ? $this->dataObjectLoader->findIdsByNumber(...)
            : $this->dataObjectLoader->findIdsByAttribute(...);

        return $find(
            $this->className,
            $attributeName,
            $value,
            $this->configuration->language,
            $path,
            $limit,
            $directlyInPath
        );
    }

    private function existingObject(string $value, int $id, int $rowNumber): RowMatch
    {
        // two values can mean one object: the field's collation may ignore case, and `+7` is the id 7
        if (isset($this->rowByObjectId[$id])) {
            return new RowMatch(
                RowMatchOutcome::DuplicateInFile,
                $value,
                objectIds: [$id],
                firstRow: $this->rowByObjectId[$id]
            );
        }
        $this->rowByObjectId[$id] = $rowNumber;

        $element = $this->resolver->loadOrCreateAndPrepareElement([self::OBJECT_ID_INDEX => (string) $id]);

        return new RowMatch(RowMatchOutcome::Existing, $value, $element);
    }

    private function newObject(string $value, string $lookupValue, int $rowNumber): RowMatch
    {
        $key = Service::getValidKey($lookupValue, 'object');
        if ($key === '') {
            return new RowMatch(RowMatchOutcome::EmptyValue, $value);
        }

        if (isset($this->rowByNewKey[$key])) {
            return new RowMatch(RowMatchOutcome::KeyTaken, $value, firstRow: $this->rowByNewKey[$key]);
        }
        $this->rowByNewKey[$key] = $rowNumber;

        $taken = DataObject::getByPath(rtrim($this->newObjectFolder->getRealFullPath(), '/') . '/' . $key);
        if ($taken !== null) {
            return new RowMatch(RowMatchOutcome::KeyTaken, $value, objectIds: [(int) $taken->getId()]);
        }

        $element = $this->resolver->loadOrCreateAndPrepareElement([self::OBJECT_ID_INDEX => null]);
        $element->setKey($key);
        $element->setParent($this->newObjectFolder);

        return new RowMatch(RowMatchOutcome::New, $value, $element);
    }
}
