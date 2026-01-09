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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Pimcore\Localization\LocaleServiceInterface;
use Pimcore\Log\ApplicationLogger;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class AsCountries extends AbstractOperator implements SchemaAwareInterface
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
    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        if ($inputType != TransformationDataTypeService::DEFAULT_ARRAY) {
            throw new InvalidConfigurationException(sprintf(
                "Unsupported input type '%s' for as countries operator at transformation position %s",
                $inputType,
                $index
            ));
        }

        return TransformationDataTypeService::COUNTRY_ARRAY;
    }

    public function getSchemaDescription(): string
    {
        return 'Converts country names to country codes. '
            . 'Takes an array of country display names and converts them to their corresponding ISO country codes.';
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        // No configuration options - return null for better performance
        return null;
    }
}
