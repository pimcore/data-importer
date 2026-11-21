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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator;

use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;

/**
 * @internal
 */
abstract class AbstractOperator implements OperatorInterface
{
    /**
     * @var string
     */
    protected $configName;

    /**
     * @var ApplicationLogger
     */
    protected $applicationLogger;

    /**
     * AbstractOperator constructor.
     *
     * @param ApplicationLogger $applicationLogger
     */
    public function __construct(ApplicationLogger $applicationLogger)
    {
        $this->applicationLogger = $applicationLogger;
    }

    /**
     * @param string $configName
     *
     * @return void
     */
    public function setConfigName(string $configName)
    {
        $this->configName = $configName;
    }

    /**
     * @param mixed $inputData
     *
     * @return mixed
     */
    public function generateResultPreview($inputData)
    {
        return $inputData;
    }

    public function setSettings(array $settings): void
    {
        //nothing to do
    }
}
