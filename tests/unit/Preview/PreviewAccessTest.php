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
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataHubBundle\Proposal\StoredConfigurationsInterface;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewAccess;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScope;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScopeResolverInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PreviewAccessTest extends Unit
{
    private const string UPDATE = PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_UPDATE;

    private const string READ = PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ;

    private const string CHANGE_SET = '01a08c7b-de2d-717b-93de-8aaee94a65a9';

    public function testAnUnscopedPreviewNeedsThePermissionOfItsAction(): void
    {
        $access = $this->access($this->stored(['update' => true]), null);

        static::assertNull($access->authorize('car-import', self::UPDATE, null));
    }

    public function testAnUnscopedPreviewWithoutThePermissionIsForbidden(): void
    {
        $access = $this->access($this->stored(['read' => true, 'update' => false]), null);

        $this->expectException(ForbiddenException::class);
        $access->authorize('car-import', self::UPDATE, null);
    }

    public function testAnUnscopedPreviewOfANameNothingIsStoredUnderIsNotFound(): void
    {
        $access = $this->access(null, null);

        $this->expectException(NotFoundHttpException::class);
        $access->authorize('car-import', self::READ, null);
    }

    /** a scope no readable change set of this configuration answers to widens nothing */
    public function testAScopeNamingNoChangeSetOfTheConfigurationIsNotFound(): void
    {
        $access = $this->access($this->stored(['read' => true, 'update' => true]), null);

        $this->expectException(NotFoundHttpException::class);
        $access->authorize('car-import', self::UPDATE, new PreviewScope(self::CHANGE_SET));
    }

    public function testAScopeNamingNoChangeSetDoesNotAcceptAnUnstoredName(): void
    {
        $access = $this->access(null, null);

        $this->expectException(NotFoundHttpException::class);
        $access->authorize('car-import', self::READ, new PreviewScope(self::CHANGE_SET));
    }

    public function testAChangeSetUnderReviewNeedsOnlyReadingTheStoredConfiguration(): void
    {
        $stored = $this->stored(['read' => true, 'update' => false], [
            'loaderConfig' => ['type' => 'sftp'],
            'interpreterConfig' => ['type' => 'csv'],
        ]);
        $access = $this->access($stored, ['interpreterConfig' => ['type' => 'json']]);

        static::assertSame(
            ['interpreterConfig' => ['type' => 'json'], 'loaderConfig' => ['type' => 'sftp']],
            $access->authorize('car-import', self::UPDATE, new PreviewScope(self::CHANGE_SET))
        );
    }

    public function testAChangeSetUnderReviewStillNeedsReadingTheStoredConfiguration(): void
    {
        $access = $this->access($this->stored(['read' => false]), ['interpreterConfig' => ['type' => 'json']]);

        $this->expectException(ForbiddenException::class);
        $access->authorize('car-import', self::READ, new PreviewScope(self::CHANGE_SET));
    }

    public function testAConfigurationOnlyProposedSoFarPreviewsAsProposed(): void
    {
        $access = $this->access(null, ['interpreterConfig' => ['type' => 'xml']]);

        static::assertSame(
            ['interpreterConfig' => ['type' => 'xml']],
            $access->authorize('car-import', self::UPDATE, new PreviewScope(self::CHANGE_SET))
        );
    }

    public function testTheScopeIsResolvedWithItsContext(): void
    {
        $scope = new PreviewScope(self::CHANGE_SET, 'agent-session:7');
        $resolver = $this->createMock(PreviewScopeResolverInterface::class);
        $resolver->expects($this->once())
            ->method('proposal')
            ->with('car-import', $scope)
            ->willReturn([]);

        $access = new PreviewAccess($this->configurations(null), $resolver);

        static::assertSame([], $access->authorize('car-import', self::READ, $scope));
    }

    /**
     * @param array<string, bool> $allowed
     */
    private function stored(array $allowed, array $configuration = []): Configuration
    {
        $stored = $this->createStub(Configuration::class);
        $stored->method('isAllowed')->willReturnCallback(
            static fn (string $permission): bool => $allowed[$permission] ?? false
        );
        $stored->method('getConfiguration')->willReturn($configuration);

        return $stored;
    }

    private function configurations(?Configuration $stored): StoredConfigurationsInterface
    {
        $configurations = $this->createStub(StoredConfigurationsInterface::class);
        $configurations->method('find')->willReturn($stored);

        return $configurations;
    }

    private function access(?Configuration $stored, ?array $proposal): PreviewAccess
    {
        $resolver = $this->createStub(PreviewScopeResolverInterface::class);
        $resolver->method('proposal')->willReturn($proposal);

        return new PreviewAccess($this->configurations($stored), $resolver);
    }
}
