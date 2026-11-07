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
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;

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
}
