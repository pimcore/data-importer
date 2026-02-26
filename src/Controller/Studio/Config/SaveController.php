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
use OpenApi\Attributes\Put;
use OpenApi\Attributes\Schema;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Prefix;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Tags;
use Pimcore\Bundle\DataImporterBundle\Schema\ConfigurationDetail;
use Pimcore\Bundle\DataImporterBundle\Schema\ConfigurationSaveParameters;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\ConfigurationServiceInterface;
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
final class SaveController extends AbstractApiController
{
    private const string ROUTE = '/config/{name}';

    public function __construct(
        SerializerInterface $serializer,
        private readonly ConfigurationServiceInterface $configurationService
    ) {
        parent::__construct($serializer);
    }

    #[Route(
        path: self::ROUTE,
        name: 'pimcore_studio_api_data_importer_config_save',
        methods: ['PUT']
    )]
    #[Put(
        path: Prefix::BUNDLE . self::ROUTE,
        operationId: 'bundle_data_importer_config_save',
        description: 'bundle_data_importer_config_save_description',
        summary: 'bundle_data_importer_config_save_summary',
        tags: [Tags::DataImporter->value]
    )]
    #[IdParameter(
        type: 'configuration',
        schema: new Schema(type: 'string'),
        name: 'name',
    )]
    #[ReferenceRequestBody(ConfigurationSaveParameters::class)]
    #[SuccessResponse(
        description: 'bundle_data_importer_config_save_success_response',
        content: new JsonContent(ref: ConfigurationDetail::class)
    )]
    #[IsGranted(PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG)]
    #[DefaultResponses([
        HttpResponseCodes::UNAUTHORIZED,
        HttpResponseCodes::NOT_FOUND,
        HttpResponseCodes::FORBIDDEN,
        HttpResponseCodes::CONFLICT,
    ])]
    public function saveConfiguration(
        string $name,
        #[MapRequestPayload] ConfigurationSaveParameters $parameters
    ): JsonResponse {
        return $this->jsonResponse(
            $this->configurationService->saveConfiguration(
                $name,
                $parameters->getConfiguration(),
                $parameters->getModificationDate()
            )
        );
    }
}
