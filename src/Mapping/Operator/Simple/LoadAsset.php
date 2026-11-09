<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplicationScopeAwareTrait;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceLoadStrategy;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceQuery;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceType;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Model\Asset;

/**
 * @internal
 */
final class LoadAsset extends AbstractAssetOperator
{
    use MappingApplicationScopeAwareTrait;

    private const LOAD_STRATEGY_ID = 'id';

    private const LOAD_STRATEGY_PATH = 'path';

    private string $loadStrategy;

    public function setSettings(array $settings): void
    {
        $this->loadStrategy = $settings['loadStrategy'] ?? self::LOAD_STRATEGY_PATH;
    }

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return array|false|mixed|null
     *
     * @throws InvalidConfigurationException
     */
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
            $cleanData = trim($data);

            $referencedAsset = $this->lookupAsset($cleanData);
            if ($referencedAsset !== null) {
                $assets[] = $referencedAsset;

                continue;
            }

            if ($this->loadStrategy === self::LOAD_STRATEGY_PATH) {
                $asset = Asset::getByPath($cleanData);
            } elseif ($this->loadStrategy === self::LOAD_STRATEGY_ID) {
                if (is_numeric($cleanData)) {
                    $asset = Asset::getById((int)$cleanData);
                }
            } else {
                throw new InvalidConfigurationException("Unknown load strategy '{ $this->loadStrategy }'");
            }

            if ($asset instanceof Asset) {
                $assets[] = $asset;
            } elseif (!$dryRun && !empty($data)) {
                $logMessage = "Could not load asset from `$data`";
                if (!$this->reportWarningIfAppliedWithoutSaving($logMessage)) {
                    $this->applicationLogger->warning($logMessage . ' ', [
                        'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
                    ]);
                }
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

    private function lookupAsset(string $key): ?Asset
    {
        $loadStrategy = ReferenceLoadStrategy::tryFrom($this->loadStrategy);
        if ($key === '' || $loadStrategy === null || !$this->hasReferenceLookup()) {
            return null;
        }

        return $this->lookupReference(new ReferenceQuery(ReferenceType::Asset, $loadStrategy, $key), Asset::class);
    }
}
