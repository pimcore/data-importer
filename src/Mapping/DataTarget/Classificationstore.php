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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
final class Classificationstore implements DataTargetInterface, ConfigNameAwareInterface
{
    use ClassificationstoreKeyTrait;
    use WriteIfSourceIsEmptyTrait;

    /**
     * Without the opt-in marker this target writes every value, empty ones included.
     */
    private const WRITES_EMPTY_VALUES_WITHOUT_MARKER = true;

    private string $fieldName;

    private ?string $language;

    private ClassificationstoreKeyReference $keyReference;

    public function setSettings(array $settings): void
    {
        if (empty($settings['fieldName'])) {
            throw new InvalidConfigurationException('Empty field name.');
        }

        $keyReference = ClassificationstoreKeyReference::parse((string) ($settings['keyId'] ?? ''));
        if (!$keyReference instanceof ClassificationstoreKeyReference
            || (!$keyReference->isIdentifier && ($keyReference->group === '0' || $keyReference->key === '0'))) {
            throw new InvalidConfigurationException('Empty or invalid keyId.');
        }

        $this->fieldName = $settings['fieldName'];
        $this->keyReference = $keyReference;
        $this->language = $settings['language'] ?? null;
        $this->setWriteIfSourceIsEmptySettings($settings, self::WRITES_EMPTY_VALUES_WITHOUT_MARKER);
    }

    /**
     * @param ElementInterface $element
     * @param mixed $data
     *
     * @return void
     *
     * @throws InvalidConfigurationException
     */
    public function assignData(ElementInterface $element, $data)
    {
        $getter = 'get' . ucfirst($this->fieldName);
        $classificationStore = $element->$getter();

        if ($classificationStore instanceof \Pimcore\Model\DataObject\Classificationstore) {
            if (!$this->writeIfSourceIsEmpty && $this->isEmptySourceValue($data)) {
                return;
            }

            $relation = $this->resolveRelation($this->keyReference, $element, $this->fieldName);
            if ($relation instanceof KeyGroupRelation) {
                $groupId = $relation->getGroupId();
                $keyId = $relation->getKeyId();
            } elseif ($this->keyReference->isIdentifier) {
                throw new InvalidConfigurationException(
                    'Classification store key ' . $this->keyReference->token . ' not found.'
                );
            } else {
                $groupId = (int) $this->keyReference->group;
                $keyId = (int) $this->keyReference->key;
            }

            $this->writeKeyValue(
                $classificationStore,
                $this->keyReference->token,
                $groupId,
                $keyId,
                $data,
                $this->language,
                $this->keyReference->variant,
                $this->resolveDeclaration($relation)
            );
            $classificationStore->setActiveGroups($classificationStore->getActiveGroups() + [$groupId => true]);
        } else {
            throw new InvalidConfigurationException('Field ' . $this->fieldName . ' is not a classification store.');
        }
    }
}
