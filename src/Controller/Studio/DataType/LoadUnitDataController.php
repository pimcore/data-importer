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

namespace Pimcore\Bundle\DataImporterBundle\Controller\Studio\DataType;

use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Prefix;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Tags;
use Pimcore\Bundle\DataImporterBundle\Schema\UnitDataResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Controller\AbstractApiController;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\DefaultResponses;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\SuccessResponse;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\HttpResponseCodes;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @internal
 */
final class LoadUnitDataController extends AbstractApiController
{
    private const string ROUTE = '/data-type/unit-data';

    public function __construct(
        SerializerInterface $serializer,
        private readonly DataTypeServiceInterface $dataTypeService
    ) {
        parent::__construct($serializer);
    }

    #[Route(
        path: self::ROUTE,
        name: 'pimcore_studio_api_data_importer_data_type_load_unit_data',
        methods: ['GET']
    )]
    #[Get(
        path: Prefix::BUNDLE . self::ROUTE,
        operationId: 'bundle_data_importer_data_type_load_unit_data',
        description: 'bundle_data_importer_data_type_load_unit_data_description',
        summary: 'bundle_data_importer_data_type_load_unit_data_summary',
        tags: [Tags::DataImporter->value]
    )]
    #[SuccessResponse(
        description: 'bundle_data_importer_data_type_load_unit_data_success_response',
        content: new JsonContent(ref: UnitDataResponse::class)
    )]
    #[IsGranted(PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG)]
    #[DefaultResponses([
        HttpResponseCodes::UNAUTHORIZED,
    ])]
    public function loadUnitData(): JsonResponse
    {
        return $this->jsonResponse(
            $this->dataTypeService->loadUnitData()
        );
    }
}
