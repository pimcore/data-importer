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

namespace Pimcore\Bundle\DataImporterBundle\Event;

class PostPreparationEvent
{
    protected string $configName;

    protected string $executionType;

    protected bool $fileInterpreted;

    public function __construct(string $configName, string $executionType, bool $fileInterpreted)
    {
        $this->configName = $configName;
        $this->executionType = $executionType;
        $this->fileInterpreted = $fileInterpreted;
    }

    public function getConfigName(): string
    {
        return $this->configName;
    }

    public function getExecutionType(): string
    {
        return $this->executionType;
    }

    public function isFileInterpreted(): bool
    {
        return $this->fileInterpreted;
    }
}
