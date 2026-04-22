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

namespace Pimcore\Bundle\DataImporterBundle\Mapping;

use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\DataTargetInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\OperatorInterface;

class MappingConfiguration
{
    /**
     * @var string
     */
    protected $label;

    /**
     * @var mixed
     */
    protected $dataSourceIndex;

    /**
     * @var array
     */
    protected $transformationPipeline;

    /**
     * @var DataTargetInterface
     */
    protected $dataTarget;

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @param string $label
     */
    public function setLabel($label): void
    {
        $this->label = $label;
    }

    /**
     * @return mixed
     */
    public function getDataSourceIndex()
    {
        return $this->dataSourceIndex;
    }

    /**
     * @param mixed $dataSourceIndex
     */
    public function setDataSourceIndex($dataSourceIndex): void
    {
        $this->dataSourceIndex = $dataSourceIndex;
    }

    /**
     * @return OperatorInterface[]
     */
    public function getTransformationPipeline(): array
    {
        return $this->transformationPipeline;
    }

    /**
     * @param OperatorInterface[] $transformationPipeline
     */
    public function setTransformationPipeline($transformationPipeline): void
    {
        $this->transformationPipeline = $transformationPipeline;
    }

    public function getDataTarget(): DataTargetInterface
    {
        return $this->dataTarget;
    }

    /**
     * @param DataTargetInterface $dataTarget
     */
    public function setDataTarget($dataTarget): void
    {
        $this->dataTarget = $dataTarget;
    }
}
