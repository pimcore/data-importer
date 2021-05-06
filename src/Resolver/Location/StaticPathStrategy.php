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

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Location;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
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
        if (empty($settings['path'])) {
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
