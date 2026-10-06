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

namespace Pimcore\Bundle\DataImporterBundle\Preview;

use League\Flysystem\FilesystemOperator;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationName;
use Pimcore\Helper\TemporaryFileHelperTrait;
use Pimcore\Model\User;
use function sprintf;

/**
 * @internal
 */
final class PreviewService
{
    use TemporaryFileHelperTrait;

    public function __construct(
        private readonly FilesystemOperator $pimcoreDataImporterPreviewStorage,
    ) {
    }

    public function writePreviewFile(string $configName, string $sourcePath, User $user, ?string $scope = null): void
    {
        $target = $this->getPreviewFilePath($configName, $user, $scope);
        $this->pimcoreDataImporterPreviewStorage->write($target, file_get_contents($sourcePath));
    }

    /**
     * Where a user's preview of a configuration lives. A scope — a change set under review —
     * keeps its own file beside the live one, in the same directory, so a proposal never
     * overwrites the reviewer's preview of the stored configuration and goes with the
     * configuration when that is deleted. The scoped name cannot collide with a live one:
     * user ids are numeric.
     *
     * The name and the scope become path segments, so both are checked here rather than
     * resolved against the configuration store: a proposal may name a configuration that does
     * not exist yet.
     */
    public function getPreviewFilePath(string $configName, User $user, ?string $scope = null): string
    {
        if (!ConfigurationName::isValid($configName)) {
            throw new \InvalidArgumentException(sprintf('"%s" cannot name a preview.', $configName));
        }
        if ($scope !== null && !ConfigurationName::isValid($scope)) {
            throw new \InvalidArgumentException(sprintf('"%s" cannot scope a preview.', $scope));
        }

        return $scope === null
            ? sprintf('%s/%s.import', $configName, $user->getId())
            : sprintf('%s/_cs-%s-%s.import', $configName, $scope, $user->getId());
    }

    public function getLocalPreviewFile(string $configName, User $user, ?string $scope = null): ?string
    {
        $filePath = $this->getPreviewFilePath($configName, $user, $scope);

        if ($this->pimcoreDataImporterPreviewStorage->fileExists($filePath)) {
            $stream = $this->pimcoreDataImporterPreviewStorage->readStream($filePath);

            return self::getLocalFileFromStream($stream);
        }

        return null;
    }
}
