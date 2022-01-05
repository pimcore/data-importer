<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\Settings;

use Pimcore\Bundle\DataHubBundle\Configuration\Dao;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ConfigurationPreparationService
{
    public function prepareConfiguration(string $configName, $currentConfig = null)
    {
        if ($currentConfig) {
            if (is_string($currentConfig)) {
                $currentConfig = json_decode($currentConfig, true);
            }
            $config = $currentConfig;
        } else {
            $configuration = Dao::getByName($configName);
            if (!$configuration) {
                throw new \Exception('Configuration ' . $configName . ' does not exist.');
            }
            if (!$configuration->isAllowed('read')) {
                throw new AccessDeniedHttpException('Access denied');
            }

            $config = $configuration->getConfiguration();
            $config['userPermissions'] = [
                'update' => $configuration->isAllowed('update'),
                'delete' => $configuration->isAllowed('delete')
            ];
        }

        //init config array with default values
        $config = array_merge([
            'loaderConfig' => [],
            'interpreterConfig' => [],
            'resolverConfig' => [
                'loadingStrategy' => [],
                'createLocationStrategy' => [],
                'locationUpdateStrategy' => [],
                'publishingStrategy' => []
            ],
            'processingConfig' => [],
            'mappingConfig' => [],
            'executionConfig' => []
        ], $config);

        return $config;
    }
}
