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

use Pimcore\Bundle\DataImporterBundle\Schema\ClassAttributesResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassificationStoreKeyNameResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassificationStoreKeyParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassificationStoreKeysResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\UnitDataResponse;

/**
 * @internal
 */
interface DataTypeServiceInterface
{
    /**
     * @throws \Exception
     */
    public function loadClassAttributes(
        string $classId,
        string|array $transformationResultType,
        bool $includeSystemRead,
        bool $includeSystemWrite,
        bool $loadAdvancedRelations
    ): ClassAttributesResponse;

    /**
     * @throws \Exception
     */
    public function loadClassificationStoreAttributes(string $classId): ClassAttributesResponse;

    /**
     * @throws \Exception
     */
    public function loadClassificationStoreKeys(ClassificationStoreKeyParameters $parameters): ClassificationStoreKeysResponse;

    /**
     * @throws \Exception
     */
    public function loadClassificationStoreKeyName(string $keyId): ClassificationStoreKeyNameResponse;

    public function loadUnitData(): UnitDataResponse;
}
