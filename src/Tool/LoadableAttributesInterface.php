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

namespace Pimcore\Bundle\DataImporterBundle\Tool;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;

/**
 * Which attributes a data object can be looked up by.
 *
 * One rule, asked two ways: assert it for a configured attribute, list it for a caller that has
 * to offer a choice. Keeping them on one interface is what stops the MCP tool and the validator
 * from disagreeing about what "loadable" means.
 *
 * @internal
 */
interface LoadableAttributesInterface
{
    /**
     * @throws InvalidConfigurationException when the attribute cannot be used to load an object
     */
    public function assertAttributeLoadable(string $classId, string $attributeName): void;

    /**
     * @return list<string> empty when the class does not exist
     */
    public function listLoadableAttributes(string $classId): array;
}
