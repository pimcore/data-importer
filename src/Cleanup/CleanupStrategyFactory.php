<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Cleanup;


use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;

class CleanupStrategyFactory
{

    /**
     * @var CleanupStrategyInterface[]
     */
    protected $cleanupStrategies;

    /**
     * CleanupStrategyFactory constructor.
     * @param CleanupStrategyInterface[] $cleanupStrategies
     */
    public function __construct(array $cleanupStrategies)
    {
        $this->cleanupStrategies = $cleanupStrategies;
    }


    /**
     * @param string $type
     * @return mixed
     * @throws InvalidConfigurationException
     */
    public function loadCleanupStrategy(string $type) {
        if(empty($type) || !array_key_exists($type, $this->cleanupStrategies)) {
            throw new InvalidConfigurationException("Unknown loader type `" . ($type ?? '') . "`");
        }

        return $this->cleanupStrategies[$type];
    }

}
