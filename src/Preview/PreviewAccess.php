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

namespace Pimcore\Bundle\DataImporterBundle\Preview;

use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataHubBundle\Proposal\StoredConfigurationsInterface;
use Pimcore\Bundle\DataImporterBundle\Installer;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\User;
use function sprintf;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The gate for a preview of a configuration.
 *
 * Unscoped, the stored configuration answers under the permission the action needs. Scoped, the
 * scope must name a change set the user may read that holds this configuration: reading the
 * stored one is then enough, since the preview data is kept apart from the live preview, and a
 * configuration only proposed so far is accepted. Any other scope reads as not found. Fetching
 * through a loader the stored configuration does not have is what approving the change set would
 * do, so it needs what approving needs.
 *
 * @internal
 */
final readonly class PreviewAccess
{
    public function __construct(
        private StoredConfigurationsInterface $configurations,
        private PreviewScopeResolverInterface $scopes,
        private SecurityServiceInterface $security,
    ) {
    }

    /**
     * @return array<string, mixed>|null a scoped preview's configuration: the proposal over the
     *                                   stored sections it leaves alone; null when unscoped
     *
     * @throws NotFoundHttpException
     * @throws ForbiddenException
     */
    public function authorize(string $name, string $permission, ?PreviewScope $scope): ?array
    {
        $stored = $this->configurations->find($name);

        if ($scope === null) {
            $this->assertAllowed($name, $stored, $permission);

            return null;
        }

        $proposal = $this->scopes->proposal($name, $scope);
        if ($proposal === null) {
            throw $this->notFound($name);
        }

        if ($stored === null) {
            return $proposal;
        }

        $this->assertAllowed($name, $stored, PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ);

        return $proposal + $stored->getConfiguration();
    }

    /**
     * The gate for copying preview data: the configuration's loader fetches it.
     *
     * @return array<string, mixed>|null as authorize()
     *
     * @throws NotFoundHttpException
     * @throws ForbiddenException
     */
    public function authorizeFetch(string $name, ?PreviewScope $scope): ?array
    {
        $update = PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_UPDATE;
        $configuration = $this->authorize($name, $update, $scope);
        if ($configuration === null) {
            return null;
        }

        $stored = $this->configurations->find($name);
        if ($stored === null) {
            if (!$this->mayCreate()) {
                throw new ForbiddenException(sprintf('Access denied to configuration "%s"', $name));
            }

            return $configuration;
        }

        if (($configuration['loaderConfig'] ?? null) !== ($stored->getConfiguration()['loaderConfig'] ?? null)) {
            $this->assertAllowed($name, $stored, $update);
        }

        return $configuration;
    }

    /** as the Data Hub gates creating a configuration */
    private function mayCreate(): bool
    {
        $user = $this->security->getCurrentUser();

        return $user instanceof User
            && ($user->isAllowed(PermissionConstants::PLUGIN_DATA_IMPORTER_ADMIN)
                || $user->isAllowed(Installer::DATAHUB_ADAPTER_PERMISSION));
    }

    private function assertAllowed(string $name, ?Configuration $stored, string $permission): void
    {
        if ($stored === null) {
            throw $this->notFound($name);
        }

        if (!$stored->isAllowed($permission)) {
            throw new ForbiddenException(sprintf('Access denied to configuration "%s"', $name));
        }
    }

    private function notFound(string $name): NotFoundHttpException
    {
        return new NotFoundHttpException(sprintf('Configuration with name "%s" not found', $name));
    }
}
