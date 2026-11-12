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
    schema: 'BundleDataImporterClassificationStoreKeyNameResponse',
    title: 'Bundle Data Importer Classification Store Key Name Response',
    type: 'object'
)]
final class ClassificationStoreKeyNameResponse implements AdditionalAttributesInterface
{
    use AdditionalAttributesTrait;

    public function __construct(
        #[Property(
            description: 'The key reference, as it was asked for when the key could not be resolved',
            type: 'string',
            example: '1-5',
            nullable: true
        )]
        private readonly ?string $keyId = null,
        #[Property(description: 'The group name', type: 'string', example: 'Dimensions', nullable: true)]
        private readonly ?string $groupName = null,
        #[Property(description: 'The key name', type: 'string', example: 'Width', nullable: true)]
        private readonly ?string $keyName = null,
        #[Property(
            description: 'The variant names the key declares',
            type: 'array',
            items: new Items(type: 'string'),
            example: ['metric', 'imperial']
        )]
        private readonly array $variants = [],
        #[Property(
            description: 'Whether the key is kept per language in the field it was asked for',
            type: 'boolean',
            nullable: true
        )]
        private readonly ?bool $localized = null,
    ) {
    }

    public function getKeyId(): ?string
    {
        return $this->keyId;
    }

    public function getGroupName(): ?string
    {
        return $this->groupName;
    }

    public function getKeyName(): ?string
    {
        return $this->keyName;
    }

    public function getVariants(): array
    {
        return $this->variants;
    }

    public function getLocalized(): ?bool
    {
        return $this->localized;
    }
}
