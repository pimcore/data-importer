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

namespace Pimcore\Bundle\DataImporterBundle\Schema;

use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use Pimcore\Bundle\StudioBackendBundle\Util\Schema\AdditionalAttributesInterface;
use Pimcore\Bundle\StudioBackendBundle\Util\Trait\AdditionalAttributesTrait;

/**
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterAdHocImportFileResponse',
    title: 'Bundle Data Importer Ad Hoc Import File Response',
    required: ['maxRows', 'hasHeaderRow', 'sheets', 'rowCount', 'columns', 'firstRows'],
    type: 'object'
)]
final class AdHocImportFileResponse implements AdditionalAttributesInterface
{
    use AdditionalAttributesTrait;

    /**
     * @param string[] $sheets
     * @param array<int, array{dataIndex: string, label: string}> $columns
     * @param array<int, array<int, string|null>> $firstRows
     */
    public function __construct(
        #[Property(description: 'Rows a file may have', type: 'integer', example: 5000)]
        private readonly int $maxRows,
        #[Property(description: 'The stored file; null if it was refused for good and not kept', type: 'string', example: '0f8fad5b-d9cb-469f-a165-70867728950e', nullable: true)]
        private readonly ?string $fileId = null,
        #[Property(description: 'The file type; null if it is none of the supported ones', type: 'string', enum: ['csv', 'xlsx'], example: 'csv', nullable: true)]
        private readonly ?string $type = null,
        #[Property(description: 'Why the file cannot be imported under these settings; null if it can', type: 'string', enum: ['too-many-rows', 'unreadable', 'wrong-format'], example: null, nullable: true)]
        private readonly ?string $refusal = null,
        #[Property(description: 'CSV: the delimiter in use', type: 'string', example: ';', nullable: true)]
        private readonly ?string $delimiter = null,
        #[Property(description: 'CSV: the delimiter detected in the file', type: 'string', example: ';', nullable: true)]
        private readonly ?string $detectedDelimiter = null,
        #[Property(description: 'CSV: the enclosure in use', type: 'string', example: '"', nullable: true)]
        private readonly ?string $enclosure = null,
        #[Property(description: 'CSV: the escape character in use', type: 'string', example: '\\', nullable: true)]
        private readonly ?string $escape = null,
        #[Property(description: 'Whether the first row holds the column names', type: 'boolean', example: true)]
        private readonly bool $hasHeaderRow = true,
        #[Property(description: 'XLSX: the sheet in use', type: 'string', example: 'Products', nullable: true)]
        private readonly ?string $sheetName = null,
        #[Property(description: 'XLSX: the sheets of the file', type: 'array', items: new Items(type: 'string'))]
        private readonly array $sheets = [],
        #[Property(description: 'Rows of data', type: 'integer', example: 312)]
        private readonly int $rowCount = 0,
        #[Property(
            description: 'The columns; dataIndex is the key of the column in a row of an import',
            type: 'array',
            items: new Items(
                required: ['dataIndex', 'label'],
                properties: [
                    new Property(property: 'dataIndex', type: 'string', example: 'Artikelnr'),
                    new Property(property: 'label', type: 'string', example: 'Artikelnr'),
                ],
                type: 'object'
            )
        )]
        private readonly array $columns = [],
        #[Property(
            description: 'The first rows, their values in the order of the columns',
            type: 'array',
            items: new Items(type: 'array', items: new Items(type: 'string', nullable: true))
        )]
        private readonly array $firstRows = [],
    ) {
    }

    public function getMaxRows(): int
    {
        return $this->maxRows;
    }

    public function getFileId(): ?string
    {
        return $this->fileId;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getRefusal(): ?string
    {
        return $this->refusal;
    }

    public function getDelimiter(): ?string
    {
        return $this->delimiter;
    }

    public function getDetectedDelimiter(): ?string
    {
        return $this->detectedDelimiter;
    }

    public function getEnclosure(): ?string
    {
        return $this->enclosure;
    }

    public function getEscape(): ?string
    {
        return $this->escape;
    }

    public function getHasHeaderRow(): bool
    {
        return $this->hasHeaderRow;
    }

    public function getSheetName(): ?string
    {
        return $this->sheetName;
    }

    public function getSheets(): array
    {
        return $this->sheets;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getFirstRows(): array
    {
        return $this->firstRows;
    }
}
