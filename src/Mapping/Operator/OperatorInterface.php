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
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return mixed
     */
    public function process($inputData, bool $dryRun = false);

    /**
     * @param string $inputType
     * @param int|null $index
     *
     * @return string
     */
    public function evaluateReturnType(string $inputType, int $index = null): string;

    /**
     * @param $inputData
     *
     * @return mixed
     */
    public function generateResultPreview($inputData);

    /**
     * @param string $configName
     *
     * @return mixed
     */
    public function setConfigName(string $configName);
}
