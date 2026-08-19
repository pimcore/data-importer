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

namespace Pimcore\Bundle\DataImporterBundle\Settings;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;

/**
 * Interface for data targets that support field validation against
 * transformation result types
 */
interface DataTargetFieldValidatorInterface
{
    /**
     * Validate that the configured target field is compatible with the
     * transformation result type
     *
     * @throws InvalidConfigurationException When field is incompatible
     *     with transformation result type
     */
    public function validateTargetField(
        string $transformationResultType,
        string $classId
    ): void;
}
