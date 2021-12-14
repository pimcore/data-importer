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
     * @return CleanupStrategyInterface
     *
     * @throws InvalidConfigurationException
     */
    public function loadCleanupStrategy(string $type)
    {
        if (empty($type) || !array_key_exists($type, $this->cleanupStrategies)) {
            throw new InvalidConfigurationException('Unknown loader type `' . $type . '`');
        }

        return $this->cleanupStrategies[$type];
    }
}
