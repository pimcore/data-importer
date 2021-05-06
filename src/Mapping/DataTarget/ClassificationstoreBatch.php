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
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Model\Element\ElementInterface;

class ClassificationstoreBatch implements DataTargetInterface
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
        $getter = 'get' . ucfirst($this->fieldName);
        $classificationStore = $element->$getter();

        if ($classificationStore instanceof \Pimcore\Model\DataObject\Classificationstore) {
            if (!is_array($data)) {
                throw new InvalidInputException('Input data not an array');
            }

            $data = array_filter($data);
            if (!empty($data)) {
                foreach ($data as $key => $value) {
                    $keyParts = explode('-', $key);
                    if (count($keyParts) !== 2) {
                        throw new InvalidInputException('Key not format <GROUP_ID>-<KEY_ID>: ' . $key);
                    }

                    $classificationStore->setLocalizedKeyValue($keyParts[0], $keyParts[1], $value, $this->language);
                    $classificationStore->setActiveGroups($classificationStore->getActiveGroups() + [$keyParts[0] => true]);
                }
            }
        } else {
            throw new InvalidConfigurationException('Field ' . $this->fieldName . ' is not a classification store.');
        }
    }
}
