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

use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\ConfigurationTypes;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;

/**
 * @internal
 */
final class ImportConfigurationRepository implements ImportConfigurationRepositoryInterface
{
    public function findReadable(): array
    {
        $configurations = [];
        foreach (Configuration::getList() as $configuration) {
            if ($this->isReadableImportConfiguration($configuration)) {
                $configurations[] = $configuration;
            }
        }

        return $configurations;
    }

    public function findReadableByName(string $name): ?Configuration
    {
        $configuration = Configuration::getByName($name);

        return $this->isReadableImportConfiguration($configuration) ? $configuration : null;
    }

    private function isReadableImportConfiguration(?Configuration $configuration): bool
    {
        return $configuration instanceof Configuration
            && $configuration->getType() === ConfigurationTypes::DATA_IMPORTER_DATA_OBJECT
            && $configuration->isAllowed(PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ);
    }
}
