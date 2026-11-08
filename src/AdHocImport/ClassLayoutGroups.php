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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport;

use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\DataObject\ClassDefinition\Layout;

/**
 * Groups a class's fields as Studio's grid configuration does: by the first titled layout element above them, or by
 * the class name; an object brick's fields below the brick field and the brick.
 *
 * @internal
 */
final class ClassLayoutGroups
{
    /** @var array<string, string> */
    private array $groupOfField = [];

    private function __construct(private readonly string $className)
    {
    }

    public static function of(ClassDefinition $class): self
    {
        $groups = new self((string) $class->getName());
        $layout = $class->getLayoutDefinitions();
        if ($layout instanceof Layout) {
            $groups->collect($layout->getChildren(), null);
        }

        return $groups;
    }

    /**
     * The group path of a field key as the mapping names it: `name`, or `bricksField.BrickType.attribute`.
     *
     * @return list<string>
     */
    public function groupOf(string $key): array
    {
        $parts = explode('.', $key);
        $group = $this->groupOfField[$parts[0]] ?? $this->className;
        if (count($parts) === 3) {
            return [$group, $parts[0], $parts[1]];
        }

        return [$group];
    }

    /**
     * @param array<mixed> $children
     */
    private function collect(array $children, ?string $group): void
    {
        foreach ($children as $child) {
            if ($child instanceof Layout) {
                // only the first titled level groups, as in the grid
                $title = (string) $child->getTitle();
                $this->collect($child->getChildren(), $group ?? ($title !== '' ? $title : null));

                continue;
            }

            if ($child instanceof Localizedfields) {
                $this->collect($child->getChildren(), $group);

                continue;
            }

            if ($child instanceof Data) {
                $this->groupOfField[(string) $child->getName()] = $group ?? $this->className;
            }
        }
    }
}
