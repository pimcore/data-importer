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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Model\DataObject\Data\ExternalImage;

/**
 * An external image, which is a URL and nothing else. Nothing is fetched: the value keeps the
 * address the row carries, exactly as the field holds it.
 *
 * @internal
 */
final class AsExternalImage extends AbstractOperator
{
    /**
     * @param mixed $inputData
     * @param bool $dryRun
     */
    public function process($inputData, bool $dryRun = false): ?ExternalImage
    {
        if ($inputData === null || $inputData === '') {
            return null;
        }

        return new ExternalImage((string) $inputData);
    }

    /**
     * @param mixed $inputData
     *
     * @return mixed|string
     */
    public function generateResultPreview($inputData)
    {
        if ($inputData instanceof ExternalImage) {
            return 'ExternalImage: ' . $inputData->getUrl();
        }

        return $inputData;
    }

    /**
     * @param string $inputType
     * @param int|null $index
     *
     * @throws InvalidConfigurationException
     */
    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        if ($inputType !== TransformationDataTypeService::DEFAULT_TYPE) {
            throw new InvalidConfigurationException(sprintf(
                "Unsupported input type '%s' for external image operator at transformation position %s",
                $inputType,
                $index
            ));
        }

        return TransformationDataTypeService::EXTERNAL_IMAGE;
    }
}
