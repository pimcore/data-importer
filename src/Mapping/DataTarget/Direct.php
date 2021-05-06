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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\Element\ElementInterface;

class Direct implements DataTargetInterface
{
    /**
     * @var string
     */
    protected $fieldName;

    /**
     * @var string
     */
    protected $language;

    public function setSettings(array $settings): void
    {
        if (empty($settings['fieldName'])) {
            throw new InvalidConfigurationException('Empty field name.');
        }

        $this->fieldName = $settings['fieldName'];
        $this->language = $settings['language'] ?? null;
    }

    public function assignData(ElementInterface $element, $data)
    {
        $setterParts = explode('.', $this->fieldName);

        if (count($setterParts) === 1) {
            //direct class attribute
            $setter = 'set' . ucfirst($this->fieldName);
            $element->$setter($data, $this->language);
        } elseif (count($setterParts) === 3) {
            //brick attribute

            $brickContainerGetter = 'get' . ucfirst($setterParts[0]);
            $brickContainer = $element->$brickContainerGetter();

            $brickGetter = 'get' . ucfirst($setterParts[1]);
            $brick = $brickContainer->$brickGetter();

            if (empty($brick)) {
                $brickClassName = '\\Pimcore\\Model\\DataObject\\Objectbrick\\Data\\' . ucfirst($setterParts[1]);
                $brick = new $brickClassName($element);
                $brickSetter = 'set' . ucfirst($setterParts[1]);
                $brickContainer->$brickSetter($brick);
            }

            $setter = 'set' . ucfirst($setterParts[2]);
            $brick->$setter($data, $this->language);
        } else {
            throw new InvalidConfigurationException('Invalid number of setter parts for ' . $this->fieldName);
        }
    }
}
