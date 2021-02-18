<?php

namespace Pimcore\Bundle\DataHubBatchImportBundle\Controller;


use Pimcore\Bundle\AdminBundle\HttpFoundation\JsonResponse;
use Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Loader\DataLoaderFactory;
use Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Loader\PushLoader;
use Pimcore\Bundle\DataHubBatchImportBundle\Processing\ImportPreparationService;
use Pimcore\Bundle\DataHubBatchImportBundle\Settings\ConfigurationPreparationService;
use Pimcore\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class PushImportController
{

    protected function validateAuthorization(Request $request, PushLoader $loader)
    {
        if ($request->headers->has('authorization') === false) {
            throw new AccessDeniedHttpException('Missing authorization');
        }

        $header = $request->headers->get('authorization');

        $token = trim((string) preg_replace('/^(?:\s+)?Bearer\s/', '', $header));

        if (trim($token) !== trim($loader->getApiKey())) {
            throw new AccessDeniedHttpException('Invalid token');
        }
    }

    /**
     * @Route("/pimcore-datahub-import/{config}/push", name="data_hub_batch_import_push", requirements={"config"="[\w-]+"})
     *
     * @param Request $request
     * @param ConfigurationPreparationService $configurationLoaderService
     * @param ImportPreparationService $importPreparationService
     * @return JsonResponse
     */
    public function pushAction(Request $request, ConfigurationPreparationService $configurationLoaderService, DataLoaderFactory $dataLoaderFactory, ImportPreparationService $importPreparationService) {

        try {
            $configName = $request->get('config');
            $config = $configurationLoaderService->prepareConfiguration($configName);
            $loader = $dataLoaderFactory->loadDataLoader($config['loaderConfig']);

            if(!$loader instanceof PushLoader) {
                return new JsonResponse(['success' => false, 'message' => 'Endpoint not has no Push data source configured.'], 405);
            }

            $this->validateAuthorization($request, $loader);
            $success = $importPreparationService->prepareImport($configName, false, $loader->isIgnoreNotEmptyQueue());

            if($success) {
                return new JsonResponse(['success' => $success]);
            } else {
                return new JsonResponse(['success' => false, 'message' => 'Import not prepared, see application log for details.'], 405);
            }

        } catch (\Exception $e) {
            echo $e; die();
            Logger::error($e);
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }

    }

}
