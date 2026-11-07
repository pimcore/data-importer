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

use PhpOffice\PhpSpreadsheet\IOFactory;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\SourceFileReader;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewEventApplier;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\ConfigurationPermissionTrait;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CurrentUserResolverTrait;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;

/**
 * @internal
 */
final readonly class PreviewRowsService implements PreviewRowsServiceInterface
{
    use ConfigurationPermissionTrait;
    use CurrentUserResolverTrait;

    private const string FULLY_LOADED_FORMAT = 'xlsx';

    // about 5,000 rows of 20 columns; see fitsInMemory()
    private const int MAX_WORKBOOK_CELLS = 100000;

    public function __construct(
        private PreviewService $previewService,
        private SecurityServiceInterface $securityService, // NOSONAR php:S1068 read by CurrentUserResolverTrait
        private ConfigurationPreparationService $configurationPreparationService,
        private PreviewEventApplier $previewEventApplier,
        private SourceFileReader $sourceFileReader,
        private ConfigurationLookupInterface $configurations,
        private int $maxWorkbookCells = self::MAX_WORKBOOK_CELLS
    ) {
    }

    public function read(string $configName, ?array $currentConfig): iterable
    {
        $this->checkConfigurationPermission(
            $this->configurations->getByName($configName),
            $configName,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ
        );

        $path = $this->previewService->getLocalPreviewFile($configName, $this->resolveCurrentUser());
        if ($path === null) {
            return [];
        }

        $preparedConfig = $this->configurationPreparationService->prepareConfiguration($configName, $currentConfig);
        $interpreterConfig = $preparedConfig['interpreterConfig'];
        $path = $this->previewEventApplier->applyToPath($configName, $preparedConfig['processingConfig'], $path);
        if (!$this->fitsInMemory($interpreterConfig, $path)) {
            return [];
        }

        return $this->sourceFileReader->readRows($interpreterConfig, $path);
    }

    /**
     * A workbook loads whole, before any row limit applies, and running out of memory cannot be caught. Its size is
     * read without loading a cell. Every other format that reads rows reads them one at a time.
     *
     * @param array<string, mixed> $interpreterConfig
     */
    private function fitsInMemory(array $interpreterConfig, string $path): bool
    {
        if (($interpreterConfig['type'] ?? null) !== self::FULLY_LOADED_FORMAT) {
            return true;
        }

        $cells = 0;
        foreach (IOFactory::createReaderForFile($path)->listWorksheetInfo($path) as $sheet) {
            $cells += $sheet['totalRows'] * $sheet['totalColumns'];
        }

        return $cells <= $this->maxWorkbookCells;
    }
}
