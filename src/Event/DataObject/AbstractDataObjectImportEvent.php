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

namespace Pimcore\Bundle\DataImporterBundle\Event\DataObject;

use Pimcore\Model\Element\ElementInterface;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractDataObjectImportEvent extends Event
{
    protected string $configName;

    protected array $rawData;

    protected ElementInterface $dataObject;

    public function __construct(string $configName, array $rawData, ElementInterface $dataObject)
    {
        $this->configName = $configName;
        $this->rawData = $rawData;
        $this->dataObject = $dataObject;
    }

    public function getConfigName(): string
    {
        return $this->configName;
    }

    public function setConfigName(string $configName): self
    {
        $this->configName = $configName;

        return $this;
    }

    public function getRawData(): array
    {
        return $this->rawData;
    }

    public function setRawData(array $rawData): self
    {
        $this->rawData = $rawData;

        return $this;
    }

    public function getDataObject(): ElementInterface
    {
        return $this->dataObject;
    }

    public function setDataObject(ElementInterface $dataObject): self
    {
        $this->dataObject = $dataObject;

        return $this;
    }
}
