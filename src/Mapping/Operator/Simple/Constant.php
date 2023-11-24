<?php

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple;

use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;

class Constant extends AbstractOperator
{

    /**
     * @var string
     */
    protected string $constant;

    public function setSettings(array $settings): void
    {
        $this->constant = $settings['constant'] ?? '';
    }

    public function process($inputData, bool $dryRun = false)
    {
        return $this->constant;
    }

    public function evaluateReturnType(string $inputType, int $index = null): string
    {
        return $inputType;
    }
}
