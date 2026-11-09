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
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportWaitingRunsResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportRunServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Controller\AbstractApiController;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Parameter\Path\IdParameter;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\DefaultResponses;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\SuccessResponse;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\HttpResponseCodes;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * The imports of the user into a folder that wait for their decision, so that the dialog can show them again.
 *
 * @internal
 */
final class ListWaitingRunsController extends AbstractApiController
{
    private const string ROUTE = '/ad-hoc-import/folder/{folderId}/waiting-runs';

    public function __construct(
        SerializerInterface $serializer,
        private readonly AdHocImportRunServiceInterface $runService
    ) {
        parent::__construct($serializer);
    }

    #[Route(
        path: self::ROUTE,
        name: 'pimcore_studio_api_data_importer_ad_hoc_import_list_waiting_runs',
        methods: ['GET']
    )]
    #[Get(
        path: Prefix::BUNDLE . self::ROUTE,
        operationId: 'bundle_data_importer_ad_hoc_import_list_waiting_runs',
        description: 'bundle_data_importer_ad_hoc_import_list_waiting_runs_description',
        summary: 'bundle_data_importer_ad_hoc_import_list_waiting_runs_summary',
        tags: [Tags::DataImporter->value]
    )]
    #[IdParameter(
        type: 'folder',
        schema: new Schema(type: 'integer', example: 3),
        name: 'folderId',
    )]
    #[SuccessResponse(
        description: 'bundle_data_importer_ad_hoc_import_list_waiting_runs_success_response',
        content: new JsonContent(ref: AdHocImportWaitingRunsResponse::class)
    )]
    #[IsGranted(PermissionConstants::PLUGIN_DATA_IMPORTER_AD_HOC_IMPORT)]
    #[DefaultResponses([
        HttpResponseCodes::UNAUTHORIZED,
        HttpResponseCodes::FORBIDDEN,
    ])]
    public function listWaitingRuns(int $folderId): JsonResponse
    {
        return $this->jsonResponse(new AdHocImportWaitingRunsResponse($this->runService->waitingRuns($folderId)));
    }
}
