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
final readonly class ClassificationStoreKeyParameters
{
    public function __construct(
        private ?string $classId = null,
        private ?string $fieldName = null,
        private ?string $transformationResultType = null,
        private ?string $sort = null,
        private int $start = 0,
        private int $limit = 15,
        private ?string $searchfilter = null,
        private ?string $filter = null,
    ) {
    }

    public function getClassId(): ?string
    {
        return $this->classId;
    }

    public function getFieldName(): ?string
    {
        return $this->fieldName;
    }

    public function getTransformationResultType(): ?string
    {
        return $this->transformationResultType;
    }

    public function getSort(): ?string
    {
        return $this->sort;
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getSearchfilter(): ?string
    {
        return $this->searchfilter;
    }

    public function getFilter(): ?string
    {
        return $this->filter;
    }
}
