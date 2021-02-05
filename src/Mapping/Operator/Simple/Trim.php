<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator\Simple;



use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Type\TransformationDataTypeService;

class Trim extends AbstractOperator
{

    CONST MODE_BOTH = 'both';
    CONST MODE_LEFT = 'left';
    CONST MODE_RIGHT = 'right';

    /**
     * @var string
     */
    protected $mode;

    public function setSettings(array $settings): void
    {
        $this->mode = $settings['mode'] ?? self::MODE_BOTH;
    }


    public function process($inputData, bool $dryRun = false)
    {

        $returnScalar = false;
        if(!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }


        if($this->mode == self::MODE_BOTH) {
            foreach($inputData as &$data) {
                $data = trim($data);
            }
        }
        if($this->mode == self::MODE_LEFT) {
            foreach($inputData as &$data) {
                $data = ltrim($data);
            }
        }
        if($this->mode == self::MODE_RIGHT) {
            foreach($inputData as &$data) {
                $data = rtrim($data);
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
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for trim operator at transformation position %s", $inputType, $index));
        }

        return $inputType;

    }

}
