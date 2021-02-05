<?php

namespace Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator\Simple;

use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataHubBatchImportBundle\PimcoreDataHubBatchImportBundle;
use Pimcore\Log\FileObject;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\Service;

class LoadAsset extends ImportAsset
{

    CONST LOAD_STRATEGY_ID = 'id';
    CONST LOAD_STRATEGY_PATH = 'path';

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
        if(!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }

        $assets = [];

        foreach($inputData as $data) {

            $asset = null;
            if($this->loadStrategy === self::LOAD_STRATEGY_PATH) {
                $asset = Asset::getByPath(trim($data));
            } else if($this->loadStrategy === self::LOAD_STRATEGY_ID) {
                $asset = Asset::getById(trim($data));
            } else {
                throw new InvalidConfigurationException("Unknown load strategy '{ $this->loadStrategy }'");
            }

            if(empty($asset) && !$dryRun) {
                $this->applicationLogger->warning("Could not load asset from `$data` ", [
                    'component' => PimcoreDataHubBatchImportBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
                ]);
            } else {
                $assets[] = $asset;
            }
        }

        if($returnScalar) {
            return reset($assets);
        } else {
            return $assets;
        }

    }

}
