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

use Pimcore\Bundle\DataImporterBundle\Exception\MappingApplicationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\PreparedMapping;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceVisibilityInterface;
use Pimcore\Model\DataObject\Concrete;

/**
 * Writes one item of a Data Importer configuration's mapping.
 *
 * @internal
 */
final readonly class ConfigurationItemWriter implements FieldWriter
{
    /**
     * @param PreparedMapping $mapping the item alone
     * @param list<int|string> $columns the columns it reads, keyed as the file's rows
     */
    public function __construct(
        private ImportField $field,
        private PreparedMapping $mapping,
        private array $columns,
        private ReferenceVisibilityInterface $references,
    ) {
    }

    public function field(): ImportField
    {
        return $this->field;
    }

    public function write(Concrete $object, int $index, array $row): ?ValueError
    {
        try {
            // a warning is what an import would log, e.g. a reference it could not find
            return $this->mapping->apply($object, $row, $this->references) === [] ? null : ValueError::Invalid;
        } catch (MappingApplicationException) {
            return ValueError::Invalid;
        }
    }

    public function fileValue(array $row): ?string
    {
        $values = [];
        foreach ($this->columns as $column) {
            $value = $row[$column] ?? null;
            if (is_scalar($value) && (string) $value !== '') {
                $values[] = (string) $value;
            }
        }

        return $values === [] ? null : implode(' · ', $values);
    }
}
