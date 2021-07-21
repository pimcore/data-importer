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

namespace Pimcore\Bundle\DataImporterBundle\Event\DataObject;

use Pimcore\Model\Element\ElementInterface;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractDataObjectImportEvent extends Event
{
    /**
     * @var string
     */
    protected $configName;

    /**
     * @var array
     */
    protected $rawData;

    /**
     * @var ElementInterface
     */
    protected $dataObject;

    /**
     * AbstractDataObjectImportEvent constructor.
     *
     * @param string $configName
     * @param array $rawData
     * @param ElementInterface $dataObject
     */
    public function __construct(string $configName, array $rawData, ElementInterface $dataObject)
    {
        $this->configName = $configName;
        $this->rawData = $rawData;
        $this->dataObject = $dataObject;
    }

    /**
     * @return string
     */
    public function getConfigName(): string
    {
        return $this->configName;
    }

    /**
     * @param string $configName
     */
    public function setConfigName(string $configName): self
    {
        $this->configName = $configName;

        return $this;
    }

    /**
     * @return array
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    /**
     * @param array $rawData
     */
    public function setRawData(array $rawData): self
    {
        $this->rawData = $rawData;

        return $this;
    }

    /**
     * @return ElementInterface
     */
    public function getDataObject(): ElementInterface
    {
        return $this->dataObject;
    }

    /**
     * @param ElementInterface $dataObject
     */
    public function setDataObject(ElementInterface $dataObject): self
    {
        $this->dataObject = $dataObject;

        return $this;
    }
}
