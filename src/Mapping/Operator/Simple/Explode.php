<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator\Simple;



use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Type\TransformationDataTypeService;

class Explode extends AbstractOperator
{

    /**
     * @var string
     */
    protected $delimiter;

    public function setSettings(array $settings): void
    {
        $this->delimiter = $settings['delimiter'] ?? ' ';
    }


    public function process($inputData, bool $dryRun = false)
    {
        if(!empty($this->delimiter)) {
            return explode($this->delimiter, $inputData);
        } else {
            return [$inputData];
        }
    }

    /**
     * @param string $inputType
     * @param int|null $index
     * @return string
     * @throws InvalidConfigurationException
     */
    public function evaluateReturnType(string $inputType, int $index = null): string {

        if(!$inputType === TransformationDataTypeService::DEFAULT_TYPE) {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for explode operator at transformation position %s", $inputType, $index));
        }

        return TransformationDataTypeService::DEFAULT_ARRAY;

    }

}
