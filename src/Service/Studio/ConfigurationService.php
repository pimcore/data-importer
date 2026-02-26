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

use Pimcore\Bundle\DataImporterBundle\Hydrator\ConfigurationDetailHydratorInterface;
use Pimcore\Bundle\DataImporterBundle\Schema\ConfigurationDetail;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\ConfigurationPermissionTrait;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ConflictException;

/**
 * @internal
 */
final readonly class ConfigurationService implements ConfigurationServiceInterface
{
    use ConfigurationPermissionTrait;

    public function __construct(
        private ConfigurationDetailHydratorInterface $configurationDetailHydrator
    ) {
    }

    public function getConfiguration(string $name): ConfigurationDetail
    {
        $config = $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ
        );

        return $this->configurationDetailHydrator->hydrate($config);
    }

    public function saveConfiguration(
        string $name,
        array $configuration,
        int $modificationDate
    ): ConfigurationDetail {
        $config = $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_UPDATE
        );

        if ($modificationDate < $config->getModificationDate()) {
            throw new ConflictException(
                'The configuration was modified during editing, '
                . 'please reload the configuration and make your changes again'
            );
        }

        $configuration['general']['active'] = $configuration['general']['active'] ?? false;

        $config->setConfiguration($configuration);
        $config->save();

        return $this->configurationDetailHydrator->hydrate($config);
    }
}
