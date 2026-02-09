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
use Pimcore\Bundle\DataImporterBundle\Schema\ClassAttributesResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Controller\AbstractApiController;
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
 * @internal
 */
final class LoadAttributesController extends AbstractApiController
{
    private const string ROUTE = '/classificationstore/attributes';

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
        name: 'pimcore_studio_api_data_importer_classificationstore_load_attributes',
        methods: ['GET']
    )]
    #[Get(
        path: Prefix::BUNDLE . self::ROUTE,
        operationId: 'bundle_data_importer_classificationstore_load_attributes',
        description: 'bundle_data_importer_classificationstore_load_attributes_description',
        summary: 'bundle_data_importer_classificationstore_load_attributes_summary',
        tags: [Tags::DataImporter->value]
    )]
    #[TextFieldParameter(
        name: 'classId',
        description: 'The ID of the data object class to load classification store attributes for',
        required: true,
        example: 'Product'
    )]
    #[SuccessResponse(
        description: 'bundle_data_importer_classificationstore_load_attributes_success_response',
        content: new JsonContent(ref: ClassAttributesResponse::class)
    )]
    #[IsGranted(PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG)]
    #[DefaultResponses([
        HttpResponseCodes::UNAUTHORIZED,
    ])]
    public function loadAttributes(
        #[MapQueryParameter] ?string $classId = null
    ): JsonResponse {
        if (empty($classId)) {
            return $this->jsonResponse(new ClassAttributesResponse([]));
        }

        return $this->jsonResponse(
            $this->dataTypeService->loadClassificationStoreAttributes($classId)
        );
    }
}
