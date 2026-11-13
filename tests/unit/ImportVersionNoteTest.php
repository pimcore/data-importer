<?php declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Closure;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\Cleanup\CleanupStrategyFactory;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfiguration;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Bundle\DataImporterBundle\Resolver\Load\LoadStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Location\LocationStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Publish\PublishStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Resolver;
use Pimcore\Bundle\DataImporterBundle\Resolver\ResolverFactory;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Version;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ImportVersionNoteTest extends Unit
{
    private const CONFIG_NAME = 'version-note-test';

    private const VERSION_NOTE = 'Imported by ERP feed';

    private bool $versioningDisabledBefore;

    protected function _before(): void
    {
        $this->versioningDisabledBefore = Version::$disabled;
        Version::enable();
    }

    protected function _after(): void
    {
        Version::$disabled = $this->versioningDisabledBefore;
    }

    public function testImportedElementIsSavedWithConfiguredVersionNote(): void
    {
        $element = $this->createElement();
        $element->expects($this->once())
            ->method('save')
            ->with(['versionNote' => self::VERSION_NOTE])
            ->willReturnSelf();

        $this->processRow($element, ['versionNote' => self::VERSION_NOTE]);
    }

    public function testChangedVersionNoteIsUsedByTheSameServiceInstance(): void
    {
        $service = $this->createService();

        $firstElement = $this->createElement();
        $firstElement->expects($this->once())
            ->method('save')
            ->with(['versionNote' => self::VERSION_NOTE])
            ->willReturnSelf();
        $this->processRow($firstElement, ['versionNote' => self::VERSION_NOTE], $service);

        $secondElement = $this->createElement();
        $secondElement->expects($this->once())
            ->method('save')
            ->with(['versionNote' => 'Changed note'])
            ->willReturnSelf();
        $this->processRow($secondElement, ['versionNote' => 'Changed note'], $service);
    }

    public function testImportedElementIsSavedWithoutParametersWhenNoVersionNoteIsConfigured(): void
    {
        $element = $this->createElement();
        $element->expects($this->once())
            ->method('save')
            ->with([])
            ->willReturnSelf();

        $this->processRow($element, []);
    }

    public function testVersionNoteDoesNotInterfereWithDisabledVersioning(): void
    {
        $versioningDisabledDuringSave = null;

        $element = $this->createElement();
        $element->expects($this->once())
            ->method('save')
            ->with(['versionNote' => self::VERSION_NOTE])
            ->willReturnCallback(function () use (&$versioningDisabledDuringSave, $element) {
                $versioningDisabledDuringSave = Version::$disabled;

                return $element;
            });

        $this->processRow($element, ['versionNote' => self::VERSION_NOTE, 'disableVersioning' => true]);

        static::assertTrue($versioningDisabledDuringSave, 'No version, and therefore no note, is written');
        static::assertFalse(Version::$disabled, 'Versioning is restored after the element was saved');
    }

    private function createElement(): ElementInterface&MockObject
    {
        $element = $this->createMock(ElementInterface::class);
        $element->method('setUserModification')->willReturnSelf();

        return $element;
    }

    private function createService(): ImportProcessingService
    {
        $service = new ImportProcessingService(
            new QueueService(),
            new MappingConfigurationFactory(new MappingConfiguration(), [], []),
            new ResolverFactory(new Resolver(), [], [], [], []),
            new CleanupStrategyFactory([]),
            $this->createStub(ApplicationLogger::class),
            $this->createStub(EventDispatcherInterface::class),
        );
        $service->setLogger(new NullLogger());

        return $service;
    }

    private function processRow(
        ElementInterface $element,
        array $processingConfig,
        ?ImportProcessingService $service = null,
    ): void {
        $service ??= $this->createService();
        $resolver = $this->createResolverLoading($element);
        $configName = self::CONFIG_NAME;

        Closure::bind(function () use ($configName, $processingConfig, $resolver): void {
            $this->loggingConfigCache[$configName] = ['disableInfoLogs' => true, 'disableErrorLogs' => true];
            $this->versioningConfigCache[$configName] = $processingConfig['disableVersioning'] ?? false;

            $this->processElement($configName, ['id-1'], $resolver, [], 0, $processingConfig);
        }, $service, ImportProcessingService::class)();
    }

    private function createResolverLoading(ElementInterface $element): Resolver
    {
        $loadingStrategy = $this->createStub(LoadStrategyInterface::class);
        $loadingStrategy->method('loadElement')->willReturn($element);

        $resolver = new Resolver();
        $resolver->setLoadingStrategy($loadingStrategy);
        $resolver->setLocationUpdateStrategy($this->createStub(LocationStrategyInterface::class));
        $resolver->setCreateLocationStrategy($this->createStub(LocationStrategyInterface::class));
        $resolver->setPublishingStrategy($this->createStub(PublishStrategyInterface::class));

        return $resolver;
    }
}
