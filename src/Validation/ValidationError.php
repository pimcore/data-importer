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
 * One validation finding: where it is, and what is wrong there.
 */
final readonly class ValidationError
{
    public function __construct(
        private string $path,
        private string $message,
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array{path: string, message: string}
     */
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
