<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Interpreter;


use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataHubBatchImportBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Resolver;

class InterpreterFactory
{

    /**
     * @var InterpreterInterface[]
     */
    protected $interpreterBluePrints;

    /**
     * LoaderFactory constructor.
     * @param InterpreterInterface[] $interpreterBluePrints
     */
    public function __construct(array $interpreterBluePrints)
    {
        $this->interpreterBluePrints = $interpreterBluePrints;
    }

    public function loadInterpreter(string $configName, array $interpreterConfiguration, array $processingConfiguration, Resolver $resolver = null) {

        if(empty($interpreterConfiguration['type']) || !array_key_exists($interpreterConfiguration['type'], $this->interpreterBluePrints)) {
            throw new InvalidConfigurationException("Unknown loader type `" . ($interpreterConfiguration['type'] ?? '') . "`");
        }

        $loader = clone $this->interpreterBluePrints[$interpreterConfiguration['type']];
        $loader->setConfigName($configName);
        $loader->setExecutionType($processingConfiguration['executionType'] ?? ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL);
        $loader->setIdDataIndex($processingConfiguration['idDataIndex'] ?? null);
        $loader->setDoDeltaCheck($processingConfiguration['doDeltaCheck'] ?? false);
        $loader->setDoCleanup($processingConfiguration['cleanup']['doCleanup'] ?? false);
        $loader->setDoArchiveImportFile($processingConfiguration['doArchiveImportFile'] ?? false);

        if($resolver) {
            $loader->setResolver($resolver);
        }

        $loader->setSettings($interpreterConfiguration['settings'] ?? []);

        return $loader;

    }

}
