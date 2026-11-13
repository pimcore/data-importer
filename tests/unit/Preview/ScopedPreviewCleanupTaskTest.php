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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Preview;

use Codeception\Test\Unit;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use Pimcore\Bundle\DataImporterBundle\Maintenance\ScopedPreviewCleanupTask;
use Pimcore\Bundle\DataImporterBundle\Preview\NoPreviewScopeResolver;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScope;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScopeResolverInterface;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;

class ScopedPreviewCleanupTaskTest extends Unit
{
    private const string OPEN = 'car-import/_cs-01a08c7b-de2d-717b-93de-8aaee94a65a9-22.import';

    private const string CLOSED = 'car-import/_cs-0198f2c1-4b7a-7c3d-9e5f-1a2b3c4d5e6f-22.import';

    public function testThePreviewsOfChangeSetsTheResolverNoLongerSeesOpenAreRemoved(): void
    {
        $scopes = $this->createStub(PreviewScopeResolverInterface::class);
        $scopes->method('isOpen')->willReturnCallback(
            static fn (PreviewScope $scope): bool => $scope->changeSetId === '01a08c7b-de2d-717b-93de-8aaee94a65a9'
        );

        static::assertSame([self::CLOSED], $this->sweep($scopes));
    }

    /** without Change Control no change set is open, so no scoped preview outlives the sweep */
    public function testWithoutChangeControlEveryScopedPreviewIsRemoved(): void
    {
        $scopes = new NoPreviewScopeResolver();

        static::assertNull($scopes->proposal('car-import', new PreviewScope('01a08c7b-de2d-717b-93de-8aaee94a65a9')));
        static::assertSame([self::OPEN, self::CLOSED], $this->sweep($scopes));
    }

    /** @return list<string> the paths deleted */
    private function sweep(PreviewScopeResolverInterface $scopes): array
    {
        $storage = $this->createStub(FilesystemOperator::class);
        $storage->method('listContents')->willReturn(new DirectoryListing([
            new FileAttributes('car-import/22.import'),
            new FileAttributes(self::OPEN),
            new FileAttributes(self::CLOSED),
        ]));
        $deleted = [];
        $storage->method('delete')->willReturnCallback(static function (string $path) use (&$deleted): void {
            $deleted[] = $path;
        });

        (new ScopedPreviewCleanupTask(new PreviewService($storage), $scopes))->execute();

        return $deleted;
    }
}
