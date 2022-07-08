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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Localization\LocaleServiceInterface;
use Pimcore\Log\ApplicationLogger;

class AsCountries extends AbstractOperator
{
    public function __construct(ApplicationLogger $applicationLogger, private LocaleServiceInterface $localeService)
    {
        parent::__construct($applicationLogger);
    }

    public function process(mixed $inputData, bool $dryRun = false): mixed
    {
        $countries = $this->localeService->getDisplayRegions();

        foreach ($inputData as &$input) {
            foreach ($countries as $countryCode => $country) {
                if (ltrim(rtrim($input)) == $country) {
                    $input = $countryCode;
                    break;
                }
            }
        }

        return $inputData;
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
        if ($inputType != TransformationDataTypeService::DEFAULT_ARRAY) {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for as countries operator at transformation position %s", $inputType, $index));
        }
        return TransformationDataTypeService::COUNTRY_ARRAY;
    }
}
