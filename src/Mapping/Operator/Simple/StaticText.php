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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;

class StaticText extends AbstractOperator
{
    const MODE_APPEND = 'append';

    const MODE_PREPEND = 'prepend';

    /**
     * @var string
     */
    protected $mode;

    /**
     * @var string
     */
    protected $text;

    /**
     * @var bool
     */
    protected $alwaysAdd;

    public function setSettings(array $settings): void
    {
        $this->mode = $settings['mode'] ?? self::MODE_APPEND;
        $this->text = $settings['text'] ?? '';
        $this->alwaysAdd = $settings['alwaysAdd'] ?? false;
    }

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return array|false|mixed|null
     *
     * @throws InvalidConfigurationException
     */
    public function process($inputData, bool $dryRun = false)
    {
        $returnScalar = false;
        if (!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }

        if ($this->text !== '') {
            foreach ($inputData as &$data) {
                if (!empty($data) || $this->alwaysAdd) {
                    switch ($this->mode) {
                        case self::MODE_APPEND:
                            $data = $data . $this->text;

                            break;

                        case self::MODE_PREPEND:
                            $data = $this->text . $data;

                            break;

                        default:
                            throw new InvalidConfigurationException(sprintf('Invalid mode: %s', $this->mode));
                    }
                }
            }
        }

        if ($returnScalar) {
            if (!empty($inputData)) {
                return reset($inputData);
            }

            return null;
        } else {
            return $inputData;
        }
    }

    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        if (!in_array($inputType, [TransformationDataTypeService::DEFAULT_TYPE, TransformationDataTypeService::DEFAULT_ARRAY])) {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for static t ext operator at transformation position %s", $inputType, $index));
        }

        return $inputType;
    }
}
