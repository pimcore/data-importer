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

use OpenApi\Attributes\AdditionalProperties;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\SettingsInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What to check a file for: its settings, how its rows find their objects, and the mapping, either the columns mapped
 * here or a Data Importer configuration, whose class, match and folder replace those given.
 *
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterAdHocImportDryRunParameters',
    title: 'Bundle Data Importer Ad Hoc Import Dry Run Parameters',
    required: ['folderId', 'classId', 'matchBy', 'matchColumn'],
    type: 'object'
)]
final readonly class AdHocImportDryRunParameters
{
    public const string MATCH_BY_ID = 'id';

    public const string MATCH_BY_FIELD = 'field';

    // more columns than a file of the row limit has cells per row
    private const int MAX_COLUMNS = 200;

    /**
     * @param list<mixed> $columns
     * @param array<mixed> $configurationColumns
     */
    public function __construct(
        #[Property(description: 'The ID of the data object folder the import goes into', type: 'integer', example: 7)]
        #[Assert\Positive]
        private int $folderId,
        #[Property(description: 'The ID of the data object class to import into', type: 'string', example: 'Product')]
        private string $classId,
        #[Property(
            description: 'Match existing objects by their Object ID or by a field',
            type: 'string',
            enum: [self::MATCH_BY_ID, self::MATCH_BY_FIELD],
            example: self::MATCH_BY_FIELD
        )]
        #[Assert\Choice([self::MATCH_BY_ID, self::MATCH_BY_FIELD])]
        private string $matchBy,
        #[Property(description: 'Data index of the column holding the match value', type: 'string', example: 'SKU')]
        private string $matchColumn,
        #[Property(description: 'Key of the field to match by', type: 'string', example: 'sku', nullable: true)]
        private ?string $matchField = null,
        #[Property(description: 'Language of a localized field to match by', type: 'string', nullable: true)]
        private ?string $matchLanguage = null,
        #[Property(
            description: 'Path of the folder new objects go to; empty for the folder the import goes into',
            type: 'string',
            example: '/Products/New',
            nullable: true
        )]
        private ?string $newObjectsFolder = null,
        #[Property(
            description: 'The columns mapped here, each into one field; the column matched by goes into its field too',
            type: 'array',
            items: new Items(
                required: ['column', 'field'],
                properties: [
                    new Property(property: 'column', description: 'Data index of the column', type: 'string'),
                    new Property(property: 'field', description: 'Key of the field', type: 'string'),
                    new Property(
                        property: 'language',
                        description: 'Language of a localized field',
                        type: 'string',
                        nullable: true
                    ),
                    new Property(
                        property: 'key',
                        description: 'For a classification store, the key the column goes into, as <group id>-<key id>',
                        type: 'string',
                        nullable: true
                    ),
                    new Property(
                        property: 'transformationPipeline',
                        description: 'The transformations of the column, in order',
                        type: 'array',
                        items: new Items(type: 'object')
                    ),
                ],
                type: 'object'
            )
        )]
        #[Assert\Count(max: self::MAX_COLUMNS)]
        private array $columns = [],
        #[Property(
            description: 'Name of the Data Importer configuration to map with instead',
            type: 'string',
            nullable: true
        )]
        private ?string $configuration = null,
        #[Property(
            description: 'Per column the configuration reads, the data index of that column in the file',
            type: 'object',
            additionalProperties: new AdditionalProperties(type: 'string')
        )]
        #[Assert\Count(max: self::MAX_COLUMNS)]
        private array $configurationColumns = [],
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

    /**
     * @param array<string, mixed> $values what toArray() gave
     */
    public static function fromArray(array $values): self
    {
        return new self(...$values);
    }

    /**
     * @return array<string, mixed> every parameter by its name
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public function getFolderId(): int
    {
        return $this->folderId;
    }

    public function getClassId(): string
    {
        return $this->classId;
    }

    public function getMatchBy(): string
    {
        return $this->matchBy;
    }

    public function getMatchColumn(): string
    {
        return $this->matchColumn;
    }

    public function getMatchField(): ?string
    {
        return $this->matchField;
    }

    public function getMatchLanguage(): ?string
    {
        return $this->matchLanguage;
    }

    public function getNewObjectsFolder(): ?string
    {
        return $this->newObjectsFolder;
    }

    /**
     * @return list<mixed>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getConfiguration(): ?string
    {
        return $this->configuration;
    }

    /**
     * @return array<mixed>
     */
    public function getConfigurationColumns(): array
    {
        return $this->configurationColumns;
    }

    public function toSettingsInput(): SettingsInput
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
