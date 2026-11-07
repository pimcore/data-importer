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

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\SettingsInput;

/**
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterAdHocImportFileSettings',
    title: 'Bundle Data Importer Ad Hoc Import File Settings',
    type: 'object'
)]
final readonly class AdHocImportFileSettings
{
    public function __construct(
        #[Property(
            description: 'CSV: the delimiter; empty for the detected one',
            type: 'string',
            example: ';',
            nullable: true
        )]
        private ?string $delimiter = null,
        #[Property(description: 'CSV: the enclosure', type: 'string', example: '"', nullable: true)]
        private ?string $enclosure = null,
        #[Property(description: 'CSV: the escape character', type: 'string', example: '\\', nullable: true)]
        private ?string $escape = null,
        #[Property(description: 'Whether the first row holds the column names', type: 'boolean', example: true)]
        private bool $hasHeaderRow = true,
        #[Property(
            description: 'XLSX: the sheet; empty for the first one',
            type: 'string',
            example: 'Products',
            nullable: true
        )]
        private ?string $sheetName = null,
    ) {
    }

    public function getDelimiter(): ?string
    {
        return $this->delimiter;
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

    public function toInput(): SettingsInput
    {
        return new SettingsInput(
            $this->delimiter,
            $this->enclosure,
            $this->escape,
            $this->hasHeaderRow,
            $this->sheetName
        );
    }
}
