<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
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

    /**
     * @return mixed
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @param mixed $label
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
     * @param mixed $transformationPipeline
     */
    public function setTransformationPipeline($transformationPipeline): void
    {
        $this->transformationPipeline = $transformationPipeline;
    }

    /**
     * @return DataTargetInterface
     */
    public function getDataTarget(): DataTargetInterface
    {
        return $this->dataTarget;
    }

    /**
     * @param mixed $dataTarget
     */
    public function setDataTarget($dataTarget): void
    {
        $this->dataTarget = $dataTarget;
    }
}
