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

namespace Pimcore\Bundle\DataImporterBundle\Controller;

use Pimcore\Bundle\AdminBundle\HttpFoundation\JsonResponse;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\DataLoaderFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\PushLoader;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportPreparationService;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
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
     * @Route("/pimcore-datahub-import/{config}/push", name="data_hub_data_importer_push", methods={"POST"}, requirements={"config"="[\w-]+"})
     *
     * @param Request $request
     * @param ConfigurationPreparationService $configurationLoaderService
     * @param ImportPreparationService $importPreparationService
     *
     * @return JsonResponse
     */
    public function pushAction(Request $request, ConfigurationPreparationService $configurationLoaderService, DataLoaderFactory $dataLoaderFactory, ImportPreparationService $importPreparationService)
    {
        try {
            $configName = $request->get('config');
            $config = $configurationLoaderService->prepareConfiguration($configName);
            $loader = $dataLoaderFactory->loadDataLoader($config['loaderConfig']);

            if (!$loader instanceof PushLoader) {
                return new JsonResponse(['success' => false, 'message' => 'Endpoint not has no Push data source configured.'], 405);
            }

            $this->validateAuthorization($request, $loader);
            $success = $importPreparationService->prepareImport($configName, false, $loader->isIgnoreNotEmptyQueue());

            if ($success) {
                return new JsonResponse(['success' => $success]);
            } else {
                return new JsonResponse(['success' => false, 'message' => 'Import not prepared, see application log for details.'], 405);
            }
        } catch (\Exception $e) {
            Logger::error($e);

            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
