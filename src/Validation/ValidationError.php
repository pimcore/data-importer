<?php

declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Validation;

/**
 * Represents a validation error or warning
 */
class ValidationError
{
    protected string $path;

    protected string $message;

    public function __construct(string $path, string $message)
    {
        $this->path = $path;
        $this->message = $message;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'message' => $this->message,
        ];
    }

    public function __toString(): string
    {
        return $this->path . ': ' . $this->message;
    }
}
