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

use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Schema;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Prefix;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Tags;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportRunParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportRunResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportRunServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Controller\AbstractApiController;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Parameter\Path\IdParameter;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Request\ReferenceRequestBody;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\DefaultResponses;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\SuccessResponse;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\HttpResponseCodes;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Starts the import of a file in the background.
 *
 * @internal
 */
final class StartRunController extends AbstractApiController
{
    private const string ROUTE = '/ad-hoc-import/file/{fileId}/run';

    public function __construct(
        SerializerInterface $serializer,
        private readonly AdHocImportRunServiceInterface $runService
    ) {
        parent::__construct($serializer);
    }

    #[Route(
        path: self::ROUTE,
        name: 'pimcore_studio_api_data_importer_ad_hoc_import_start_run',
        methods: ['POST']
    )]
    #[Post(
        path: Prefix::BUNDLE . self::ROUTE,
        operationId: 'bundle_data_importer_ad_hoc_import_start_run',
        description: 'bundle_data_importer_ad_hoc_import_start_run_description',
        summary: 'bundle_data_importer_ad_hoc_import_start_run_summary',
        tags: [Tags::DataImporter->value]
    )]
    #[IdParameter(
        type: 'file',
        schema: new Schema(type: 'string'),
        name: 'fileId',
    )]
    #[ReferenceRequestBody(AdHocImportRunParameters::class)]
    #[SuccessResponse(
        description: 'bundle_data_importer_ad_hoc_import_start_run_success_response',
        content: new JsonContent(ref: AdHocImportRunResponse::class)
    )]
    #[IsGranted(PermissionConstants::PLUGIN_DATA_IMPORTER_AD_HOC_IMPORT)]
    #[DefaultResponses([
        HttpResponseCodes::UNAUTHORIZED,
        HttpResponseCodes::NOT_FOUND,
        HttpResponseCodes::FORBIDDEN,
        HttpResponseCodes::UNPROCESSABLE_CONTENT,
    ])]
    public function startRun(
        string $fileId,
        #[MapRequestPayload] AdHocImportRunParameters $parameters
    ): JsonResponse {
        return $this->jsonResponse($this->runService->start($fileId, $parameters));
    }
}
