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

namespace Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter;

use Pimcore\Bundle\DataImporterBundle\Preview\Model\PreviewData;
use Pimcore\Bundle\DataImporterBundle\Resolver\Resolver;
use Pimcore\Bundle\DataImporterBundle\Settings\SettingsAwareInterface;

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
