<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

namespace Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator\Simple;

use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataHubBatchImportBundle\PimcoreDataHubBatchImportBundle;
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

            if (empty($asset) && !$dryRun) {
                $this->applicationLogger->warning("Could not load asset from `$data` ", [
                    'component' => PimcoreDataHubBatchImportBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
                ]);
            } else {
                $assets[] = $asset;
            }
        }

        if ($returnScalar) {
            return reset($assets);
        } else {
            return $assets;
        }
    }
}
