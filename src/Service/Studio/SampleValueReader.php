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

namespace Pimcore\Bundle\DataImporterBundle\Service\Studio;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion\SampleValuePicker;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Unreadable rows cost the agent its samples, not the suggestions; a refusal stays a refusal.
 *
 * @internal
 */
final readonly class SampleValueReader
{
    public function __construct(
        private SampleValuePicker $sampleValuePicker,
        private PreviewRowsServiceInterface $previewRows,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed>|null $currentConfig
     * @param list<string> $columnIndexes
     *
     * @return array<string, list<string>>
     */
    public function read(string $configName, ?array $currentConfig, array $columnIndexes): array
    {
        try {
            return $this->sampleValuePicker->pick(
                $this->previewRows->read($configName, $currentConfig),
                $columnIndexes
            );
        } catch (ForbiddenException | NotFoundHttpException | AccessDeniedHttpException $refusal) {
            throw $refusal;
        } catch (InvalidConfigurationException | InvalidInputException $exception) {
            $this->logger->info('Mapping suggestions: no sample values', ['exception' => $exception]);
        } catch (Throwable $exception) {
            $this->logger->warning('Mapping suggestions: no sample values', ['exception' => $exception]);
        }

        return [];
    }
}
