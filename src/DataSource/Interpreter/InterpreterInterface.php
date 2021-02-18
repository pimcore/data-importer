<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Interpreter;


use Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Resolver;
use Pimcore\Bundle\DataHubBatchImportBundle\Settings\PreviewData;
use Pimcore\Bundle\DataHubBatchImportBundle\Settings\SettingsAwareInterface;

interface InterpreterInterface extends SettingsAwareInterface
{

    /**
     * @param string $path
     * @param bool $originalFilename
     * @return bool
     */
    public function fileValid(string $path, bool $originalFilename = false): bool;

    /**
     * @param string $path
     * @return bool
     */
    public function interpretFile(string $path): bool;

    /**
     * @param string $path
     * @param int $recordNumber
     * @param array $mappedColumns
     * @return PreviewData
     */
    public function previewData(string $path, int $recordNumber = 0, array $mappedColumns = []): PreviewData;

    /**
     * @param string $configName
     */
    public function setConfigName(string $configName): void;

    /**
     * @param string $executionType
     */
    public function setExecutionType(string $executionType): void;

    /**
     * @param bool $doDeltaCheck
     */
    public function setDoDeltaCheck(bool $doDeltaCheck): void;

    /**
     * @param mixed $idDataIndex
     */
    public function setIdDataIndex($idDataIndex): void;

    /**
     * @param bool $doCleanup
     */
    public function setDoCleanup(bool $doCleanup): void;

    /**
     * @param bool $doArchiveImportFile
     */
    public function setDoArchiveImportFile(bool $doArchiveImportFile): void;

    /**
     * @param Resolver $resolver
     */
    public function setResolver(Resolver $resolver): void;
}
