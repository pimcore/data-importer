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

use Pimcore\Log\ApplicationLogger;

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

    public function setConfigName(string $configName)
    {
        $this->configName = $configName;
    }

    public function generateResultPreview($inputData)
    {
        return $inputData;
    }

    public function setSettings(array $settings): void
    {
        //nothing to do
    }
}
