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

namespace Pimcore\Bundle\DataImporterBundle\Hydrator;

use Exception;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewEventApplier;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Bundle\DataImporterBundle\Schema\ColumnHeadersResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\DataPreviewResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CurrentUserResolverTrait;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Logger;

/**
 * @internal
 */
final readonly class PreviewHydrator implements PreviewHydratorInterface
{
    use CurrentUserResolverTrait;

    public function __construct(
        private SecurityServiceInterface $securityService,
        private PreviewService $previewService,
        private InterpreterFactory $interpreterFactory,
        private PreviewEventApplier $previewEventApplier
    ) {
    }

    public function hydrateDataPreview(array $dataPreview, int $recordNumber): DataPreviewResponse
    {
        return new DataPreviewResponse($dataPreview, $recordNumber);
    }

    public function hydrateColumnHeaders(array $columnHeaders): ColumnHeadersResponse
    {
        return new ColumnHeadersResponse($columnHeaders);
    }

    public function loadAvailableColumnHeaders(string $name, array $config): array
    {
        try {
            $user = $this->resolveCurrentUser();
        } catch (EnvironmentException) {
            return [];
        }

        $previewFilePath = $this->previewService->getLocalPreviewFile($name, $user);
        if ($previewFilePath === null || !is_file($previewFilePath)) {
            return [];
        }

        try {
            $interpreter = $this->interpreterFactory->loadInterpreter(
                $name,
                $config['interpreterConfig'],
                $config['processingConfig']
            );
            $previewFilePath = $this->previewEventApplier->applyToPath(
                $name,
                $config['processingConfig'],
                $previewFilePath
            );
            $dataPreview = $interpreter->previewData($previewFilePath);
            $dataPreview = $this->previewEventApplier->applyToPreviewData(
                $name,
                $config['processingConfig'],
                $dataPreview
            );
            $columnHeaders = $dataPreview->getDataColumnHeaders();

            if (!$this->isValidJson($columnHeaders)) {
                throw new Exception('Invalid column headers.');
            }

            return $columnHeaders;
        } catch (Exception $e) {
            Logger::warning($e->getMessage());

            return [];
        }
    }

    public function isValidJson(array $array): bool
    {
        json_encode($array);

        return json_last_error() === \JSON_ERROR_NONE;
    }
}
