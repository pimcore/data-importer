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

namespace Pimcore\Bundle\DataImporterBundle\Hydrator;

use Pimcore\Bundle\DataImporterBundle\Schema\TransformationResultPreviewsResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\TransformationResultTypeResponse;

/**
 * @internal
 */
final readonly class TransformationHydrator implements TransformationHydratorInterface
{
    public function hydrateResultPreviews(array $transformationResults): TransformationResultPreviewsResponse
    {
        return new TransformationResultPreviewsResponse($transformationResults);
    }

    public function hydrateResultType(string $type): TransformationResultTypeResponse
    {
        return new TransformationResultTypeResponse($type);
    }
}
