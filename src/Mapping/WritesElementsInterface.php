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

namespace Pimcore\Bundle\DataImporterBundle\Mapping;

use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplier;

/**
 * Marks an operator or data target that saves, creates or deletes elements (including folders) while a mapping is
 * processed. A mapping containing one cannot be applied without saving, see MappingApplier.
 *
 * @see MappingApplier::lint()
 */
interface WritesElementsInterface
{
}
