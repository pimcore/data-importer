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

namespace Pimcore\Bundle\DataImporterBundle\Controller\Studio\ClassificationStore;

use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Prefix;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Tags;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassificationStoreKeyParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassificationStoreKeysResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Controller\AbstractApiController;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Parameter\Query\IntParameter;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Parameter\Query\TextFieldParameter;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\DefaultResponses;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\SuccessResponse;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\HttpResponseCodes;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @internal
 */
final class LoadKeysController extends AbstractApiController
{
    private const string ROUTE = '/classificationstore/keys';

    public function __construct(
        SerializerInterface $serializer,
        private readonly DataTypeServiceInterface $dataTypeService
    ) {
        parent::__construct($serializer);
    }

    /**
     * @throws \Exception
     */
    #[Route(
        path: self::ROUTE,
        name: 'pimcore_studio_api_data_importer_classificationstore_load_keys',
        methods: ['GET']
    )]
    #[Get(
        path: Prefix::BUNDLE . self::ROUTE,
        operationId: 'bundle_data_importer_classificationstore_load_keys',
        description: 'bundle_data_importer_classificationstore_load_keys_description',
        summary: 'bundle_data_importer_classificationstore_load_keys_summary',
        tags: [Tags::DataImporter->value]
    )]
    #[TextFieldParameter(
        name: 'classId',
        description: 'The ID of the data object class',
        required: true,
        example: 'Product'
    )]
    #[TextFieldParameter(
        name: 'fieldName',
        description: 'The classification store field name',
        required: true,
        example: 'classificationStore'
    )]
    #[TextFieldParameter(
        name: 'transformationResultType',
        description: 'Filter keys by transformation result data type',
        required: true,
        example: 'default'
    )]
    #[TextFieldParameter(
        name: 'sort',
        description: 'JSON-encoded sort configuration',
        required: false,
        example: null
    )]
    #[IntParameter(
        name: 'start',
        description: 'Pagination offset',
        required: false,
        example: 0
    )]
    #[IntParameter(
        name: 'limit',
        description: 'Maximum number of results',
        required: false,
        example: 15
    )]
    #[TextFieldParameter(
        name: 'searchfilter',
        description: 'Search string to filter keys by name, group name, or description',
        required: false,
        example: null
    )]
    #[TextFieldParameter(
        name: 'filter',
        description: 'JSON-encoded column filter configuration',
        required: false,
        example: null
    )]
    #[SuccessResponse(
        description: 'bundle_data_importer_classificationstore_load_keys_success_response',
        content: new JsonContent(ref: ClassificationStoreKeysResponse::class)
    )]
    #[IsGranted(PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG)]
    #[DefaultResponses([
        HttpResponseCodes::UNAUTHORIZED,
    ])]
    public function loadKeys(
        #[MapQueryString] ClassificationStoreKeyParameters $parameters = new ClassificationStoreKeyParameters()
    ): JsonResponse {
        return $this->jsonResponse(
            $this->dataTypeService->loadClassificationStoreKeys($parameters)
        );
    }
}
