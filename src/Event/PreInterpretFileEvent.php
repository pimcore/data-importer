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

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before an interpreter starts reading the source file, both for real imports
 * and for the Studio preview. Listeners may replace the file path, e.g. to normalize the
 * file (transcode, strip a report preamble, rewrite delimiters) without replacing the
 * interpreter. Stateful PreQueueRowEvent listeners can also use it as a per-run reset signal.
 */
final class PreInterpretFileEvent extends Event
{
    public function __construct(
        private readonly string $configName,
        private readonly string $executionType,
        private string $path,
        private readonly bool $preview = false,
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

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    /**
     * True when the file is being read for the Studio preview instead of an actual import.
     */
    public function isPreview(): bool
    {
        return $this->preview;
    }
}
