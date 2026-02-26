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

use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\ConnectionsEvent;
use Pimcore\Bundle\DataImporterBundle\Hydrator\ConnectionHydratorInterface;
use Pimcore\Bundle\DataImporterBundle\Schema\ConnectionsResponse;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final readonly class ConnectionService implements ConnectionServiceInterface
{
    /**
     * @param array<string, string> $doctrineConnections
     */
    public function __construct(
        private array $doctrineConnections,
        private ConnectionHydratorInterface $connectionHydrator,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function listConnections(): ConnectionsResponse
    {
        $mappedConnections = array_map(
            static fn (string $key, string $value): array => [
                'name' => $key,
                'value' => $value,
            ],
            array_keys($this->doctrineConnections),
            $this->doctrineConnections
        );

        $response = $this->connectionHydrator->hydrateConnections($mappedConnections);

        $this->eventDispatcher->dispatch(
            new ConnectionsEvent($response),
            ConnectionsEvent::EVENT_NAME
        );

        return $response;
    }
}
