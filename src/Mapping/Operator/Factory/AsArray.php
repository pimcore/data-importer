<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator\Factory;



use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Type\TransformationDataTypeService;

class AsArray extends AbstractOperator
{

    public function process($inputData, bool $dryRun = false)
    {
        if(!is_array($inputData)) {
            $inputData = [$inputData];
        }

        return $inputData;
    }

    /**
     * @param string $inputType
     * @param int|null $index
     * @return string
     */
    public function evaluateReturnType(string $inputType, int $index = null): string {
        return TransformationDataTypeService::DEFAULT_ARRAY;
    }

}
