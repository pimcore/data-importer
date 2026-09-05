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

use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\TransformationResultPreviewsEvent;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\TransformationResultTypeEvent;
use Pimcore\Bundle\DataImporterBundle\Hydrator\TransformationHydratorInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewEventApplier;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Schema\TransformationResultPreviewsResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\TransformationResultTypeResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\ConfigurationPermissionTrait;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CurrentUserResolverTrait;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final readonly class TransformationService implements TransformationServiceInterface
{
    use ConfigurationPermissionTrait;
    use CurrentUserResolverTrait;

    public function __construct(
        private TransformationHydratorInterface $transformationHydrator,
        private PreviewService $previewService,
        private SecurityServiceInterface $securityService,
        private ConfigurationPreparationService $configurationPreparationService,
        private InterpreterFactory $interpreterFactory,
        private MappingConfigurationFactory $mappingConfigurationFactory,
        private ImportProcessingService $importProcessingService,
        private EventDispatcherInterface $eventDispatcher,
        private PreviewEventApplier $previewEventApplier
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

        if ($previewFilePath !== null && is_file($previewFilePath)) {
            $interpreter = $this->interpreterFactory->loadInterpreter(
                $name,
                $preparedConfig['interpreterConfig'],
                $preparedConfig['processingConfig']
            );

            $previewFilePath = $this->previewEventApplier->applyToPath(
                $name,
                $preparedConfig['processingConfig'],
                $previewFilePath
            );
            $dataPreview = $interpreter->previewData($previewFilePath, $recordNumber);
            $dataPreview = $this->previewEventApplier->applyToPreviewData(
                $name,
                $preparedConfig['processingConfig'],
                $dataPreview
            );
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
}
