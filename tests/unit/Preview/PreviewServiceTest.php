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
use InvalidArgumentException;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScope;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Model\User;

class PreviewServiceTest extends Unit
{
    private const CONFIG = 'car-import';

    private const CHANGE_SET = '01a08c7b-de2d-717b-93de-8aaee94a65a9';

    private const OTHER_CHANGE_SET = '0198f2c1-4b7a-7c3d-9e5f-1a2b3c4d5e6f';

    private const CONTEXT = 'agent-session:7/x';

    private const LIVE_PATH = 'car-import/22.import';

    private const SCOPED_PREFIX = 'car-import/_cs-';

    private const SCOPED_PATH = self::SCOPED_PREFIX . self::CHANGE_SET . '-22.import';

    private PreviewService $service;

    protected function _before(): void
    {
        $this->service = new PreviewService($this->createStub(FilesystemOperator::class));
    }

    public function testALivePreviewIsKeyedByConfigurationAndUser(): void
    {
        static::assertSame(self::LIVE_PATH, $this->service->getPreviewFilePath(self::CONFIG, $this->user(22)));
    }

    /** beside the live one, in the same directory — never over it, and gone with the configuration */
    public function testAScopedPreviewIsKeyedByChangeSetToo(): void
    {
        static::assertSame(
            self::SCOPED_PATH,
            $this->service->getPreviewFilePath(
                self::CONFIG,
                $this->user(22),
                new PreviewScope(self::CHANGE_SET)
            )
        );
    }

    /** an agent's change set is visible only in its context, so the name keeps it for the sweep */
    public function testAScopedPreviewKeepsItsContextInTheName(): void
    {
        $path = $this->service->getPreviewFilePath(
            self::CONFIG,
            $this->user(22),
            new PreviewScope(self::CHANGE_SET, self::CONTEXT)
        );

        static::assertSame(
            self::SCOPED_PREFIX . self::CHANGE_SET . '.' . bin2hex(self::CONTEXT) . '-22.import',
            $path
        );
        static::assertEquals(
            new PreviewScope(self::CHANGE_SET, self::CONTEXT),
            PreviewService::scopeOf($path)
        );
    }

    public function testAScopeIsReadBackFromItsFileName(): void
    {
        static::assertEquals(
            new PreviewScope(self::CHANGE_SET),
            PreviewService::scopeOf(self::SCOPED_PATH)
        );
        static::assertNull(PreviewService::scopeOf(self::LIVE_PATH));
        static::assertNull(PreviewService::scopeOf('car-import/_cs-x.zz-22.import'));
        static::assertNull(PreviewService::scopeOf('car-import/notes.txt'));
    }

    public function testClosedScopesAreRemovedAndTheRestIsKept(): void
    {
        $open = self::SCOPED_PATH;
        $closed = self::SCOPED_PREFIX . self::OTHER_CHANGE_SET . '-22.import';
        $closedOtherUser = self::SCOPED_PREFIX . self::OTHER_CHANGE_SET . '-23.import';
        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())->method('listContents')->with('', true)->willReturn(new DirectoryListing([
            new DirectoryAttributes(self::CONFIG),
            new FileAttributes(self::LIVE_PATH),
            new FileAttributes($open),
            new FileAttributes($closed),
            new FileAttributes($closedOtherUser),
        ]));
        $deleted = [];
        $storage->method('delete')->willReturnCallback(static function (string $path) use (&$deleted): void {
            $deleted[] = $path;
        });
        $asked = [];

        $removed = (new PreviewService($storage))->removeClosedScopes(
            static function (PreviewScope $scope) use (&$asked): bool {
                $asked[] = $scope->changeSetId;

                return $scope->changeSetId === self::CHANGE_SET;
            }
        );

        static::assertSame(2, $removed);
        static::assertSame([$closed, $closedOtherUser], $deleted);
        // once per change set, however many reviewers previewed it
        static::assertSame([self::CHANGE_SET, self::OTHER_CHANGE_SET], $asked);
    }

    /**
     * @dataProvider traversals
     */
    public function testAPathShapedNameOrScopeIsRefused(string $name, ?string $scope): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->getPreviewFilePath($name, $this->user(22), $scope === null ? null : new PreviewScope($scope));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function traversals(): iterable
    {
        yield 'parent directory in the name' => ['../etc', null];
        yield 'slash in the name' => ['car/import', null];
        yield 'empty name' => ['', null];
        yield 'parent directory in the scope' => [self::CONFIG, '../22'];
        yield 'slash in the scope' => [self::CONFIG, 'a/b'];
    }

    private function user(int $id): User
    {
        $user = new User();
        $user->setId($id);

        return $user;
    }
}
