<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Service\Studio;

use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\TransformationResultPreviewsEvent;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\TransformationResultTypeEvent;
use Pimcore\Bundle\DataImporterBundle\Hydrator\TransformationHydratorInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Schema\TransformationResultPreviewsResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\TransformationResultTypeResponse;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\User;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
final readonly class TransformationService implements TransformationServiceInterface
{
    public function __construct(
        private TransformationHydratorInterface $transformationHydrator,
        private PreviewService $previewService,
        private SecurityServiceInterface $securityService,
        private ConfigurationPreparationService $configurationPreparationService,
        private InterpreterFactory $interpreterFactory,
        private MappingConfigurationFactory $mappingConfigurationFactory,
        private ImportProcessingService $importProcessingService,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function loadTransformationResultPreviews(
        string $name,
        ?array $currentConfig,
        int $recordNumber
    ): TransformationResultPreviewsResponse {
        $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ
        );

        $user = $this->resolveCurrentUser();

        $preparedConfig = $this->configurationPreparationService->prepareConfiguration(
            $name,
            $currentConfig
        );

        $previewFilePath = $this->previewService->getLocalPreviewFile($name, $user);
        $importDataRow = [];

        if (is_file($previewFilePath)) {
            $interpreter = $this->interpreterFactory->loadInterpreter(
                $name,
                $preparedConfig['interpreterConfig'],
                $preparedConfig['processingConfig']
            );

            $dataPreview = $interpreter->previewData($previewFilePath, $recordNumber);
            $importDataRow = $dataPreview->getRawData();
        }

        $mapping = $this->mappingConfigurationFactory->loadMappingConfiguration(
            $name,
            $preparedConfig['mappingConfig'],
            true
        );

        $transformationResults = [];
        foreach ($mapping as $mappingConfiguration) {
            $transformationResults[] =
                $this->importProcessingService->generateTransformationResultPreview(
                    $importDataRow,
                    $mappingConfiguration
                );
        }

        $response = $this->transformationHydrator->hydrateResultPreviews($transformationResults);

        $this->eventDispatcher->dispatch(
            new TransformationResultPreviewsEvent($response),
            TransformationResultPreviewsEvent::EVENT_NAME
        );

        return $response;
    }

    public function calculateTransformationResultType(
        string $name,
        array $currentConfig
    ): TransformationResultTypeResponse {
        $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ
        );

        $mappingConfiguration = $this->mappingConfigurationFactory->loadMappingConfigurationItem(
            $name,
            $currentConfig,
            true
        );

        $type = $this->importProcessingService->evaluateTransformationResultDataType(
            $mappingConfiguration
        );

        $response = $this->transformationHydrator->hydrateResultType($type);

        $this->eventDispatcher->dispatch(
            new TransformationResultTypeEvent($response),
            TransformationResultTypeEvent::EVENT_NAME
        );

        return $response;
    }

    /**
     * Load and validate a configuration by name, checking the given permission.
     *
     * @throws NotFoundHttpException if the configuration does not exist
     * @throws ForbiddenException if the current user lacks the required permission
     */
    private function loadConfigurationWithPermission(string $name, string $permission): Configuration
    {
        $config = Configuration::getByName($name);

        if (!$config) {
            throw new NotFoundHttpException(
                sprintf('Configuration with name "%s" not found', $name)
            );
        }

        if (!$config->isAllowed($permission)) {
            throw new ForbiddenException(
                sprintf('Access denied to configuration "%s"', $name)
            );
        }

        return $config;
    }

    /**
     * Resolve the current user, ensuring it is a Pimcore User instance.
     *
     * @throws EnvironmentException if the current user cannot be resolved
     */
    private function resolveCurrentUser(): User
    {
        $user = $this->securityService->getCurrentUser();

        if (!$user instanceof User) {
            throw new EnvironmentException('Could not resolve current user');
        }

        return $user;
    }
}
