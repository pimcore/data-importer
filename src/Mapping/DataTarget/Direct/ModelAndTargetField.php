<?php

namespace Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\Direct;

use Pimcore\Model\ModelInterface;

readonly class ModelAndTargetField
{
    public function __construct(private ModelInterface $valueContainer, private string $targetField){

    }
    public function getModel(): ModelInterface
    {
        return $this->valueContainer;
    }

    public function getTargetField(): string
    {
        return $this->targetField;
    }
}
