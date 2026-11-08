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
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Prefix;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Tags;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassificationStoreKeysResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportMappingServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Controller\AbstractApiController;
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
 * The keys of a classification store a column can be imported into, for users of the ad hoc import without any Data
 * Hub right.
 *
 * @internal
 */
final class LoadClassificationStoreKeysController extends AbstractApiController
{
    private const string ROUTE = '/ad-hoc-import/classification-store-keys';

    public function __construct(
        SerializerInterface $serializer,
        private readonly AdHocImportMappingServiceInterface $mappingService
    ) {
        parent::__construct($serializer);
    }

    #[Route(
        path: self::ROUTE,
        name: 'pimcore_studio_api_data_importer_ad_hoc_import_load_classification_store_keys',
        methods: ['GET']
    )]
    #[Get(
        path: Prefix::BUNDLE . self::ROUTE,
        operationId: 'bundle_data_importer_ad_hoc_import_load_classification_store_keys',
        description: 'bundle_data_importer_ad_hoc_import_load_classification_store_keys_description',
        summary: 'bundle_data_importer_ad_hoc_import_load_classification_store_keys_summary',
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
        example: 'attributes'
    )]
    #[TextFieldParameter(
        name: 'searchfilter',
        description: 'Search string to filter keys by name, group name, or description',
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
    #[SuccessResponse(
        description: 'bundle_data_importer_ad_hoc_import_load_classification_store_keys_success_response',
        content: new JsonContent(ref: ClassificationStoreKeysResponse::class)
    )]
    #[IsGranted(PermissionConstants::PLUGIN_DATA_IMPORTER_AD_HOC_IMPORT)]
    #[DefaultResponses([
        HttpResponseCodes::UNAUTHORIZED,
        HttpResponseCodes::FORBIDDEN,
        HttpResponseCodes::NOT_FOUND,
    ])]
    public function loadClassificationStoreKeys(
        #[MapQueryParameter] string $classId,
        #[MapQueryParameter] string $fieldName,
        #[MapQueryParameter] ?string $searchfilter = null,
        #[MapQueryParameter] int $start = 0,
        #[MapQueryParameter] int $limit = 15,
    ): JsonResponse {
        return $this->jsonResponse(
            $this->mappingService->loadClassificationStoreKeys($classId, $fieldName, $searchfilter, $start, $limit)
        );
    }
}
