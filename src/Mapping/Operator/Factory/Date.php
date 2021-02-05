<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator\Factory;



use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Type\TransformationDataTypeService;

class Date extends AbstractOperator
{

    /**
     * @var string
     */
    protected $format;

    public function setSettings(array $settings): void
    {
        $this->format = $settings['format'] ?? 'Y-m-d';
    }


    public function process($inputData, bool $dryRun = false)
    {

        $returnScalar = false;
        if(!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }

        foreach($inputData as &$data) {
            if(!empty($data)) {
                $data = \DateTime::createFromFormat($this->format, $data);
            }
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
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for date operator at transformation position %s", $inputType, $index));
        }

        return TransformationDataTypeService::DATE;

    }

    public function generateResultPreview($inputData)
    {
        if($inputData instanceof \DateTime) {
            return $inputData->format('c');
        }

        return $inputData;
    }

}
