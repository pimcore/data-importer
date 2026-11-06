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

namespace Pimcore\Bundle\DataImporterBundle\Controller\Studio\MappingSuggestion;

use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Schema;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Prefix;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Tags;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionsResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\MappingSuggestionServiceInterface;
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
 * @internal
 */
final class LoadAiController extends AbstractApiController
{
    private const string ROUTE = '/mapping-suggestions/ai/{requestId}';

    public function __construct(
        SerializerInterface $serializer,
        private readonly MappingSuggestionServiceInterface $mappingSuggestionService
    ) {
        parent::__construct($serializer);
    }

    #[Route(
        path: self::ROUTE,
        name: 'pimcore_studio_api_data_importer_mapping_suggestion_load_ai',
        requirements: ['requestId' => '[a-f0-9]{32}'],
        methods: ['GET']
    )]
    #[Get(
        path: Prefix::BUNDLE . self::ROUTE,
        operationId: 'bundle_data_importer_mapping_suggestion_load_ai',
        description: 'bundle_data_importer_mapping_suggestion_load_ai_description',
        summary: 'bundle_data_importer_mapping_suggestion_load_ai_summary',
        tags: [Tags::DataImporter->value]
    )]
    #[IdParameter(
        type: 'mapping suggestion request',
        schema: new Schema(type: 'string'),
        name: 'requestId',
    )]
    #[SuccessResponse(
        description: 'bundle_data_importer_mapping_suggestion_load_ai_success_response',
        content: new JsonContent(ref: MappingSuggestionsResponse::class)
    )]
    #[IsGranted(PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG)]
    #[DefaultResponses([
        HttpResponseCodes::UNAUTHORIZED,
        HttpResponseCodes::FORBIDDEN,
        HttpResponseCodes::NOT_FOUND,
    ])]
    public function load(string $requestId): JsonResponse
    {
        return $this->jsonResponse($this->mappingSuggestionService->loadAi($requestId));
    }
}
