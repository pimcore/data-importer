<?php

namespace Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Location;

use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\DataObject\Service;
use Pimcore\Model\Element\ElementInterface;

class StaticPathStrategy implements LocationStrategyInterface
{

    /**
     * @var string
     */
    protected $path;

    public function setSettings(array $settings): void
    {
        if(empty($settings['path'])) {
            throw new InvalidConfigurationException('Empty path.');
        }

        $this->path = $settings['path'];
    }

    public function updateParent(ElementInterface $element, array $inputData): ElementInterface
    {
        $element->setParent(Service::createFolderByPath($this->path));
        return $element;
    }

}
