<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator;


use Pimcore\Log\ApplicationLogger;

abstract class AbstractOperator implements OperatorInterface
{

    /**
     * @var string
     */
    protected $configName;

    /**
     * @var ApplicationLogger
     */
    protected $applicationLogger;

    /**
     * AbstractOperator constructor.
     * @param ApplicationLogger $applicationLogger
     */
    public function __construct(ApplicationLogger $applicationLogger)
    {
        $this->applicationLogger = $applicationLogger;
    }

    public function setConfigName(string $configName)
    {
        $this->configName = $configName;
    }



    public function generateResultPreview($inputData)
    {
        return $inputData;
    }

    public function setSettings(array $settings): void
    {
        //nothing to do
    }
}
