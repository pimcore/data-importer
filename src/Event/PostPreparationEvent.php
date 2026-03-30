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

final readonly class PostPreparationEvent
{
    public function __construct(
        private string $configName,
        private string $executionType,
        private bool $fileInterpreted,
    ) {
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
