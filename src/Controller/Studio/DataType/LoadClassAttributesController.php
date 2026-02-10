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
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Prefix;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Tags;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassAttributeParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassAttributesResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Controller\AbstractApiController;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Parameter\Query\BoolParameter;
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
final class LoadClassAttributesController extends AbstractApiController
{
    private const string ROUTE = '/data-type/class-attributes';

    public function __construct(
        SerializerInterface $serializer,
        private readonly DataTypeServiceInterface $dataTypeService
    ) {
        parent::__construct($serializer);
    }

    #[Route(
        path: self::ROUTE,
        name: 'pimcore_studio_api_data_importer_data_type_load_class_attributes',
        methods: ['GET']
    )]
    #[Get(
        path: Prefix::BUNDLE . self::ROUTE,
        operationId: 'bundle_data_importer_data_type_load_class_attributes',
        description: 'bundle_data_importer_data_type_load_class_attributes_description',
        summary: 'bundle_data_importer_data_type_load_class_attributes_summary',
        tags: [Tags::DataImporter->value]
    )]
    #[TextFieldParameter(
        name: 'classId',
        description: 'The ID of the data object class to load attributes for',
        required: true,
        example: 'Product'
    )]
    #[BoolParameter(
        name: 'loadAdvancedRelations',
        description: 'Whether to include advanced relation field types',
        required: false,
        example: false
    )]
    #[BoolParameter(
        name: 'systemRead',
        description: 'Whether to include system read fields',
        required: false,
        example: false
    )]
    #[BoolParameter(
        name: 'systemWrite',
        description: 'Whether to include system write fields',
        required: false,
        example: false
    )]
    #[TextFieldParameter(
        name: 'transformationResultType',
        description: 'Filter attributes by transformation result data type. If not provided, defaults to DEFAULT and NUMERIC types.',
        required: false,
        example: null
    )]
    #[SuccessResponse(
        description: 'bundle_data_importer_data_type_load_class_attributes_success_response',
        content: new JsonContent(ref: ClassAttributesResponse::class)
    )]
    #[IsGranted(PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG)]
    #[DefaultResponses([
        HttpResponseCodes::UNAUTHORIZED,
    ])]
    public function loadClassAttributes(
        #[MapQueryString] ClassAttributeParameters $parameters = new ClassAttributeParameters()
    ): JsonResponse {
        $classId = $parameters->getClassId();

        if (empty($classId)) {
            return $this->jsonResponse(new ClassAttributesResponse([]));
        }

        $transformationResultType = $parameters->getTransformationResultType();
        if ($transformationResultType === null) {
            $transformationResultType = [
                TransformationDataTypeService::DEFAULT_TYPE,
                TransformationDataTypeService::NUMERIC,
            ];
        }

        return $this->jsonResponse(
            $this->dataTypeService->loadClassAttributes(
                $classId,
                $transformationResultType,
                $parameters->getSystemRead(),
                $parameters->getSystemWrite(),
                $parameters->getLoadAdvancedRelations()
            )
        );
    }
}
