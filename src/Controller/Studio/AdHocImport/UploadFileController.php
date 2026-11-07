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
use OpenApi\Attributes\Property;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Prefix;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Tags;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportFileResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Controller\AbstractApiController;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Request\MultipartFormDataRequestBody;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\DefaultResponses;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\SuccessResponse;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\HttpResponseCodes;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @internal
 */
final class UploadFileController extends AbstractApiController
{
    private const string ROUTE = '/ad-hoc-import/file';

    public function __construct(
        SerializerInterface $serializer,
        private readonly AdHocImportServiceInterface $adHocImportService
    ) {
        parent::__construct($serializer);
    }

    #[Route(
        path: self::ROUTE,
        name: 'pimcore_studio_api_data_importer_ad_hoc_import_upload_file',
        methods: ['POST']
    )]
    #[Post(
        path: Prefix::BUNDLE . self::ROUTE,
        operationId: 'bundle_data_importer_ad_hoc_import_upload_file',
        description: 'bundle_data_importer_ad_hoc_import_upload_file_description',
        summary: 'bundle_data_importer_ad_hoc_import_upload_file_summary',
        tags: [Tags::DataImporter->value]
    )]
    #[MultipartFormDataRequestBody(
        [
            new Property(
                property: 'file',
                description: 'The CSV or XLSX file',
                type: 'string',
                format: 'binary'
            ),
            new Property(
                property: 'folderId',
                description: 'The data object folder the import starts from',
                type: 'integer'
            ),
        ],
        ['file', 'folderId']
    )]
    #[SuccessResponse(
        description: 'bundle_data_importer_ad_hoc_import_upload_file_success_response',
        content: new JsonContent(ref: AdHocImportFileResponse::class)
    )]
    #[IsGranted(PermissionConstants::PLUGIN_DATA_IMPORTER_AD_HOC_IMPORT)]
    #[DefaultResponses([
        HttpResponseCodes::UNAUTHORIZED,
        HttpResponseCodes::NOT_FOUND,
        HttpResponseCodes::FORBIDDEN,
        HttpResponseCodes::MAX_FILE_SIZE_EXCEEDED,
    ])]
    public function uploadFile(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new EnvironmentException('Invalid file found in the request');
        }

        return $this->jsonResponse(
            $this->adHocImportService->upload($file, $request->request->getInt('folderId'))
        );
    }
}
