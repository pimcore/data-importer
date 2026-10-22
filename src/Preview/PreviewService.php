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

use function basename;
use function bin2hex;
use function hex2bin;
use League\Flysystem\FilesystemOperator;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationName;
use Pimcore\Helper\TemporaryFileHelperTrait;
use Pimcore\Model\User;
use function preg_match;
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

    private const string SCOPED_PREFIX = '_cs-';

    /** a scoped file name: change set, the context hex-encoded where it has one, user id */
    private const string SCOPED_FILE = '/^_cs-([A-Za-z0-9][A-Za-z0-9_-]*?)(?:\.([0-9a-f]+))?-(\d+)\.import$/';

    public function writePreviewFile(
        string $configName,
        string $sourcePath,
        User $user,
        ?PreviewScope $scope = null
    ): void {
        $target = $this->getPreviewFilePath($configName, $user, $scope);
        $this->pimcoreDataImporterPreviewStorage->write($target, file_get_contents($sourcePath));
    }

    /**
     * Where a user's preview of a configuration lives. A scope — a change set under review —
     * keeps its own file beside the live one, in the same directory, so a proposal never
     * overwrites the reviewer's preview of the stored configuration and goes with the
     * configuration when that is deleted. The scoped name cannot collide with a live one:
     * user ids are numeric. It carries the scope's context, so a sweep can ask whether the
     * change set is still open without the request that made it.
     *
     * The name and the scope become path segments, so both are checked here rather than
     * resolved against the configuration store: a proposal may name a configuration that does
     * not exist yet.
     */
    public function getPreviewFilePath(string $configName, User $user, ?PreviewScope $scope = null): string
    {
        if (!ConfigurationName::isValid($configName)) {
            throw new \InvalidArgumentException(sprintf('"%s" cannot name a preview.', $configName));
        }
        if ($scope === null) {
            return sprintf('%s/%s.import', $configName, $user->getId());
        }
        if (!ConfigurationName::isValid($scope->changeSetId)) {
            throw new \InvalidArgumentException(sprintf('"%s" cannot scope a preview.', $scope->changeSetId));
        }

        return sprintf(
            '%s/%s%s%s-%s.import',
            $configName,
            self::SCOPED_PREFIX,
            $scope->changeSetId,
            $scope->contextRef !== null ? '.' . bin2hex($scope->contextRef) : '',
            $user->getId(),
        );
    }

    /**
     * The scope a stored preview file was made in; null for a live preview or a foreign file.
     */
    public static function scopeOf(string $path): ?PreviewScope
    {
        if (preg_match(self::SCOPED_FILE, basename($path), $match) !== 1) {
            return null;
        }

        $context = $match[2] !== '' ? hex2bin($match[2]) : null;

        return $context === false ? null : new PreviewScope($match[1], $context);
    }

    /**
     * Removes the scoped previews whose change set is no longer open: a review that ended,
     * however it ended, leaves no preview behind.
     *
     * @param callable(PreviewScope): bool $isOpen
     *
     * @return int the files removed
     */
    public function removeClosedScopes(callable $isOpen): int
    {
        $open = [];
        $removed = 0;
        foreach ($this->pimcoreDataImporterPreviewStorage->listContents('', true) as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $scope = self::scopeOf($item->path());
            if ($scope === null) {
                continue;
            }

            $key = $scope->changeSetId . "\0" . $scope->contextRef;
            $open[$key] ??= $isOpen($scope);
            if (!$open[$key]) {
                $this->pimcoreDataImporterPreviewStorage->delete($item->path());
                ++$removed;
            }
        }

        return $removed;
    }

    public function getLocalPreviewFile(string $configName, User $user, ?PreviewScope $scope = null): ?string
    {
        $filePath = $this->getPreviewFilePath($configName, $user, $scope);

        if ($this->pimcoreDataImporterPreviewStorage->fileExists($filePath)) {
            $stream = $this->pimcoreDataImporterPreviewStorage->readStream($filePath);

            return self::getLocalFileFromStream($stream);
        }

        return null;
    }
}
