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

namespace Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Interpreter;

use Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Resolver;
use Pimcore\Bundle\DataHubBatchImportBundle\Settings\PreviewData;
use Pimcore\Bundle\DataHubBatchImportBundle\Settings\SettingsAwareInterface;

interface InterpreterInterface extends SettingsAwareInterface
{
    /**
     * Check if file is valid
     *
     * @param string $path
     * @param bool $originalFilename
     *
     * @return bool
     */
    public function fileValid(string $path, bool $originalFilename = false): bool;

    /**
     * Load file, create queue entries for importing and element cleanup
     *
     * @param string $path
     *
     * @return bool
     */
    public function interpretFile(string $path): bool;

    /**
     * Read given file an extract preview data from it
     *
     * @param string $path
     * @param int $recordNumber
     * @param array $mappedColumns
     *
     * @return PreviewData
     */
    public function previewData(string $path, int $recordNumber = 0, array $mappedColumns = []): PreviewData;

    /**
     * Set name of current import configuration
     *
     * @param string $configName
     */
    public function setConfigName(string $configName): void;

    /**
     * Set current execution type
     *
     * @param string $executionType
     */
    public function setExecutionType(string $executionType): void;

    /**
     * Activate/Deactivate delta check
     *
     * @param bool $doDeltaCheck
     */
    public function setDoDeltaCheck(bool $doDeltaCheck): void;

    /**
     * Set data index for id column in import data
     *
     * @param mixed $idDataIndex
     */
    public function setIdDataIndex($idDataIndex): void;

    /**
     * Activate/deactivate element cleanup
     *
     * @param bool $doCleanup
     */
    public function setDoCleanup(bool $doCleanup): void;

    /**
     * Activate/deactivate archivate import file, e.g. to application logger
     *
     * @param bool $doArchiveImportFile
     */
    public function setDoArchiveImportFile(bool $doArchiveImportFile): void;

    /**
     * Set resolver
     *
     * @param Resolver $resolver
     */
    public function setResolver(Resolver $resolver): void;
}
