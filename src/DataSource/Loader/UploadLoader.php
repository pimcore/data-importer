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

namespace Pimcore\Bundle\DataImporterBundle\DataSource\Loader;

use League\Flysystem\FilesystemOperator;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Helper\TemporaryFileHelperTrait;

class UploadLoader implements DataLoaderInterface
{
    use TemporaryFileHelperTrait;

    /**
     * @var string
     */
    protected $uploadFilePath;

    /**
     * @var string
     */
    protected $temporaryFile = null;

    protected FilesystemOperator $pimcoreDataImporterUploadStorage;

    public function __construct(FilesystemOperator $pimcoreDataImporterUploadStorage)
    {
        $this->pimcoreDataImporterUploadStorage = $pimcoreDataImporterUploadStorage;
    }

    public function loadData(): string
    {
        if ($this->pimcoreDataImporterUploadStorage->fileExists($this->uploadFilePath)) {
            $stream = $this->pimcoreDataImporterUploadStorage->readStream($this->uploadFilePath);
            $this->temporaryFile = self::getTemporaryFileFromStream($stream, true);

            return $this->temporaryFile;
        }

        throw new InvalidConfigurationException('No file uploaded for import.');
    }

    public function setSettings(array $settings): void
    {
        $this->uploadFilePath = $settings['uploadFilePath'];
    }

    public function cleanup(): void
    {
        unlink($this->temporaryFile);
    }
}
