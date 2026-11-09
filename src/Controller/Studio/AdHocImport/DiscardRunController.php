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

use OpenApi\Attributes\Delete;
use OpenApi\Attributes\Schema;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Prefix;
use Pimcore\Bundle\DataImporterBundle\OpenApi\Config\Tags;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportRunServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Controller\AbstractApiController;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Parameter\Path\IdParameter;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\DefaultResponses;
use Pimcore\Bundle\StudioBackendBundle\OpenApi\Attribute\Response\SuccessResponse;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\HttpResponseCodes;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Forgets a finished import and what it left for download.
 *
 * @internal
 */
final class DiscardRunController extends AbstractApiController
{
    private const string ROUTE = '/ad-hoc-import/file/{fileId}/run';

    public function __construct(
        SerializerInterface $serializer,
        private readonly AdHocImportRunServiceInterface $runService
    ) {
        parent::__construct($serializer);
    }

    #[Route(
        path: self::ROUTE,
        name: 'pimcore_studio_api_data_importer_ad_hoc_import_discard_run',
        methods: ['DELETE']
    )]
    #[Delete(
        path: Prefix::BUNDLE . self::ROUTE,
        operationId: 'bundle_data_importer_ad_hoc_import_discard_run',
        description: 'bundle_data_importer_ad_hoc_import_discard_run_description',
        summary: 'bundle_data_importer_ad_hoc_import_discard_run_summary',
        tags: [Tags::DataImporter->value]
    )]
    #[IdParameter(
        type: 'file',
        schema: new Schema(type: 'string'),
        name: 'fileId',
    )]
    #[SuccessResponse(description: 'bundle_data_importer_ad_hoc_import_discard_run_success_response')]
    #[IsGranted(PermissionConstants::PLUGIN_DATA_IMPORTER_AD_HOC_IMPORT)]
    #[DefaultResponses([
        HttpResponseCodes::UNAUTHORIZED,
        HttpResponseCodes::NOT_FOUND,
        HttpResponseCodes::FORBIDDEN,
        HttpResponseCodes::UNPROCESSABLE_CONTENT,
    ])]
    public function discardRun(string $fileId): Response
    {
        $this->runService->discard($fileId);

        return new Response();
    }
}
