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
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Bundle\DataImporterBundle\Schema\ColumnHeadersResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\DataPreviewResponse;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Logger;
use Pimcore\Model\User;

/**
 * @internal
 */
final readonly class PreviewHydrator implements PreviewHydratorInterface
{
    public function __construct(
        private SecurityServiceInterface $securityService,
        private PreviewService $previewService,
        private InterpreterFactory $interpreterFactory
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
        if (!is_file($previewFilePath)) {
            return [];
        }

        try {
            $interpreter = $this->interpreterFactory->loadInterpreter(
                $name,
                $config['interpreterConfig'],
                $config['processingConfig']
            );
            $dataPreview = $interpreter->previewData($previewFilePath);
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

    /**
     * Resolve the current user, ensuring it is a Pimcore User instance.
     *
     * @throws EnvironmentException if the current user cannot be resolved
     */
    private function resolveCurrentUser(): User
    {
        $user = $this->securityService->getCurrentUser();

        if (!$user instanceof User) {
            throw new EnvironmentException('Could not resolve current user');
        }

        return $user;
    }
}
