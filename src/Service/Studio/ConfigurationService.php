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

use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\Hydrator\ConfigurationDetailHydratorInterface;
use Pimcore\Bundle\DataImporterBundle\Schema\ConfigurationDetail;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
final readonly class ConfigurationService implements ConfigurationServiceInterface
{
    public function __construct(
        private ConfigurationDetailHydratorInterface $configurationDetailHydrator
    ) {
    }

    /**
     * @throws \Exception
     */
    public function getConfiguration(string $name): ConfigurationDetail
    {
        $configuration = Configuration::getByName($name);

        if (!$configuration) {
            throw new NotFoundHttpException(
                sprintf('Configuration with name "%s" not found', $name)
            );
        }

        if (!$configuration->isAllowed(PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ)) {
            throw new ForbiddenException(
                sprintf('Access denied to configuration "%s"', $name)
            );
        }

        return $this->configurationDetailHydrator->hydrate($configuration);
    }
}
