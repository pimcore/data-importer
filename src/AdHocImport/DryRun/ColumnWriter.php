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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun;

use Pimcore\Bundle\DataImporterBundle\AdHocImport\ConvertedColumn;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\Classificationstore;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\DataTargetInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\Direct;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\User;

/**
 * Writes one column of the file, transformed and converted beforehand, into its field.
 *
 * @internal
 */
final readonly class ColumnWriter implements FieldWriter
{
    private DataTargetInterface $target;

    public function __construct(
        private ImportField $field,
        private int|string $column,
        private ConvertedColumn $converted,
        private ?UserReferenceVisibility $references = null,
    ) {
        if ($field->storeKey !== null) {
            $this->target = new Classificationstore();
            $this->target->setSettings([
                'fieldName' => (string) $field->key,
                'keyId' => $field->storeKey->id(),
                'language' => $field->language ?? 'default',
            ]);

            return;
        }

        $this->target = new Direct();
        // an empty cell clears the field, as in a Data Importer run
        $this->target->setSettings([
            'fieldName' => (string) $field->key,
            'language' => $field->language,
            'writeIfSourceIsEmpty' => true,
            'writeIfTargetIsNotEmpty' => true,
        ]);
    }

    public function field(): ImportField
    {
        return $this->field;
    }

    public function write(Concrete $object, int $index, array $row): ?ValueError
    {
        if ($this->converted->failed[$index] ?? true) {
            return ($this->converted->failedInSteps[$index] ?? true)
                ? ValueError::Invalid
                : ValueError::ofConversion($this->converted->conversion);
        }

        $value = $this->converted->values[$index];
        // dropping the element clears or shortens what the field holds, and the row would still count as saved
        if (($this->converted->lost[$index] ?? false) || $this->references?->hidesAnElementIn($value) === true) {
            return ValueError::Invalid;
        }

        try {
            $this->target->assignData($object, $value);
        } catch (\Throwable $throwable) { // NOSONAR php:S2221 a setter refuses a value of the wrong type with a \TypeError
            if ($throwable instanceof \Error && !$throwable instanceof \TypeError) {
                throw $throwable;
            }

            return ValueError::Invalid;
        }

        return null;
    }

    public function fileValue(array $row): ?string
    {
        $value = $row[$this->column] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    public function forUser(User $user): self
    {
        // the values were loaded for the user the plan was made for
        return new self($this->field, $this->column, $this->converted, new UserReferenceVisibility($user));
    }
}
