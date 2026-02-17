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

namespace Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits;

use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
trait ConfigurationPermissionTrait
{
    /**
     * Load and validate a configuration by name, checking the given permission.
     *
     * @throws NotFoundHttpException if the configuration does not exist
     * @throws ForbiddenException if the current user lacks the required permission
     */
    private function loadConfigurationWithPermission(string $name, string $permission): Configuration
    {
        $config = Configuration::getByName($name);

        if (!$config) {
            throw new NotFoundHttpException(
                sprintf('Configuration with name "%s" not found', $name)
            );
        }

        if (!$config->isAllowed($permission)) {
            throw new ForbiddenException(
                sprintf('Access denied to configuration "%s"', $name)
            );
        }

        return $config;
    }
}
