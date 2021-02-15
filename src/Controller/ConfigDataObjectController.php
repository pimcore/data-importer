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

namespace Pimcore\Bundle\DataHubBatchImportBundle\Controller;

use Cron\CronExpression;
use Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataHubBatchImportBundle\Processing\ImportPreparationService;
use Pimcore\Bundle\DataHubBatchImportBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataHubBatchImportBundle\Settings\ConfigurationPreparationService;
use Pimcore\Bundle\DataHubBundle\Configuration\Dao;
use Pimcore\Bundle\DataHubSimpleRestBundle\Service\IndexService;
use Pimcore\File;
use Pimcore\Logger;
use Pimcore\Translation\Translator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/pimcoredatahubbatchimport/dataobject/config")
 */
class ConfigDataObjectController extends \Pimcore\Bundle\AdminBundle\Controller\AdminController
{
    public const CONFIG_NAME = 'plugin_datahub_config';

    /**
     * @Route("/save")
     *
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws \Exception
     *
     */
    public function saveAction(Request $request, IndexService $indexService): ?JsonResponse
    {
        $this->checkPermission(self::CONFIG_NAME);

        try {
            $data = $request->get('data');
            $modificationDate = $request->get('modificationDate', 0);

            if ($modificationDate < Dao::getConfigModificationDate()) {
                throw new \Exception('The configuration was modified during editing, please reload the configuration and make your changes again');
            }

            $dataDecoded = json_decode($data, true);

            $name = $dataDecoded['general']['name'];
            $dataDecoded['general']['active'] = $dataDecoded['general']['active'] ?? false;
            $config = Dao::getByName($name);

            $oldConfig = $config->getConfiguration();

            $config->setConfiguration($dataDecoded);
            $config->save();

            return $this->json(['success' => true, 'modificationDate' => Dao::getConfigModificationDate()]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }


    protected function loadAvailableColumnHeaders(
        string $configName,
        array $config,
        InterpreterFactory $interpreterFactory
    ) {
        $previewFilePath = $this->getPreviewFilePath($configName);
        if(is_file($previewFilePath)) {
            try {
                $interpreter = $interpreterFactory->loadInterpreter($configName, $config['interpreterConfig'], $config['processingConfig']);
                $dataPreview = $interpreter->previewData($previewFilePath);
                return $dataPreview->getDataColumnHeaders();
            } catch (\Exception $e) {
                Logger::warning($e);
            }
        }
        return [];
    }

    /**
     * @Route("/get")
     *
     * @param Request $request
     * @param ConfigurationPreparationService $configurationPreparationService
     * @param InterpreterFactory $interpreterFactory
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function getAction(Request $request,
        ConfigurationPreparationService $configurationPreparationService,
        InterpreterFactory $interpreterFactory
    ): JsonResponse
    {
        $this->checkPermission(self::CONFIG_NAME);

        $name = $request->get('name');
        $config = $configurationPreparationService->prepareConfiguration($name);

        return new JsonResponse(
            [
                'name' => $name,
                'configuration' => $config,
                'modificationDate' => Dao::getConfigModificationDate(),
                'columnHeaders' => $this->loadAvailableColumnHeaders($name, $config, $interpreterFactory)
            ]
        );
    }

    /**
     * @param string $configName
     * @return string
     * @throws \Exception
     */
    protected function getPreviewFilePath(string $configName): string {
        $user = $this->getAdminUser();

        $configuration = Dao::getByName($configName);
        if (!$configuration) {
            throw new \Exception('Configuration ' . $configName . ' does not exist.');
        }

        $filePath = PIMCORE_PRIVATE_VAR . '/tmp/datahub/batchimport/' . $configuration->getName() . '/' . $user->getId() . '.import';
        return $filePath;
    }

    /**
     * @Route("/upload-preview", methods={"POST"})
     *
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function uploadPreviewDataAction(Request $request) {

        try {
            if (array_key_exists('Filedata', $_FILES)) {
                $filename = $_FILES['Filedata']['name'];
                $sourcePath = $_FILES['Filedata']['tmp_name'];
            } else {
                throw new \Exception('The filename of the preview data is empty');
            }

            if (is_file($sourcePath) && filesize($sourcePath) < 1) {
                throw new \Exception('File is empty!');
            } elseif (!is_file($sourcePath)) {
                throw new \Exception('Something went wrong, please check upload_max_filesize and post_max_size in your php.ini and write permissions of ' . PIMCORE_PUBLIC_VAR);
            }

            if(filesize($sourcePath) > 10485760) { //10 MB
                throw new \Exception('File it too big for preview file, please create a smaller one');
            }

            $target = $this->getPreviewFilePath($request->get('config_name'));
            File::put($target, file_get_contents($sourcePath));

            @unlink($sourcePath);

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            Logger::error($e);
            return $this->adminJson([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }

    }

    /**
     * @Route("/load-preview-data", methods={"POST"})
     *
     * @param Request $request
     * @param ConfigurationPreparationService $configurationPreparationService
     * @param InterpreterFactory $interpreterFactory
     * @param Translator $translator
     * @return JsonResponse
     * @throws \Exception
     */
    public function loadDataPreviewAction(
        Request $request,
        ConfigurationPreparationService $configurationPreparationService,
        InterpreterFactory $interpreterFactory,
        Translator $translator
    ) {

        $configName = $request->get('config_name');
        $currentConfig = $request->get('current_config');
        $recordNumber = intval($request->get('record_number'));

        $dataPreview = null;
        $hasData = false;
        $errorMessage = '';
        $previewFilePath = $this->getPreviewFilePath($configName);
        if(is_file($previewFilePath)) {
            $config = $configurationPreparationService->prepareConfiguration($configName, $currentConfig);

            $mappedColumns = [];
            foreach(($config['mappingConfig'] ?? []) as $mapping) {
                $mappedColumns = array_merge($mappedColumns, ($mapping['dataSourceIndex'] ?? []));
            }
            $mappedColumns = array_unique($mappedColumns);

            try {
                $interpreter = $interpreterFactory->loadInterpreter($configName, $config['interpreterConfig'], $config['processingConfig']);

                if($interpreter->fileValid($previewFilePath)) {
                    $dataPreview = $interpreter->previewData($previewFilePath, $recordNumber, $mappedColumns);
                    $hasData = true;
                } else {
                    $errorMessage = $translator->trans('plugin_pimcore_datahub_batch_import_configpanel_preview_error_invalid_file', [], 'admin');
                }

            } catch (\Exception $e) {
                Logger::error($e);
                $errorMessage = $translator->trans('plugin_pimcore_datahub_batch_import_configpanel_preview_error_prefix', [], 'admin') . ': ' . $e->getMessage();
            }
        }

        return new JsonResponse([
            'dataPreview' => $dataPreview ? $dataPreview->getDataPreview() : [],
            'previewRecordIndex' => $dataPreview ? $dataPreview->getRecordNumber() : 0,
            'hasData' => $hasData,
            'errorMessage' => $errorMessage
        ]);
    }

    /**
     * @Route("/load-column-headers", methods={"POST"})
     *
     * @param Request $request
     * @param ConfigurationPreparationService $configurationPreparationService
     * @param InterpreterFactory $interpreterFactory
     * @return JsonResponse
     * @throws \Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException
     */
    public function loadAvailableColumnHeadersAction(
        Request $request,
        ConfigurationPreparationService $configurationPreparationService,
        InterpreterFactory $interpreterFactory
    ) {
        $configName = $request->get('config_name');
        $currentConfig = $request->get('current_config');
        $config = $configurationPreparationService->prepareConfiguration($configName, $currentConfig);

        return new JsonResponse([
            'columnHeaders' => $this->loadAvailableColumnHeaders($configName, $config, $interpreterFactory)
        ]);
    }

    /**
     * @Route("/load-transformation-result", methods={"POST"})
     *
     * @param Request $request
     * @param ConfigurationPreparationService $configurationPreparationService
     * @param MappingConfigurationFactory $factory
     * @param InterpreterFactory $interpreterFactory
     * @param ImportProcessingService $importProcessingService
     * @return JsonResponse
     * @throws InvalidConfigurationException
     */
    public function loadTransformationResultPreviewsAction(
        Request $request,
        ConfigurationPreparationService $configurationPreparationService,
        MappingConfigurationFactory $factory,
        InterpreterFactory $interpreterFactory,
        ImportProcessingService $importProcessingService
    ) {

        $configName = $request->get('config_name');
        $currentConfig = $request->get('current_config');
        $recordNumber = intval($request->get('current_preview_record'));

        $config = $configurationPreparationService->prepareConfiguration($configName, $currentConfig);

        $previewFilePath = $this->getPreviewFilePath($configName);
        $importDataRow = [];
        $transformationResults = [];
        $errorMessage = '';

        try {
            if(is_file($previewFilePath)) {
                $interpreter = $interpreterFactory->loadInterpreter($configName, $config['interpreterConfig'], $config['processingConfig']);

                $dataPreview = $interpreter->previewData($previewFilePath, $recordNumber);
                $importDataRow = $dataPreview->getRawData();
            }


            $mapping = $factory->loadMappingConfiguration($configName, $config['mappingConfig'], true);



            foreach($mapping as $index => $mappingConfiguration) {
                $transformationResults[] = $importProcessingService->generateTransformationResultPreview($importDataRow, $mappingConfiguration);
            }

        } catch (\Exception $e) {
            Logger::error($e);
            $errorMessage = $e->getMessage();
        }

        return new JsonResponse([
            'transformationResultPreviews' => $transformationResults,
            'errorMessage' => $errorMessage
        ]);

    }

    /**
     * @Route("/calculate-transformation-result-type", methods={"POST"})
     *
     * @param Request $request
     * @param MappingConfigurationFactory $factory
     * @param ImportProcessingService $importProcessingService
     * @return JsonResponse
     * @throws \Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException
     */
    public function calculateTransformationResultTypeAction(
        Request $request,
        MappingConfigurationFactory $factory,
        ImportProcessingService $importProcessingService
    ) {
        try {
            $currentConfig = json_decode($request->get('current_config'), true);
            $configName = $request->get('config_name');
            $mappingConfiguration = $factory->loadMappingConfigurationItem($configName, $currentConfig, true);
            return new JsonResponse($importProcessingService->evaluateTransformationResultDataType($mappingConfiguration));
        } catch (InvalidConfigurationException $e) {
            return new JsonResponse('ERROR: ' . $e->getMessage());
        }

    }

    /**
     * @Route("/load-class-attributes", methods={"GET"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function loadDataObjectAttributesAction(Request $request, TransformationDataTypeService $transformationDataTypeService) {

        $classId = $request->get('class_id');
        if(empty($classId)) {
            return new JsonResponse([]);
        }

        $includeSystemRead = boolval($request->get('system_read', false));
        $includeSystemWrite = boolval($request->get('system_write', false));

        $transformationTargetType = $request->get('transformation_result_type', [TransformationDataTypeService::DEFAULT_TYPE, TransformationDataTypeService::NUMERIC]);
        return new JsonResponse([
            'attributes' => $transformationDataTypeService->getPimcoreDataTypes($classId, $transformationTargetType, $includeSystemRead, $includeSystemWrite)
        ]);

    }

    /**
     * @Route("/start-import", methods={"PUT"})
     *
     * @param Request $request
     * @param ImportPreparationService $importPreparationService
     * @return JsonResponse
     */
    public function startBatchImportAction(Request $request, ImportPreparationService $importPreparationService) {

        $configName = $request->get('config_name');
        $success = $importPreparationService->prepareImport($configName, true);

        return new JsonResponse([
            'success' => $success
        ]);

    }

    /**
     * @Route("/check-import-progress", methods={"GET"})
     *
     * @param Request $request
     * @param ImportProcessingService $importProcessingService
     * @return JsonResponse
     */
    public function checkImportProgressAction(Request $request, ImportProcessingService $importProcessingService) {
        $configName = $request->get('config_name');
        return new JsonResponse($importProcessingService->getImportStatus($configName));
    }

    /**
     * @Route("/check-crontab", methods={"GET"})
     * @param Request $request
     * @return JsonResponse
     */
    public function isCronExpressionValidAction(Request $request) {

        $message = '';
        $success = true;
        $cronExpression = $request->get('cron_expression');
        if(!empty($cronExpression)) {
            try {
                new CronExpression($cronExpression);
            } catch (\Exception $e) {
                $success = false;
                $message = $e->getMessage();
            }
        }

        return new JsonResponse([
            'success' => $success,
            'message' => $message
        ]);

    }


    /**
     * @Route("/cancel-execution", methods={"PUT"})
     * @param Request $request
     * @param ImportProcessingService $importProcessingService
     * @return JsonResponse
     * @throws \Doctrine\DBAL\DBALException
     */
    public function cancelExecutionAction(Request $request, ImportProcessingService $importProcessingService) {
        $configName = $request->get('config_name');
        $importProcessingService->cancelImportAndCleanupQueue($configName);
        return new JsonResponse([
            'success' => true
        ]);
    }

}
