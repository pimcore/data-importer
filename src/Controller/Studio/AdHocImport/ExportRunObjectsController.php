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
use OpenApi\Attributes\Schema;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Prefix;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Tags;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportRunObjectsServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Controller\AbstractApiController;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Parameter\Path\IdParameter;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Parameter\Query\TextFieldParameter;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\Content\MediaType;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\DefaultResponses;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\Header\ContentDisposition;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\SuccessResponse;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\Asset\MimeTypes;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\HttpResponseCodes;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * The objects an ended import updated or created as CSV, for the user who started it.
 *
 * @internal
 */
final class ExportRunObjectsController extends AbstractApiController
{
    private const string ROUTE = '/ad-hoc-import/file/{fileId}/run/objects/export';

    public function __construct(
        SerializerInterface $serializer,
        private readonly AdHocImportRunObjectsServiceInterface $objectsService
    ) {
        parent::__construct($serializer);
    }

    #[Route(
        path: self::ROUTE,
        name: 'pimcore_studio_api_data_importer_ad_hoc_import_export_run_objects',
        methods: ['GET']
    )]
    #[Get(
        path: Prefix::BUNDLE . self::ROUTE,
        operationId: 'bundle_data_importer_ad_hoc_import_export_run_objects',
        description: 'bundle_data_importer_ad_hoc_import_export_run_objects_description',
        summary: 'bundle_data_importer_ad_hoc_import_export_run_objects_summary',
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
    #[SuccessResponse(
        description: 'bundle_data_importer_ad_hoc_import_export_run_objects_success_response',
        content: [new MediaType(MimeTypes::CSV->value)],
        headers: [new ContentDisposition(fileName: 'objects.csv')]
    )]
    #[IsGranted(PermissionConstants::PLUGIN_DATA_IMPORTER_AD_HOC_IMPORT)]
    #[DefaultResponses([
        HttpResponseCodes::UNAUTHORIZED,
        HttpResponseCodes::NOT_FOUND,
        HttpResponseCodes::FORBIDDEN,
        HttpResponseCodes::UNPROCESSABLE_CONTENT,
    ])]
    public function exportRunObjects(string $fileId, #[MapQueryParameter] string $kind): Response
    {
        return new Response(
            $this->objectsService->objectsCsv($fileId, $kind),
            Response::HTTP_OK,
            [
                'Content-Type' => MimeTypes::CSV->value . '; charset=UTF-8',
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_ATTACHMENT,
                    $kind . '-objects.csv'
                ),
            ]
        );
    }
}
