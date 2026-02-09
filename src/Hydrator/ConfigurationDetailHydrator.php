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
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\ConfigurationDetailEvent;
use Pimcore\Bundle\DataImporterBundle\Schema\ConfigurationDetail;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final readonly class ConfigurationDetailHydrator implements ConfigurationDetailHydratorInterface
{
    public function __construct(
        private ConfigurationPreparationService $configurationPreparationService,
        private PreviewHydratorInterface $previewHydrator,
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
        $columnHeaders = $this->previewHydrator->loadAvailableColumnHeaders($name, $config);

        $hydratedConfig = new ConfigurationDetail(
            $name,
            $config,
            $config['userPermissions'] ?? ['update' => false, 'delete' => false],
            $configuration->getModificationDate(),
            $columnHeaders
        );

        $event = new ConfigurationDetailEvent($hydratedConfig);
        $this->eventDispatcher->dispatch($event, ConfigurationDetailEvent::EVENT_NAME);

        return $event->getConfig();
    }
}
