<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Cleanup;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;

/**
 * @internal
 */
final class CleanupStrategyFactory
{
    /**
     * CleanupStrategyFactory constructor.
     *
     * @param CleanupStrategyInterface[] $cleanupStrategies
     */
    public function __construct(
        private readonly array $cleanupStrategies,
    ) {
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
