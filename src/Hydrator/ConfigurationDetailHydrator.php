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

namespace Pimcore\Bundle\DataImporterBundle\Hydrator;

use Exception;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\ConfigurationDetailEvent;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Bundle\DataImporterBundle\Schema\ConfigurationDetail;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
use Pimcore\Logger;
use Pimcore\Security\User\UserLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final readonly class ConfigurationDetailHydrator implements ConfigurationDetailHydratorInterface
{
    public function __construct(
        private ConfigurationPreparationService $configurationPreparationService,
        private PreviewService $previewService,
        private InterpreterFactory $interpreterFactory,
        private UserLoader $userLoader,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @throws Exception
     */
    public function hydrate(Configuration $configuration): ConfigurationDetail
    {
        $name = $configuration->getName();
        $config = $this->configurationPreparationService->prepareConfiguration($name);
        $columnHeaders = $this->loadAvailableColumnHeaders($name, $config);

        $hydratedConfig = new ConfigurationDetail(
            name: $name,
            configuration: $config,
            userPermissions: $config['userPermissions'] ?? ['update' => false, 'delete' => false],
            modificationDate: $configuration->getModificationDate(),
            columnHeaders: $columnHeaders
        );

        $event = new ConfigurationDetailEvent($hydratedConfig);
        $this->eventDispatcher->dispatch($event, ConfigurationDetailEvent::EVENT_NAME);

        return $event->getConfig();
    }

    /**
     * Load available column headers from preview data
     */
    private function loadAvailableColumnHeaders(string $configName, array $config): array
    {
        $user = $this->userLoader->getUser();
        if (!$user) {
            return [];
        }

        $previewFilePath = $this->previewService->getLocalPreviewFile($configName, $user);
        if (!$previewFilePath || !is_file($previewFilePath)) {
            return [];
        }

        try {
            $interpreter = $this->interpreterFactory->loadInterpreter(
                $configName,
                $config['interpreterConfig'],
                $config['processingConfig']
            );
            $dataPreview = $interpreter->previewData($previewFilePath);
            $columnHeaders = $dataPreview->getDataColumnHeaders();

            // Validate if the column headers are valid JSON
            if (!$this->isValidJson($columnHeaders)) {
                throw new Exception('Invalid column headers.');
            }

            return $columnHeaders;
        } catch (Exception $e) {
            Logger::warning($e->getMessage());

            return [];
        }
    }

    private function isValidJson(array $array): bool
    {
        json_encode($array);

        return json_last_error() === \JSON_ERROR_NONE;
    }
}
