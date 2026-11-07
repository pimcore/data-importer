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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\MappingSuggestion;

use Codeception\Test\Unit;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\CsvFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\XlsxFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\Event\PreQueueRowEvent;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\SourceFileReader;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewEventApplier;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\ConfigurationLookupInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\PreviewRowsService;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\User;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PreviewRowsServiceTest extends Unit
{
    private const string CONFIG_NAME = 'products';

    private const int USER_ID = 7;

    private const string SHEET_NAME = 'Products';

    /** @var list<string> */
    private array $files = [];

    protected function _after(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    private function service(
        PreviewService $previewService,
        ?Configuration $configuration,
        int $maxWorkbookCells = 100,
        int $maxWorkbookBytes = 1024 * 1024,
        EventDispatcher $eventDispatcher = new EventDispatcher()
    ): PreviewRowsService {
        $user = new User();
        $user->setId(self::USER_ID);

        $security = $this->createStub(SecurityServiceInterface::class);
        $security->method('getCurrentUser')->willReturn($user);

        $configurations = $this->createStub(ConfigurationLookupInterface::class);
        $configurations->method('getByName')->willReturn($configuration);

        return new PreviewRowsService(
            $previewService,
            $security,
            new ConfigurationPreparationService(),
            new PreviewEventApplier($eventDispatcher),
            new SourceFileReader(new InterpreterFactory([
                // their queue and delta check are final and reading rows uses neither
                'csv' => (new ReflectionClass(CsvFileInterpreter::class))->newInstanceWithoutConstructor(),
                'xlsx' => (new ReflectionClass(XlsxFileInterpreter::class))->newInstanceWithoutConstructor(),
            ])),
            $configurations,
            $maxWorkbookCells,
            $maxWorkbookBytes
        );
    }

    private function readableConfiguration(): Configuration
    {
        $configuration = $this->createStub(Configuration::class);
        $configuration->method('isAllowed')->willReturn(true);

        return $configuration;
    }

    private function previewFile(?string $path): PreviewService
    {
        $previewService = $this->createStub(PreviewService::class);
        $previewService->method('getLocalPreviewFile')->willReturn($path);

        return $previewService;
    }

    public function testAConfigurationTheUserMayNotReadIsForbiddenAndNoFileIsRead(): void
    {
        $configuration = $this->createMock(Configuration::class);
        $configuration->expects($this->once())
            ->method('isAllowed')
            ->with(PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ)
            ->willReturn(false);

        $previewService = $this->createMock(PreviewService::class);
        $previewService->expects($this->never())->method('getLocalPreviewFile');

        $this->expectException(ForbiddenException::class);

        // an unsaved configuration in the request does not replace the check of the stored one
        $this->service($previewService, $configuration)->read(self::CONFIG_NAME, $this->csvConfig());
    }

    public function testAnUnknownConfigurationIsNotFoundAndNoFileIsRead(): void
    {
        $previewService = $this->createMock(PreviewService::class);
        $previewService->expects($this->never())->method('getLocalPreviewFile');

        $this->expectException(NotFoundHttpException::class);

        $this->service($previewService, null)->read(self::CONFIG_NAME, $this->csvConfig());
    }

    public function testNoPreviewFileGivesNoRows(): void
    {
        $service = $this->service($this->previewFile(null), $this->readableConfiguration());

        $this->assertSame([], $service->read(self::CONFIG_NAME, $this->csvConfig()));
    }

    public function testItReadsTheFileOfTheCurrentUserWithTheGivenConfiguration(): void
    {
        $previewService = $this->createMock(PreviewService::class);
        $previewService->expects($this->once())
            ->method('getLocalPreviewFile')
            ->with(
                self::CONFIG_NAME,
                $this->callback(static fn (User $user): bool => $user->getId() === self::USER_ID)
            )
            ->willReturn($this->writeFile("sku;color\nA-1;red\nA-2;blue\n", 'csv'));

        $rows = $this->service($previewService, $this->readableConfiguration())
            ->read(self::CONFIG_NAME, $this->csvConfig());

        $this->assertSame(
            [['sku' => 'A-1', 'color' => 'red'], ['sku' => 'A-2', 'color' => 'blue']],
            iterator_to_array($rows, false)
        );
    }

    public function testTheRowsAreThoseThePreviewShowsAfterItsRowListeners(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(PreQueueRowEvent::class, static function (PreQueueRowEvent $event): void {
            $row = $event->getOriginalRow();
            match ($row['sku']) {
                'A-1' => $event->setRows([['sku' => 'A-1', 'color' => 'RED', 'origin' => 'DE']]),
                'A-2' => $event->skipRow(),
                default => $event->setRows([
                    ['sku' => 'A-3a', 'color' => 'green'],
                    ['sku' => 'A-3b', 'color' => 'teal'],
                ]),
            };
        });
        $seen = [];
        $recordSeen = static function (PreQueueRowEvent $event) use (&$seen): void {
            $seen[] = [$event->getConfigName(), $event->isPreview()];
        };
        $eventDispatcher->addListener(PreQueueRowEvent::class, $recordSeen);

        $service = $this->service(
            $this->previewFile($this->writeFile("sku;color\nA-1;red\nA-2;blue\nA-3;green\n", 'csv')),
            $this->readableConfiguration(),
            eventDispatcher: $eventDispatcher
        );

        $this->assertSame(
            [
                // a listener's added column and rewritten value
                ['sku' => 'A-1', 'color' => 'RED', 'origin' => 'DE'],
                // a skipped row stays as the preview shows it
                ['sku' => 'A-2', 'color' => 'blue'],
                // a fan-out gives every resulting row
                ['sku' => 'A-3a', 'color' => 'green'],
                ['sku' => 'A-3b', 'color' => 'teal'],
            ],
            iterator_to_array($service->read(self::CONFIG_NAME, $this->csvConfig()), false)
        );
        $this->assertSame(array_fill(0, 3, [self::CONFIG_NAME, true]), $seen);
    }

    public function testAWorkbookWithinTheLimitGivesItsRows(): void
    {
        // 2 rows of 2 columns are 4 cells
        $service = $this->service(
            $this->previewFile($this->writeXlsx([['A-1', 'red'], ['A-2', 'blue']])),
            $this->readableConfiguration(),
            4
        );

        $this->assertSame(
            [['A-1', 'red'], ['A-2', 'blue']],
            iterator_to_array($service->read(self::CONFIG_NAME, $this->xlsxConfig()), false)
        );
    }

    public function testAWorkbookOverTheLimitIsNotLoaded(): void
    {
        $service = $this->service(
            $this->previewFile($this->writeXlsx([['A-1', 'red'], ['A-2', 'blue'], ['A-3', 'green']])),
            $this->readableConfiguration(),
            4
        );

        $this->assertSame([], $service->read(self::CONFIG_NAME, $this->xlsxConfig()));
    }

    public function testEverySheetCountsBecauseEverySheetIsLoaded(): void
    {
        $service = $this->service(
            $this->previewFile($this->writeXlsx([['A-1', 'red'], ['A-2', 'blue']], [['other']])),
            $this->readableConfiguration(),
            4
        );

        $this->assertSame([], $service->read(self::CONFIG_NAME, $this->xlsxConfig()));
    }

    public function testAWorkbookOfFewCellsButLongTextsIsNotLoaded(): void
    {
        $longText = str_repeat('x', 32000);
        $path = $this->writeXlsx([[$longText . '1', $longText . '2'], [$longText . '3', $longText . '4']]);

        // 4 cells are within the cell limit; their unpacked text is not within 100 KB
        $service = $this->service($this->previewFile($path), $this->readableConfiguration(), 4, 100 * 1024);

        $this->assertSame([], $service->read(self::CONFIG_NAME, $this->xlsxConfig()));
        $this->assertLessThan(100 * 1024, filesize($path), 'the packed file alone would pass');
    }

    public function testAWorkbookThatIsNoZipArchiveCountsWithItsSizeOnDisk(): void
    {
        // an old binary workbook, or a broken file, under the xlsx interpreter
        $path = $this->writeFile(str_repeat('x', 2048), 'xlsx');

        $service = $this->service($this->previewFile($path), $this->readableConfiguration(), 100, 1024);

        $this->assertSame([], $service->read(self::CONFIG_NAME, $this->xlsxConfig()));
    }

    /**
     * @return array<string, mixed>
     */
    private function csvConfig(): array
    {
        return [
            'interpreterConfig' => [
                'type' => 'csv',
                'settings' => [
                    'skipFirstRow' => true,
                    'saveHeaderName' => true,
                    'delimiter' => ';',
                    'enclosure' => '"',
                    'escape' => '\\',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function xlsxConfig(): array
    {
        return [
            'interpreterConfig' => [
                'type' => 'xlsx',
                'settings' => ['skipFirstRow' => false, 'sheetName' => self::SHEET_NAME],
            ],
        ];
    }

    /**
     * @param list<list<string>> $rows
     * @param list<list<string>>|null $otherSheet
     */
    private function writeXlsx(array $rows, ?array $otherSheet = null): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setTitle(self::SHEET_NAME)->fromArray($rows);
        if ($otherSheet !== null) {
            $spreadsheet->createSheet()->setTitle('Other')->fromArray($otherSheet);
        }

        $path = $this->writeFile('', 'xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function writeFile(string $content, string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'di_preview_rows_');
        $path = $base . '.' . $extension;
        rename($base, $path);
        file_put_contents($path, $content);
        $this->files[] = $path;

        return $path;
    }
}
