<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Matching;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Resolver\ResolverFactory;
use Pimcore\Bundle\DataImporterBundle\Tool\DataObjectLoader;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\DataObject\ClassDefinition\Data\Objectbricks;
use Pimcore\Model\DataObject\ClassDefinition\Data\QueryResourcePersistenceAwareInterface;
use Pimcore\Model\DataObject\Objectbrick;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Tool;

/**
 * @internal
 */
final class RowMatcherFactory
{
    private const LOCALIZED_FIELDS = 'localizedfields';

    public function __construct(
        private readonly ResolverFactory $resolverFactory,
        private readonly DataObjectLoader $dataObjectLoader,
    ) {
    }

    /**
     * @throws InvalidConfigurationException if the class, a folder or the field to match by cannot be used
     */
    public function create(MatchingConfiguration $configuration): RowMatcher
    {
        $class = ClassDefinition::getById($configuration->classId);
        if ($class === null) {
            throw new InvalidConfigurationException("Class `{$configuration->classId}` not found.");
        }

        $this->assertLanguage($configuration->language);
        if ($configuration->attributeName !== null) {
            $this->assertMatchableField($class, $configuration->attributeName, $configuration->language);
        }

        $targetFolder = $this->loadFolder($configuration->targetFolder);

        return new RowMatcher(
            // the matcher picks the object and places a new one, so the resolver loads by id and moves nothing
            $this->resolverFactory->loadResolver([
                'elementType' => 'dataObject',
                'dataObjectClassId' => $configuration->classId,
                'loadingStrategy' => ['type' => 'id', 'settings' => ['dataSourceIndex' => RowMatcher::OBJECT_ID_INDEX]],
                'createLocationStrategy' => ['type' => 'noChange'],
                'locationUpdateStrategy' => ['type' => 'noChange'],
                'publishingStrategy' => ['type' => 'noChangeUnpublishNew'],
            ]),
            $this->dataObjectLoader,
            $configuration,
            '\\Pimcore\\Model\\DataObject\\' . ucfirst($class->getName()),
            $this->dataObjectLoader->normalizePath($targetFolder->getRealFullPath()),
            $configuration->newObjectFolder === null ? $targetFolder : $this->loadFolder($configuration->newObjectFolder)
        );
    }

    private function loadFolder(string $path): ElementInterface
    {
        // never created here: the strategies that would create a missing one write while a file is only checked
        $folder = DataObject::getByPath($path);
        if ($folder === null) {
            throw new InvalidConfigurationException("Folder `{$path}` not found.");
        }

        return $folder;
    }

    private function assertLanguage(string $language): void
    {
        if ($language !== '' && !Tool::isValidLanguage($language)) {
            throw new InvalidConfigurationException("Invalid language `{$language}`.");
        }
    }

    private function assertMatchableField(ClassDefinition $class, string $attributeName, string $language): void
    {
        $definition = $class->getFieldDefinition($attributeName);

        if ($definition === null) {
            $localizedFields = $class->getFieldDefinition(self::LOCALIZED_FIELDS);
            $definition = $localizedFields instanceof Localizedfields
                ? $localizedFields->getFieldDefinition($attributeName)
                : null;

            if ($definition !== null && $language === '') {
                throw new InvalidConfigurationException("Localized field `{$attributeName}` needs a language.");
            }
        }

        $definition ??= $this->brickFieldDefinition($class, $attributeName);

        // the lookup compares one column: no relations, and no value that spreads over several columns
        if (
            !$definition instanceof QueryResourcePersistenceAwareInterface
            || !is_string($definition->getQueryColumnType())
        ) {
            throw new InvalidConfigurationException("Objects cannot be matched by field `{$attributeName}`.");
        }
    }

    private function brickFieldDefinition(ClassDefinition $class, string $attributeName): ?ClassDefinition\Data
    {
        $parts = explode('.', $attributeName);
        if (count($parts) !== 3) {
            return null;
        }

        [$container, $brick, $field] = $parts;
        $containerDefinition = $class->getFieldDefinition($container);
        if (
            !$containerDefinition instanceof Objectbricks
            || !in_array($brick, $containerDefinition->getAllowedTypes(), true)
        ) {
            return null;
        }

        return Objectbrick\Definition::getByKey($brick)?->getFieldDefinition($field);
    }
}
