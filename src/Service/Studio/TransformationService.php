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
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Hydrator\TransformationHydratorInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Mapping\WritesElementsInterface;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewEventApplier;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Schema\TransformationResultPreviewsResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\TransformationResultTypeResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\ConfigurationPermissionTrait;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CurrentUserResolverTrait;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final readonly class TransformationService implements TransformationServiceInterface
{
    use ConfigurationPermissionTrait;
    use CurrentUserResolverTrait;

    // operators log under their configuration's name; a posted configuration has none
    private const string POSTED_CONFIG_NAME = '';

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

        return $this->previewTransformationResults($name, $preparedConfig['mappingConfig'], $importDataRow);
    }

    public function loadTransformationResultPreviewsFor(
        array $mappingConfig,
        array $dataRow
    ): TransformationResultPreviewsResponse {
        $this->assertImporterPermission();
        foreach ($mappingConfig as $index => $entry) {
            $this->assertPostedEntryShape($entry, sprintf('Mapping entry %s', $index));
        }
        $this->assertNoSideEffects($mappingConfig);

        try {
            return $this->previewTransformationResults(self::POSTED_CONFIG_NAME, $mappingConfig, $dataRow);
        } catch (InvalidConfigurationException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), $exception);
        }
    }

    public function calculateTransformationResultType(
        string $name,
        array $currentConfig
    ): TransformationResultTypeResponse {
        $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ
        );

        return $this->evaluateTransformationResultType($name, $currentConfig);
    }

    public function calculateTransformationResultTypeOf(array $mappingEntry): TransformationResultTypeResponse
    {
        $this->assertImporterPermission();
        $this->assertPostedEntryShape($mappingEntry, 'The mapping entry');

        try {
            return $this->evaluateTransformationResultType(self::POSTED_CONFIG_NAME, $mappingEntry);
        } catch (InvalidConfigurationException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), $exception);
        }
    }

    /**
     * The factory takes a posted entry as it is and fails with a \TypeError on a wrong shape; refuse that as a 422.
     *
     * @throws InvalidArgumentException
     */
    private function assertPostedEntryShape(mixed $entry, string $what): void
    {
        if (!is_array($entry)) {
            throw new InvalidArgumentException(sprintf('%s is not an object.', $what));
        }
        if (!is_string($entry['label'] ?? '')) {
            throw new InvalidArgumentException(sprintf('%s: label must be a string.', $what));
        }
        $indexes = $entry['dataSourceIndex'] ?? [];
        $indexesWellFormed = is_array($indexes)
            && array_filter($indexes, static fn ($index) => !is_string($index) && !is_int($index)) === [];
        if (!$indexesWellFormed) {
            throw new InvalidArgumentException(sprintf('%s: dataSourceIndex must be a list of columns.', $what));
        }
        $pipeline = $entry['transformationPipeline'] ?? [];
        if (!is_array($pipeline)) {
            throw new InvalidArgumentException(
                sprintf('%s: transformationPipeline must be a list of operators.', $what)
            );
        }
        foreach ($pipeline as $position => $operator) {
            $wellFormed = is_array($operator)
                && is_string($operator['type'] ?? null)
                && is_array($operator['settings'] ?? []);
            if (!$wellFormed) {
                throw new InvalidArgumentException(sprintf(
                    '%s: operator %s needs a type and, if any, settings as an object.',
                    $what,
                    $position
                ));
            }
        }
    }

    /**
     * Studio renders only its API exceptions as JSON, so only these carry the reason to the client.
     *
     * @throws InvalidArgumentException
     */
    private function assertNoSideEffects(array $mappingConfig): void
    {
        foreach ($mappingConfig as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            try {
                $item = $this->mappingConfigurationFactory->loadMappingConfigurationItem(
                    self::POSTED_CONFIG_NAME,
                    $entry,
                    true
                );
            } catch (InvalidConfigurationException) {
                // an unknown operator fails in the preview itself, with its own message
                continue;
            }

            // a preview runs operators with dryRun, but these still write or fetch (importAsset creates folders and
            // loads URLs)
            $operatorConfigs = array_values((array) ($entry['transformationPipeline'] ?? []));
            foreach ($item->getTransformationPipeline() as $position => $operator) {
                if ($operator instanceof WritesElementsInterface) {
                    throw new InvalidArgumentException(sprintf(
                        'The operator "%s" writes or fetches data, so a posted mapping cannot preview it.',
                        $operatorConfigs[$position]['type'] ?? $operator::class
                    ));
                }
            }
        }
    }

    /**
     * A posted pipeline has no permission grid, so it needs the rule
     * Configuration::isAllowed() falls back to for an importer without one.
     *
     * @throws EnvironmentException
     * @throws ForbiddenException
     */
    private function assertImporterPermission(): void
    {
        $user = $this->resolveCurrentUser();

        // isAllowed() is true for admins
        if (!$user->isAllowed(PermissionConstants::PLUGIN_DATA_IMPORTER_ADMIN) &&
            !$user->isAllowed(PermissionConstants::PLUGIN_DATA_IMPORTER_ADAPTER)
        ) {
            throw new ForbiddenException('Access denied to the data importer');
        }
    }

    /**
     * @throws InvalidConfigurationException
     */
    private function previewTransformationResults(
        string $name,
        array $mappingConfig,
        array $importDataRow
    ): TransformationResultPreviewsResponse {
        $mapping = $this->mappingConfigurationFactory->loadMappingConfiguration($name, $mappingConfig, true);

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

    /**
     * @throws InvalidConfigurationException
     */
    private function evaluateTransformationResultType(
        string $name,
        array $mappingEntry
    ): TransformationResultTypeResponse {
        $mappingConfiguration = $this->mappingConfigurationFactory->loadMappingConfigurationItem(
            $name,
            $mappingEntry,
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
