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

use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportRunObjectsResponse;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The objects an ended import saved, with the fields its file wrote.
 *
 * @internal
 */
interface AdHocImportRunObjectsServiceInterface
{
    /**
     * @param string $kind updated or created
     *
     * @throws NotFoundHttpException if the user has no ended run of such a file
     * @throws InvalidArgumentException if the kind is neither updated nor created
     */
    public function objects(string $fileId, string $kind, int $page, int $pageSize): AdHocImportRunObjectsResponse;

    /**
     * The same objects as CSV, all of them.
     *
     * @throws NotFoundHttpException if the user has no ended run of such a file
     * @throws InvalidArgumentException if the kind is neither updated nor created
     */
    public function objectsCsv(string $fileId, string $kind): string;
}
