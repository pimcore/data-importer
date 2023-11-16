<?php

namespace Pimcore\Bundle\DataImporterBundle\Controller;

use Exception;
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
     *
     * @throws Exception
     */
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
