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

use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportMappingResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassificationStoreKeysResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\NotFoundException;

/**
 * @internal
 */
interface AdHocImportMappingServiceInterface
{
    /**
     * The fields the columns can be imported into, and a suggested field per column.
     *
     * @throws ForbiddenException if the user may not create objects of that class
     */
    public function loadMapping(MappingSuggestionParameters $parameters): AdHocImportMappingResponse;

    /**
     * The keys of a classification store of the class a column can be imported into, a page at a time.
     *
     * @throws ForbiddenException if the user may not create objects of that class
     * @throws NotFoundException if the class has no classification store of that name
     */
    public function loadClassificationStoreKeys(
        string $classId,
        string $fieldName,
        ?string $search,
        int $start,
        int $limit
    ): ClassificationStoreKeysResponse;
}
