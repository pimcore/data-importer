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
    schema: 'BundleDataImporterTransformationResultPreviewsResponse',
    title: 'Bundle Data Importer Transformation Result Previews Response',
    required: ['transformationResultPreviews'],
    type: 'object'
)]
final class TransformationResultPreviewsResponse implements AdditionalAttributesInterface
{
    use AdditionalAttributesTrait;

    public function __construct(
        #[Property(
            description: 'Transformation result preview strings for each mapping entry',
            type: 'array',
            items: new Items(type: 'string')
        )]
        private readonly array $transformationResultPreviews,
    ) {
    }

    public function getTransformationResultPreviews(): array
    {
        return $this->transformationResultPreviews;
    }
}
