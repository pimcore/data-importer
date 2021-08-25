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

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Load;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\Element\ElementInterface;

class AttributeStrategy extends AbstractLoad
{
    /**
     * @var string
     */
    protected $attributeName;

    /**
     * @var string
     */
    protected $attributeLanguage;

    /**
     * @var bool
     */
    protected $includeUnpublished;

    /**
     * @param array $settings
     *
     * @throws InvalidConfigurationException
     */
    public function setSettings(array $settings): void
    {
        parent::setSettings($settings);

        if (empty($settings['attributeName'])) {
            throw new InvalidConfigurationException('Empty attribute name.');
        }

        $this->attributeName = $settings['attributeName'];
        $this->attributeLanguage = $settings['attributeLanguage'] ?? null;
        $this->includeUnpublished = $settings['includeUnpublished'] ?? false;
    }

    /**
     * @param $identifier
     *
     * @return ElementInterface|null
     *
     * @throws InvalidConfigurationException
     */
    public function loadElementByIdentifier($identifier): ?ElementInterface
    {
        $className = $this->getClassName();
        $getter = 'getBy' . $this->attributeName;

        if ($this->includeUnpublished) {
            $className::setHideUnpublished(false);
        }

        $element = null;
        if ($this->attributeLanguage) {
            $element = $className::$getter($identifier, $this->attributeLanguage, 1);
        } else {
            $element = $className::$getter($identifier, 1);
        }

        if ($element instanceof ElementInterface) {
            return $element;
        }

        return null;
    }

    /**
     * @return array
     */
    public function loadFullIdentifierList(): array
    {
        $tableName = 'object_' . $this->dataObjectClassId;
        if ($this->attributeLanguage) {
            $tableName = 'object_localized_' . $this->dataObjectClassId . '_' . $this->attributeLanguage;
        }

        $sql = sprintf('SELECT `%s` FROM %s', $this->attributeName, $tableName);

        return $this->db->fetchCol($sql);
    }
}
