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

use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportConfigurationsResponse;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;

/**
 * @internal
 */
interface AdHocImportConfigurationServiceInterface
{
    /**
     * The data object import configurations the user may read, each with the class, match and folder an ad hoc
     * import takes from it, or why it cannot.
     *
     * @throws EnvironmentException if the current user cannot be resolved
     */
    public function listConfigurations(): AdHocImportConfigurationsResponse;
}
