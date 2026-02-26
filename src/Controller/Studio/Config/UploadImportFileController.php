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

namespace Pimcore\Bundle\DataImporterBundle\Controller\Studio\Config;

use OpenApi\Attributes\Post;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Prefix;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Tags;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\ImportServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Controller\AbstractApiController;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Parameter\Path\IdParameter;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Request\MultipartFormDataRequestBody;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\DefaultResponses;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\SuccessResponse;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\HttpResponseCodes;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @internal
 */
final class UploadImportFileController extends AbstractApiController
{
    private const string ROUTE = '/config/{name}/upload-import-file';

    public function __construct(
        SerializerInterface $serializer,
        private readonly ImportServiceInterface $importService
    ) {
        parent::__construct($serializer);
    }

    #[Route(
        path: self::ROUTE,
        name: 'pimcore_studio_api_data_importer_config_upload_import_file',
        methods: ['POST']
    )]
    #[Post(
        path: Prefix::BUNDLE . self::ROUTE,
        operationId: 'bundle_data_importer_config_upload_import_file',
        description: 'bundle_data_importer_config_upload_import_file_description',
        summary: 'bundle_data_importer_config_upload_import_file_summary',
        tags: [Tags::DataImporter->value]
    )]
    #[IdParameter(
        type: 'configuration',
        schema: new Schema(type: 'string'),
        name: 'name',
    )]
    #[MultipartFormDataRequestBody(
        [
            new Property(
                property: 'file',
                description: 'Import data file to upload',
                type: 'string',
                format: 'binary'
            ),
        ],
        ['file']
    )]
    #[SuccessResponse(
        description: 'bundle_data_importer_config_upload_import_file_success_response',
    )]
    #[IsGranted(PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG)]
    #[DefaultResponses([
        HttpResponseCodes::UNAUTHORIZED,
        HttpResponseCodes::NOT_FOUND,
        HttpResponseCodes::FORBIDDEN,
    ])]
    public function uploadImportFile(
        string $name,
        Request $request
    ): Response {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new EnvironmentException('Invalid file found in the request');
        }

        $this->importService->uploadImportFile($name, $file);

        return new Response();
    }
}
