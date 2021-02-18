<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Settings;

use Pimcore\Bundle\DataHubBatchImportBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataHubBundle\Configuration\Dao;

class ConfigurationPreparationService
{

    public function prepareConfiguration(string $configName, $currentConfig = null) {

        if($currentConfig) {
            if(is_string($currentConfig)) {
                $currentConfig = json_decode($currentConfig, true);
            }
            $config = $currentConfig;
        } else {
            $configuration = Dao::getByName($configName);
            if (!$configuration) {
                throw new \Exception('Configuration ' . $configName . ' does not exist.');
            }

            $config = $configuration->getConfiguration();
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
