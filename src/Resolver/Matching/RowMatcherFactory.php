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
        $newObjectFolder = $configuration->newObjectFolder === null
            ? $targetFolder
            : $this->loadFolder($configuration->newObjectFolder);

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
            $this->searchPaths($targetFolder, $newObjectFolder),
            $newObjectFolder
        );
    }

    /**
     * @return list<string>
     */
    private function searchPaths(ElementInterface $targetFolder, ElementInterface $newObjectFolder): array
    {
        $targetPath = $this->dataObjectLoader->normalizePath($targetFolder->getRealFullPath());
        $newObjectPath = $this->dataObjectLoader->normalizePath($newObjectFolder->getRealFullPath());

        return str_starts_with($newObjectPath, $targetPath) ? [$targetPath] : [$targetPath, $newObjectPath];
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
        // finds a localized field as well
        $definition = $class->getFieldDefinition($attributeName) ?? $this->brickFieldDefinition($class, $attributeName);

        $localizedFields = $class->getFieldDefinition(self::LOCALIZED_FIELDS);
        if (
            $language === ''
            && $localizedFields instanceof Localizedfields
            && $localizedFields->getFieldDefinition($attributeName) !== null
        ) {
            throw new InvalidConfigurationException("Localized field `{$attributeName}` needs a language.");
        }

        // the lookup compares one column as text: no relations, no value that spreads over several columns, and no
        // number or date column, where the database would read `12abc` as 12 and change the wrong object
        if (
            !$definition instanceof QueryResourcePersistenceAwareInterface
            || !is_string($definition->getQueryColumnType())
            || preg_match('/char|text/i', $definition->getQueryColumnType()) !== 1
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
