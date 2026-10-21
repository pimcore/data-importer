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

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Load;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
final class AttributeStrategy extends AbstractLoad
{
    private string $attributeName;

    private string $attributeLanguage;

    private bool $includeUnpublished;

    private string $path = '';

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
        $this->attributeLanguage = $settings['language'] ?? '';
        $this->includeUnpublished = $settings['includeUnpublished'] ?? false;
        $this->path = $this->dataObjectLoader->normalizePath((string) ($settings['path'] ?? ''));
    }

    /**
     * @param string $identifier
     *
     * @return ElementInterface|null
     *
     * @throws InvalidConfigurationException
     */
    public function loadElementByIdentifier($identifier): ?ElementInterface
    {
        return $this->dataObjectLoader->loadByAttribute($this->getClassName(),
            $this->attributeName,
            $identifier,
            $this->attributeLanguage,
            $this->includeUnpublished,
            1,
            '=',
            $this->path);
    }

    public function loadFullIdentifierList(): array
    {
        $tableName = 'object_' . $this->dataObjectClassId;
        if ($this->attributeLanguage) {
            $tableName = 'object_localized_' . $this->dataObjectClassId . '_' . $this->attributeLanguage;
        }

        $sql = sprintf('SELECT `%s` FROM %s', $this->attributeName, $tableName);

        if ($this->path !== '') {
            // restrict to the configured tree path, so objects outside of it are never offered for cleanup
            $sql .= ' WHERE ' . $this->dataObjectLoader->getPathLikeCondition($this->path);
        }

        return $this->db->fetchFirstColumn($sql);
    }
}
