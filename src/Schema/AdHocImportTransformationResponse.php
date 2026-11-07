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

/**
 * @phpstan-type Step array{
 *     type: string,
 *     resultType: string|null,
 *     error: string|null,
 *     values: list<AdHocImportTransformationCell>
 * }
 *
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterAdHocImportTransformationResponse',
    title: 'Bundle Data Importer Ad Hoc Import Transformation Response',
    required: ['values', 'steps', 'conversion', 'resultType', 'fits', 'results', 'offered'],
    type: 'object'
)]
final readonly class AdHocImportTransformationResponse
{
    private const string CELLS = 'One value per row of the first rows';

    /**
     * @param list<AdHocImportTransformationCell> $values
     * @param list<Step> $steps
     * @param list<AdHocImportTransformationCell> $results
     * @param list<string> $offered
     */
    public function __construct(
        #[Property(
            description: 'The column\'s values in the first rows of the file',
            type: 'array',
            items: new Items(ref: AdHocImportTransformationCell::class)
        )]
        private array $values,
        #[Property(
            description: 'Each transformation with the values after it',
            type: 'array',
            items: new Items(
                required: ['type', 'resultType', 'error', 'values'],
                properties: [
                    new Property(property: 'type', description: 'Operator type', type: 'string', example: 'trim'),
                    new Property(
                        property: 'resultType',
                        description: 'Transformation result type after the step; '
                            . 'null once a step cannot take its input',
                        type: 'string',
                        example: 'default',
                        nullable: true
                    ),
                    new Property(
                        property: 'error',
                        description: 'Why the step cannot take the result of the steps before it',
                        type: 'string',
                        nullable: true
                    ),
                    new Property(
                        property: 'values',
                        description: self::CELLS,
                        type: 'array',
                        items: new Items(ref: AdHocImportTransformationCell::class)
                    ),
                ],
                type: 'object'
            )
        )]
        private array $steps,
        #[Property(
            description: 'The operator added to convert the result to the field\'s type; null when it fits as it is',
            type: 'string',
            example: 'date',
            nullable: true
        )]
        private ?string $conversion,
        #[Property(
            description: 'Transformation result type that goes into the field; null once a step cannot take its input',
            type: 'string',
            example: 'date',
            nullable: true
        )]
        private ?string $resultType,
        #[Property(description: 'Whether the result can go into the field', type: 'boolean', example: true)]
        private bool $fits,
        #[Property(
            description: 'The values that go into the field, converted',
            type: 'array',
            items: new Items(ref: AdHocImportTransformationCell::class)
        )]
        private array $results,
        #[Property(
            description: 'The operator types that can be added next and still lead to the field',
            type: 'array',
            items: new Items(type: 'string', example: 'loadAsset')
        )]
        private array $offered,
    ) {
    }

    /**
     * @return list<AdHocImportTransformationCell>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * @return list<Step>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    public function getConversion(): ?string
    {
        return $this->conversion;
    }

    public function getResultType(): ?string
    {
        return $this->resultType;
    }

    public function getFits(): bool
    {
        return $this->fits;
    }

    /**
     * @return list<AdHocImportTransformationCell>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @return list<string>
     */
    public function getOffered(): array
    {
        return $this->offered;
    }
}
