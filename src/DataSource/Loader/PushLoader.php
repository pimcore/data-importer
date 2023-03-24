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

namespace Pimcore\Bundle\DataImporterBundle\DataSource\Loader;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Symfony\Component\Filesystem\Filesystem;

class PushLoader implements DataLoaderInterface
{
    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var bool
     */
    protected $ignoreNotEmptyQueue = false;

    /**
     * @var string
     */
    protected $importFilePath;

    public function __construct(
        protected Filesystem $filesystem
    ) {
    }

    public function loadData(): string
    {
        $folder = PIMCORE_PRIVATE_VAR . '/tmp/datahub/dataimporter/push-loader/';
        $this->filesystem->mkdir($folder, 0775);

        $this->importFilePath = $folder . uniqid('push-import-');

        $content = file_get_contents('php://input');
        file_put_contents($this->importFilePath, $content);

        return $this->importFilePath;
    }

    public function cleanup(): void
    {
        unlink($this->importFilePath);
    }

    public function setSettings(array $settings): void
    {
        if (empty($settings['apiKey'])) {
            throw new InvalidConfigurationException('Empty API Key.');
        }
        $this->apiKey = $settings['apiKey'];

        $this->ignoreNotEmptyQueue = $settings['ignoreNotEmptyQueue'] ?? false;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function isIgnoreNotEmptyQueue(): bool
    {
        return $this->ignoreNotEmptyQueue;
    }
}
