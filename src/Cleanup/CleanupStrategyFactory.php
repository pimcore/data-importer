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

namespace Pimcore\Bundle\DataImporterBundle\Cleanup;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;

class CleanupStrategyFactory
{
    /**
     * @var CleanupStrategyInterface[]
     */
    protected $cleanupStrategies;

    /**
     * CleanupStrategyFactory constructor.
     *
     * @param CleanupStrategyInterface[] $cleanupStrategies
     */
    public function __construct(array $cleanupStrategies)
    {
        $this->cleanupStrategies = $cleanupStrategies;
    }

    /**
     * @param string $type
     *
     * @return mixed
     *
     * @throws InvalidConfigurationException
     */
    public function loadCleanupStrategy(string $type)
    {
        if (empty($type) || !array_key_exists($type, $this->cleanupStrategies)) {
            throw new InvalidConfigurationException('Unknown loader type `' . ($type ?? '') . '`');
        }

        return $this->cleanupStrategies[$type];
    }
}
