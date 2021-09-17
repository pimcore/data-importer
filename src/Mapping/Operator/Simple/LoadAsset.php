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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Model\Asset;

class LoadAsset extends ImportAsset
{
    const LOAD_STRATEGY_ID = 'id';
    const LOAD_STRATEGY_PATH = 'path';

    /**
     * @var string
     */
    protected $loadStrategy;

    public function setSettings(array $settings): void
    {
        $this->loadStrategy = $settings['loadStrategy'] ?? self::LOAD_STRATEGY_PATH;
    }

    public function process($inputData, bool $dryRun = false)
    {
        $returnScalar = false;
        if (!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }

        $assets = [];

        foreach ($inputData as $data) {
            $asset = null;
            if ($this->loadStrategy === self::LOAD_STRATEGY_PATH) {
                $asset = Asset::getByPath(trim($data));
            } elseif ($this->loadStrategy === self::LOAD_STRATEGY_ID) {
                $asset = Asset::getById(trim($data));
            } else {
                throw new InvalidConfigurationException("Unknown load strategy '{ $this->loadStrategy }'");
            }

            if ($asset instanceof Asset) {
                $assets[] = $asset;
            } elseif (!$dryRun && !empty($data)) {
                $this->applicationLogger->warning("Could not load asset from `$data` ", [
                    'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
                ]);
            }
        }

        if ($returnScalar) {
            if (!empty($assets)) {
                return reset($assets);
            }

            return null;
        } else {
            return $assets;
        }
    }
}
