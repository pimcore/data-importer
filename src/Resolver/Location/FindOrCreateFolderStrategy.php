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

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Location;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Tool\DataObjectLoader;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Service;
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
final class FindOrCreateFolderStrategy implements LocationStrategyInterface
{
    private mixed $dataSourceIndex;

    private string $findStrategy;

    private string $fallbackPath;

    private mixed $attributeDataObjectClassId;

    private string $attributeName;

    private string $attributeLanguage;

    public function __construct(private readonly DataObjectLoader $dataObjectLoader)
    {
    }

    public function setSettings(array $settings): void
    {
        if ($settings['dataSourceIndex'] !== 0 && $settings['dataSourceIndex'] !== '0' && empty($settings['dataSourceIndex'])) {
            throw new InvalidConfigurationException('Empty data source index.');
        }

        $this->dataSourceIndex = $settings['dataSourceIndex'];

        $this->fallbackPath = $settings['fallbackPath'] ?? null;
    }

    public function updateParent(ElementInterface $element, array $inputData): ElementInterface
    {
        $newParent = null;
        $identifier = $inputData[$this->dataSourceIndex] ?? null;

        if ($identifier) {
            $newParent = $this->dataObjectLoader->loadByPath($identifier);

            if (!($newParent instanceof DataObject)) {
                $newParent = Service::createFolderByPath($identifier);
            }
        }

        if (!($newParent instanceof DataObject) && $this->fallbackPath) {
            $newParent = DataObject::getByPath($this->fallbackPath);
        }

        if (!($newParent)) {
            $newParent = DataObject::getById(1);
        }

        return $element->setParent($newParent);
    }

    private function loadById()
    {
    }
}
