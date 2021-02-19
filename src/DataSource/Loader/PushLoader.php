<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

namespace Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Loader;

use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\File;

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

    public function loadData(): string
    {
        $folder = PIMCORE_PRIVATE_VAR . '/datahub_batchimport_tmp';
        File::mkdir($folder);

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

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * @return bool
     */
    public function isIgnoreNotEmptyQueue(): bool
    {
        return $this->ignoreNotEmptyQueue;
    }
}
