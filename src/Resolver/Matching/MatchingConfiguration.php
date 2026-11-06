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

/**
 * How the rows of a file find their objects.
 *
 * @internal
 */
final readonly class MatchingConfiguration
{
    /**
     * @param int|string $dataSourceIndex the column holding the match value
     * @param string $targetFolder path of an existing element; only it, the folder for new objects and what lies below
     *                             them can be matched
     * @param string|null $attributeName the text field to match by, `<field>.<brick>.<attribute>` for an object brick
     *                                   attribute; null matches by the Object ID
     * @param string $language the language of a localized field
     * @param string|null $newObjectFolder path of the existing element new objects go to; null is the target folder;
     *                                     searched too, so importing a file again finds what its first run made
     */
    public function __construct(
        public string $classId,
        public int|string $dataSourceIndex,
        public string $targetFolder,
        public ?string $attributeName = null,
        public string $language = '',
        public ?string $newObjectFolder = null,
    ) {
    }
}
