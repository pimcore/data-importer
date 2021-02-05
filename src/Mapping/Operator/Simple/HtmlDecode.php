<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator\Simple;



use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Type\TransformationDataTypeService;

class HtmlDecode extends AbstractOperator
{

    public function process($inputData, bool $dryRun = false)
    {
        $returnScalar = false;
        if(!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }

        foreach($inputData as &$data) {
            $data = html_entity_decode($data);
        }

        if($returnScalar) {
            return reset($inputData);
        } else {
            return $inputData;
        }
    }

    /**
     * @param string $inputType
     * @param int|null $index
     * @return string
     * @throws InvalidConfigurationException
     */
    public function evaluateReturnType(string $inputType, int $index = null): string {

        if(!in_array($inputType, [TransformationDataTypeService::DEFAULT_TYPE, TransformationDataTypeService::DEFAULT_ARRAY])) {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for html decode operator at transformation position %s", $inputType, $index));
        }

        return $inputType;
    }

}
