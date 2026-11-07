<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService as Types;

/**
 * Stands in for a load operator, which needs the database: loads by ID as the real one does.
 */
final class AdHocStandInLoader extends AbstractOperator
{
    /**
     * @param \Closure(string): mixed $load
     */
    public function __construct(
        private readonly string $type,
        private readonly string $arrayType,
        private readonly \Closure $load
    ) {
        parent::__construct((new \ReflectionClass(ApplicationLogger::class))->newInstanceWithoutConstructor());
    }

    public function process($inputData, bool $dryRun = false)
    {
        if (is_array($inputData)) {
            return array_values(array_filter(array_map($this->load, $inputData)));
        }

        return ($this->load)((string) $inputData);
    }

    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        return match ($inputType) {
            Types::DEFAULT_TYPE => $this->type,
            Types::DEFAULT_ARRAY => $this->arrayType,
            default => throw new InvalidConfigurationException('Unsupported input type'),
        };
    }
}
