<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;

class Trim extends AbstractOperator
{
    const MODE_BOTH = 'both';
    const MODE_LEFT = 'left';
    const MODE_RIGHT = 'right';

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
        if (!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }

        if ($this->mode == self::MODE_BOTH) {
            foreach ($inputData as &$data) {
                $data = trim($data);
            }
        }
        if ($this->mode == self::MODE_LEFT) {
            foreach ($inputData as &$data) {
                $data = ltrim($data);
            }
        }
        if ($this->mode == self::MODE_RIGHT) {
            foreach ($inputData as &$data) {
                $data = rtrim($data);
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

    /**
     * @param string $inputType
     * @param int|null $index
     *
     * @return string
     *
     * @throws InvalidConfigurationException
     */
    public function evaluateReturnType(string $inputType, int $index = null): string
    {
        if (!in_array($inputType, [TransformationDataTypeService::DEFAULT_TYPE, TransformationDataTypeService::DEFAULT_ARRAY])) {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for trim operator at transformation position %s", $inputType, $index));
        }

        return $inputType;
    }
}
