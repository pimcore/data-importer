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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator;

use Pimcore\Bundle\DataImporterBundle\Settings\SettingsAwareInterface;

interface OperatorInterface extends SettingsAwareInterface
{
    /**
     * Apply transformation to input data
     *
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return mixed
     */
    public function process($inputData, bool $dryRun = false);

    /**
     * Calculate resulting return type for given input type. Throw exception if input type not supported.
     *
     * @param string $inputType
     * @param int|null $index
     *
     * @return string
     */
    public function evaluateReturnType(string $inputType, int $index = null): string;

    /**
     * Generate string representation of given input
     *
     * @param mixed $inputData
     *
     * @return mixed
     */
    public function generateResultPreview($inputData);

    /**
     * Set name of current import configuration
     *
     * @param string $configName
     *
     * @return mixed
     */
    public function setConfigName(string $configName);
}
