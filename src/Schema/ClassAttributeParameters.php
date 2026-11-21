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

namespace Pimcore\Bundle\DataImporterBundle\Schema;

/**
 * @internal
 */
final readonly class ClassAttributeParameters
{
    public function __construct(
        private ?string $classId = null,
        private bool $loadAdvancedRelations = false,
        private bool $systemRead = false,
        private bool $systemWrite = false,
        private ?string $transformationResultType = null,
    ) {
    }

    public function getClassId(): ?string
    {
        return $this->classId;
    }

    public function getLoadAdvancedRelations(): bool
    {
        return $this->loadAdvancedRelations;
    }

    public function getSystemRead(): bool
    {
        return $this->systemRead;
    }

    public function getSystemWrite(): bool
    {
        return $this->systemWrite;
    }

    public function getTransformationResultType(): ?string
    {
        return $this->transformationResultType;
    }
}
