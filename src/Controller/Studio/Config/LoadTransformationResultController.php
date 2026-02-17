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

use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Schema;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Prefix;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Tags;
use Pimcore\Bundle\DataImporterBundle\Schema\LoadPreviewParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\TransformationResultPreviewsResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\TransformationServiceInterface;
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
 * @internal
 */
final class LoadTransformationResultController extends AbstractApiController
{
    private const string ROUTE = '/config/{name}/transformation-result';

    public function __construct(
        SerializerInterface $serializer,
        private readonly TransformationServiceInterface $transformationService
    ) {
        parent::__construct($serializer);
    }

    #[Route(
        path: self::ROUTE,
        name: 'pimcore_studio_api_data_importer_config_load_transformation_result',
        methods: ['POST']
    )]
    #[Post(
        path: Prefix::BUNDLE . self::ROUTE,
        operationId: 'bundle_data_importer_config_load_transformation_result',
        description: 'bundle_data_importer_config_load_transformation_result_description',
        summary: 'bundle_data_importer_config_load_transformation_result_summary',
        tags: [Tags::DataImporter->value]
    )]
    #[IdParameter(
        type: 'configuration',
        schema: new Schema(type: 'string'),
        name: 'name',
    )]
    #[ReferenceRequestBody(LoadPreviewParameters::class)]
    #[SuccessResponse(
        description: 'bundle_data_importer_config_load_transformation_result_success_response',
        content: new JsonContent(ref: TransformationResultPreviewsResponse::class)
    )]
    #[IsGranted(PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG)]
    #[DefaultResponses([
        HttpResponseCodes::UNAUTHORIZED,
        HttpResponseCodes::NOT_FOUND,
        HttpResponseCodes::FORBIDDEN,
    ])]
    public function loadTransformationResult(
        string $name,
        #[MapRequestPayload] LoadPreviewParameters $parameters
    ): JsonResponse {
        return $this->jsonResponse(
            $this->transformationService->loadTransformationResultPreviews(
                $name,
                $parameters->getCurrentConfig(),
                $parameters->getRecordNumber()
            )
        );
    }
}
