<?php

declare(strict_types=1);

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

use Exception;
use Pimcore\Controller\UserAwareController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/pimcoredataimporter/')]
class ConnectionController extends UserAwareController
{
    /**
     * @throws Exception
     */
    #[Route('/connections', name: 'pimcore_dataimporter_connections', methods: ['GET'])]
    public function connectionAction(): JsonResponse
    {
        $connections = $this->getParameter('doctrine.connections');

        if (!is_array($connections)) {
            throw new Exception('Doctrine connection not returned as array');
        }

        $mappedConnections = array_map(fn ($key, $value): array => [
            'name' => $key,
            'value' => $value
        ], array_keys($connections), $connections);

        return $this->json($mappedConnections);
    }
}
