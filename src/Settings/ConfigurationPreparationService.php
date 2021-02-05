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




        $loaderConfig = [
            'type' => 'asset',
            'settings' => [
                'assetPath' => '/upload/import.csv'
            ]
        ];

        $interpreterConfig = [
            'type' => 'csv',
            'settings' => [
                'skipFirstRow' => true
            ],
            'executionType' => ImportProcessingService::EXECUTION_TYPE_PARALLEL,
            'doArchiveImportFile' => false,
            'idDataIndex' => 0,
            'doDeltaCheck' => true,
            'cleanup' => [
                'doCleanup' => false,
                'strategy' => 'unpublish'
            ]

        ];

        $resolverConfig = [
            'elementType' => 'dataObject',
            'dataObjectClassId' => 'portaluser',
            'loadingStrategy' => [
                'type' => 'id',
                'settings' => [
                    'dataSourceIndex' => 0
                ]
            ],
            'createLocationStrategy' => [
                'type' => 'staticPath',
                'settings' => [
                    'path' => '/newimport'
                ]
            ],
            'locationUpdateStrategy' => [
                'type' => 'noChange',
                'settings' => [

                ]
            ],
            'publishingStrategy' => [
                'type' => 'alwaysPublish',
                'settings' => [

                ]
            ]
        ];

        $mappingConfig = [
            [
                'label' => 'Firstname',
                'dataSourceIndex' => 1,
                'transformationPipeline' => [
                    [
                        'type' => 'trim',
                        'settings' => [
                            'mode' => 'both'
                        ]
                    ]
                ],
                'dataTarget' => [
                    'type' => 'direct',
                    'settings' => [
                        'fieldName' => 'firstname',
                        'language' => null
                    ]
                ]
            ],
            [
                'label' => 'Lastname',
                'dataSourceIndex' => 2,
                'transformationPipeline' => [
//                    [
//                        'type' => 'trim',
//                        'settings' => [
//                            'mode' => 'both'
//                        ]
//                    ]
                ],
                'dataTarget' => [
                    'type' => 'direct',
                    'settings' => [
                        'fieldName' => 'lastname',
                        'language' => null
                    ]
                ]
            ]
        ];


        return [
            'loaderConfig' => $loaderConfig,
            'interpreterConfig' => $interpreterConfig,
            'resolverConfig' => $resolverConfig,
            'mappingConfig' => $mappingConfig
        ];

    }

}
