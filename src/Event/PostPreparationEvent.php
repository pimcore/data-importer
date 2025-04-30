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
