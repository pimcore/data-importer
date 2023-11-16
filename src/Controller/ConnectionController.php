<?php

namespace Pimcore\Bundle\DataImporterBundle\Controller;

use Pimcore\Controller\UserAwareController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/pimcoredataimporter/")
 */
class ConnectionController extends UserAwareController
{
    /**
     * @Route("connections", name="pimcore_dataimporter_connections", methods={"GET"})
     */
    public function connectionAction(): JsonResponse
    {
        $connections = $this->getParameter('doctrine.connections');
        $mappedConnections = array_map(fn ($key, $value): array => [
            'name' => $key,
            'value' => $value
        ], array_keys($connections), $connections);

        return $this->json($mappedConnections);
    }
}
