<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Tests\Cleanup;

use Codeception\Test\Unit;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\Cleanup\CleanupStrategyFactory;
use Pimcore\Bundle\DataImporterBundle\Cleanup\CleanupStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Cleanup\DeleteStrategy;
use Pimcore\Bundle\DataImporterBundle\Cleanup\UnpublishStrategy;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Bundle\DataImporterBundle\Resolver\Load\LoadStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Resolver;
use Pimcore\Bundle\DataImporterBundle\Resolver\ResolverFactory;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Element\ElementInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CleanupElementTest extends Unit
{
    private const CONFIG = 'pme_cleanup';

    public function testAlreadyUnpublishedElementIsNeitherSavedNorLogged(): void
    {
        $element = $this->createMock(Concrete::class);
        $element->method('isPublished')->willReturn(false);
        $element->expects($this->never())->method('save');

        $appLogger = $this->createMock(ApplicationLogger::class);
        $appLogger->expects($this->never())->method('info');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        $this->runCleanup($appLogger, $logger, new UnpublishStrategy(), 'unpublish', $element);
    }

    public function testPublishedElementIsUnpublishedAndLogged(): void
    {
        $element = $this->createMock(Concrete::class);
        $element->method('isPublished')->willReturn(true);
        $element->expects($this->once())->method('save');

        $appLogger = $this->createMock(ApplicationLogger::class);
        $appLogger->expects($this->once())->method('info');

        $this->runCleanup(
            $appLogger,
            $this->createMock(LoggerInterface::class),
            new UnpublishStrategy(),
            'unpublish',
            $element
        );
    }

    public function testDisabledInfoLogsKeepTheCleanupOutOfTheApplicationLog(): void
    {
        $element = $this->createMock(Concrete::class);
        $element->expects($this->once())->method('delete');

        $appLogger = $this->createMock(ApplicationLogger::class);
        $appLogger->expects($this->never())->method('info');

        $this->runCleanup(
            $appLogger,
            $this->createMock(LoggerInterface::class),
            new DeleteStrategy(),
            'delete',
            $element,
            ['disableInfoLogs' => true]
        );
    }

    public function testStrategyWithoutConditionalInterfaceIsAlwaysRunAndLogged(): void
    {
        $strategy = $this->createMock(CleanupStrategyInterface::class);
        $strategy->expects($this->once())->method('doCleanup');

        $appLogger = $this->createMock(ApplicationLogger::class);
        $appLogger->expects($this->once())->method('info');

        $this->runCleanup(
            $appLogger,
            $this->createMock(LoggerInterface::class),
            $strategy,
            'custom',
            $this->createMock(Concrete::class)
        );
    }

    public function testLoadErrorIsLoggedWithoutAnElement(): void
    {
        $appLogger = $this->createMock(ApplicationLogger::class);
        $appLogger->expects($this->once())->method('error')
            ->with(
                'Error cleaning up element: load failed',
                $this->callback(static fn (array $context): bool => $context['relatedObject'] === null)
            );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('Error cleaning up element: load failed');

        $this->runCleanup($appLogger, $logger, new DeleteStrategy(), 'delete', new RuntimeException('load failed'));
    }

    private function runCleanup(
        ApplicationLogger $appLogger,
        LoggerInterface $logger,
        CleanupStrategyInterface $strategy,
        string $type,
        ElementInterface|RuntimeException $loaded,
        array $loggingConfig = []
    ): void {
        $service = new ImportProcessingService(
            (new ReflectionClass(QueueService::class))->newInstanceWithoutConstructor(),
            (new ReflectionClass(MappingConfigurationFactory::class))->newInstanceWithoutConstructor(),
            (new ReflectionClass(ResolverFactory::class))->newInstanceWithoutConstructor(),
            new CleanupStrategyFactory([$type => $strategy]),
            $appLogger,
            $this->createMock(EventDispatcherInterface::class),
        );
        $service->setLogger($logger);
        (new ReflectionProperty(ImportProcessingService::class, 'loggingConfigCache'))
            ->setValue($service, [self::CONFIG => $loggingConfig]);

        $loadStrategy = $this->createMock(LoadStrategyInterface::class);
        if ($loaded instanceof RuntimeException) {
            $loadStrategy->method('loadElementByIdentifier')->willThrowException($loaded);
        } else {
            $loadStrategy->method('loadElementByIdentifier')->willReturn($loaded);
        }
        $resolver = (new ReflectionClass(Resolver::class))->newInstanceWithoutConstructor();
        $resolver->setLoadingStrategy($loadStrategy);

        (new ReflectionMethod(ImportProcessingService::class, 'cleanupElement'))
            ->invoke($service, self::CONFIG, '42', $resolver, ['doCleanup' => true, 'strategy' => $type]);
    }
}
