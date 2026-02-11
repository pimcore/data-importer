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

use Pimcore\Bundle\DataImporterBundle\Schema\ClassAttributesResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassificationStoreKeyNameResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassificationStoreKeysResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\UnitDataResponse;

/**
 * @internal
 */
interface DataTypeHydratorInterface
{
    public function hydrateClassAttributes(array $attributes): ClassAttributesResponse;

    public function hydrateClassificationStoreKeys(array $data, int $total): ClassificationStoreKeysResponse;

    public function hydrateClassificationStoreKeyName(
        ?string $keyId = null,
        ?string $groupName = null,
        ?string $keyName = null
    ): ClassificationStoreKeyNameResponse;

    public function hydrateUnitData(array $unitList): UnitDataResponse;
}
