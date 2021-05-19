<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\Preview;

use League\Flysystem\FilesystemOperator;
use Pimcore\Bundle\DataHubBundle\Configuration\Dao;
use Pimcore\Helper\TemporaryFileHelperTrait;
use Pimcore\Model\User;

class PreviewService
{
    use TemporaryFileHelperTrait;

    /**
     * @var FilesystemOperator
     */
    protected FilesystemOperator $pimcoreDataImporterPreviewStorage;

    /**
     * @param FilesystemOperator $pimcoreDataImporterPreviewStorage
     */
    public function __construct(FilesystemOperator $pimcoreDataImporterPreviewStorage)
    {
        $this->pimcoreDataImporterPreviewStorage = $pimcoreDataImporterPreviewStorage;
    }

    public function writePreviewFile(string $configName, string $sourcePath, User $user)
    {
        $target = $this->getPreviewFilePath($configName, $user);
        $this->pimcoreDataImporterPreviewStorage->write($target, file_get_contents($sourcePath));
    }

    /**
     * @param string $configName
     *
     * @return string
     *
     * @throws \Exception
     */
    protected function getPreviewFilePath(string $configName, User $user): string
    {
        $configuration = Dao::getByName($configName);
        if (!$configuration) {
            throw new \Exception('Configuration ' . $configName . ' does not exist.');
        }

        $filePath = $configuration->getName() . '/' . $user->getId() . '.import';

        return $filePath;
    }

    public function getLocalPreviewFile(string $configName, User $user): ?string
    {
        $filePath = $this->getPreviewFilePath($configName, $user);

        if ($this->pimcoreDataImporterPreviewStorage->fileExists($filePath)) {
            $stream = $this->pimcoreDataImporterPreviewStorage->readStream($filePath);

            return self::getLocalFileFromStream($stream);
        }

        return null;
    }
}
