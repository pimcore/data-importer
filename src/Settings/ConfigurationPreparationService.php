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

use Pimcore\Bundle\DataHubBundle\Configuration;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ConfigurationPreparationService
{
    /**
     * @param string $configName
     * @param string|array|null $currentConfig
     * @param bool $ignorePermissions
     *
     * @return array
     *
     * @throws \Exception
     */
    public function prepareConfiguration(string $configName, $currentConfig = null, $ignorePermissions = false)
    {
        if ($currentConfig) {
            if (is_string($currentConfig)) {
                $currentConfig = json_decode($currentConfig, true);
            }
            $config = $currentConfig;
        } else {
            $configuration = Configuration::getByName($configName);
            if (!$configuration) {
                throw new NotFoundHttpException(
                    sprintf(
                        'Configuration with name %s not found',
                        $configName
                    )
                );
            }

            $config = $configuration->getConfiguration();
            if (!$ignorePermissions) {
                if (!$configuration->isAllowed('read')) {
                    throw new AccessDeniedHttpException('Access denied');
                }

                $config['userPermissions'] = [
                    'update' => $configuration->isAllowed('update'),
                    'delete' => $configuration->isAllowed('delete')
                ];
            }
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
