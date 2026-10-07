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

/**
 * Interface for operators that can declare their accepted input
 * types and output type for transformation data type validation
 * and AI/LLM guidance
 */
interface TransformationTypeAwareInterface
{
    /**
     * Get accepted input transformation data types
     *
     * Returns array of transformation data type constants that this
     * operator can accept as input. These correspond to the types
     * defined in TransformationDataTypeService (e.g., 'default',
     * 'numeric', 'array', 'dataObject', etc.)
     */
    public function getAcceptedInputTypes(): array;

    /**
     * Get output transformation data types
     *
     * Returns array of transformation data type constants that this
     * operator can produce as output. Most operators produce a single
     * type, but some may produce multiple types depending on settings.
     * These correspond to types defined in TransformationDataTypeService.
     */
    public function getOutputTypes(): array;
}
