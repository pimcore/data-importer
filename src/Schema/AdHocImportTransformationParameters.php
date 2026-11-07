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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\SettingsInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterAdHocImportTransformationParameters',
    title: 'Bundle Data Importer Ad Hoc Import Transformation Parameters',
    required: ['classId', 'column', 'field', 'transformationPipeline'],
    type: 'object'
)]
final readonly class AdHocImportTransformationParameters
{
    // more steps than anyone chains for one column
    private const int MAX_STEPS = 20;

    /**
     * @param list<mixed> $transformationPipeline
     */
    public function __construct(
        #[Property(description: 'The ID of the data object class to import into', type: 'string', example: 'Product')]
        #[Assert\NotBlank]
        private string $classId,
        #[Property(description: 'Data index of the column', type: 'string', example: 'Image')]
        private string $column,
        #[Property(description: 'Key of the field the column goes into', type: 'string', example: 'image')]
        #[Assert\NotBlank]
        private string $field,
        #[Property(
            description: 'The transformations of the column, in order',
            type: 'array',
            items: new Items(
                required: ['type'],
                properties: [
                    new Property(property: 'type', description: 'Operator type', type: 'string', example: 'loadAsset'),
                    new Property(
                        property: 'settings',
                        description: 'The operator\'s settings',
                        type: 'object',
                        example: ['loadStrategy' => 'path']
                    ),
                ],
                type: 'object'
            )
        )]
        #[Assert\Count(max: self::MAX_STEPS)]
        private array $transformationPipeline = [],
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

    public function getClassId(): string
    {
        return $this->classId;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @return list<mixed>
     */
    public function getTransformationPipeline(): array
    {
        return $this->transformationPipeline;
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
