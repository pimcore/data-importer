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

namespace Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport;

use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Schema;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Prefix;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Tags;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportRunObjectsResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportRunObjectsServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Controller\AbstractApiController;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Parameter\Path\IdParameter;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Parameter\Query\IntParameter;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Parameter\Query\TextFieldParameter;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\DefaultResponses;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\SuccessResponse;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\HttpResponseCodes;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * The objects an ended import updated or created, for the user who started it.
 *
 * @internal
 */
final class ListRunObjectsController extends AbstractApiController
{
    private const string ROUTE = '/ad-hoc-import/file/{fileId}/run/objects';

    public function __construct(
        SerializerInterface $serializer,
        private readonly AdHocImportRunObjectsServiceInterface $objectsService
    ) {
        parent::__construct($serializer);
    }

    #[Route(
        path: self::ROUTE,
        name: 'pimcore_studio_api_data_importer_ad_hoc_import_list_run_objects',
        methods: ['GET']
    )]
    #[Get(
        path: Prefix::BUNDLE . self::ROUTE,
        operationId: 'bundle_data_importer_ad_hoc_import_list_run_objects',
        description: 'bundle_data_importer_ad_hoc_import_list_run_objects_description',
        summary: 'bundle_data_importer_ad_hoc_import_list_run_objects_summary',
        tags: [Tags::DataImporter->value]
    )]
    #[IdParameter(
        type: 'file',
        schema: new Schema(type: 'string'),
        name: 'fileId',
    )]
    #[TextFieldParameter(
        name: 'kind',
        description: 'updated or created',
        required: true,
        example: 'updated'
    )]
    #[IntParameter(name: 'page', description: 'Page, from 1', required: false, example: 1)]
    #[IntParameter(name: 'pageSize', description: 'Objects per page, at most 200', required: false, example: 50)]
    #[SuccessResponse(
        description: 'bundle_data_importer_ad_hoc_import_list_run_objects_success_response',
        content: new JsonContent(ref: AdHocImportRunObjectsResponse::class)
    )]
    #[IsGranted(PermissionConstants::PLUGIN_DATA_IMPORTER_AD_HOC_IMPORT)]
    #[DefaultResponses([
        HttpResponseCodes::UNAUTHORIZED,
        HttpResponseCodes::NOT_FOUND,
        HttpResponseCodes::FORBIDDEN,
        HttpResponseCodes::UNPROCESSABLE_CONTENT,
    ])]
    public function listRunObjects(
        string $fileId,
        #[MapQueryParameter] string $kind,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $pageSize = 50,
    ): JsonResponse {
        return $this->jsonResponse($this->objectsService->objects($fileId, $kind, $page, $pageSize));
    }
}
