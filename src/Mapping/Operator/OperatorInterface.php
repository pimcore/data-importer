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

namespace Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator;

use Pimcore\Bundle\DataHubBatchImportBundle\Settings\SettingsAwareInterface;

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
     * @param $inputData
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
