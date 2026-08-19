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
 * The outcome of validating one configuration.
 */
final readonly class ValidationResult
{
    /**
     * @param ValidationError[] $errors
     * @param ValidationError[] $warnings
     */
    public function __construct(
        private bool $valid,
        private array $errors = [],
        private array $warnings = [],
    ) {
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * @return ValidationError[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return ValidationError[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }

    /**
     * Get all errors as an array of strings
     */
    public function getErrorMessages(): array
    {
        return array_map(function (ValidationError $error) {
            return $error->getPath() . ': ' . $error->getMessage();
        }, $this->errors);
    }

    /**
     * Get all warnings as an array of strings
     */
    public function getWarningMessages(): array
    {
        return array_map(function (ValidationError $warning) {
            return $warning->getPath() . ': ' . $warning->getMessage();
        }, $this->warnings);
    }

    /**
     * Convert result to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => array_map(fn ($e) => $e->toArray(), $this->errors),
            'warnings' => array_map(fn ($w) => $w->toArray(), $this->warnings),
        ];
    }
}
