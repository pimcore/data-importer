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

namespace Pimcore\Bundle\DataImporterBundle\Service\Studio;

use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportTransformationParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportTransformationResponse;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
interface AdHocImportTransformationServiceInterface
{
    /**
     * The transformations of one column run on its values in the first rows, converted to the field's type.
     *
     * @throws ForbiddenException if the user may not create objects of that class
     * @throws NotFoundHttpException if the user has no such file, or the file no such column or the class no such field
     * @throws InvalidArgumentException for a transformation the ad hoc import does not offer
     */
    public function previewTransformations(
        string $fileId,
        AdHocImportTransformationParameters $parameters
    ): AdHocImportTransformationResponse;
}
