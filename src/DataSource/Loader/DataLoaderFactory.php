<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Loader;


use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;

class DataLoaderFactory
{

    /**
     * @var DataLoaderInterface[]
     */
    protected $dataLoaderBluePrints;

    /**
     * DataLoaderFactory constructor.
     * @param DataLoaderInterface[] $dataLoaderBluePrints
     */
    public function __construct(array $dataLoaderBluePrints)
    {
        $this->dataLoaderBluePrints = $dataLoaderBluePrints;
    }

    public function loadDataLoader(array $configuration) {

        if(empty($configuration['type']) || !array_key_exists($configuration['type'], $this->dataLoaderBluePrints)) {
            throw new InvalidConfigurationException("Unknown loader type `" . ($configuration['type'] ?? '') . "`");
        }

        $dataLoader = clone $this->dataLoaderBluePrints[$configuration['type']];
        $dataLoader->setSettings($configuration['settings'] ?? []);

        return $dataLoader;

    }

}
