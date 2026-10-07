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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Apply;

/**
 * Load strategy configured on the reference operator. Values match the operator's `loadStrategy` setting.
 */
enum ReferenceLoadStrategy: string
{
    case Id = 'id';
    case Path = 'path';
    case Attribute = 'attribute';
}
