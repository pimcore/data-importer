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
use League\Flysystem\FilesystemOperator;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Model\User;

class PreviewServiceTest extends Unit
{
    private PreviewService $service;

    protected function _before(): void
    {
        $this->service = new PreviewService($this->createStub(FilesystemOperator::class));
    }

    public function testALivePreviewIsKeyedByConfigurationAndUser(): void
    {
        static::assertSame('car-import/22.import', $this->service->getPreviewFilePath('car-import', $this->user(22)));
    }

    /** beside the live one, in the same directory — never over it, and gone with the configuration */
    public function testAScopedPreviewIsKeyedByChangeSetToo(): void
    {
        static::assertSame(
            'car-import/_cs-01a08c7b-de2d-717b-93de-8aaee94a65a9-22.import',
            $this->service->getPreviewFilePath('car-import', $this->user(22), '01a08c7b-de2d-717b-93de-8aaee94a65a9')
        );
    }

    /**
     * @dataProvider traversals
     */
    public function testAPathShapedNameOrScopeIsRefused(string $name, ?string $scope): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->getPreviewFilePath($name, $this->user(22), $scope);
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function traversals(): iterable
    {
        yield 'parent directory in the name' => ['../etc', null];
        yield 'slash in the name' => ['car/import', null];
        yield 'empty name' => ['', null];
        yield 'parent directory in the scope' => ['car-import', '../22'];
        yield 'slash in the scope' => ['car-import', 'a/b'];
    }

    private function user(int $id): User
    {
        $user = new User();
        $user->setId($id);

        return $user;
    }
}
