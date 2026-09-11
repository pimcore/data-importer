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

namespace Pimcore\Bundle\DataImporterBundle\ChangeControl;

use function array_filter;
use const ARRAY_FILTER_USE_KEY;
use function array_is_list;
use function array_key_exists;
use function in_array;
use function is_array;
use Pimcore\Bundle\ChangeControlBundle\Hydrator\DetailHydratorInterface;
use Pimcore\Bundle\ChangeControlBundle\Hydrator\SlotDetail;
use Pimcore\Bundle\ChangeControlBundle\Merge\LeafPath;
use Pimcore\Bundle\ChangeControlBundle\Subject\SubjectRef;
use Pimcore\Model\UserInterface;

/**
 * Renders an import configuration as the editor's own sections.
 *
 * Addresses are DOCUMENT paths, not form paths: they are handed back to the merge as exclude
 * paths, so they have to name a place in the stored tree. The editor's form flattens
 * `general.*` to its root, and the review surface translates for the fields it annotates -
 * that translation belongs on the rendering side, not here.
 *
 * `mappingConfig` is emitted whole, under its own shape, for the same reason the class editor
 * carries its layout whole: a positional address into a list is only stable until someone
 * inserts a row, so excluding "the mappings" is a decision a reviewer can actually make while
 * excluding "mapping 7" is not.
 *
 * @internal
 */
final readonly class ImportConfigDetailHydrator implements DetailHydratorInterface
{
    /** slot key => the stored sections it carries, in the order the editor shows them */
    private const array SLOTS = [
        'general' => ['general'],
        'dataSource' => ['loaderConfig', 'interpreterConfig'],
        'resolver' => ['resolverConfig'],
        'processing' => ['processingConfig'],
        'execution' => ['executionConfig'],
        'permissions' => ['permissions'],
    ];

    /** the mapping list is one address, carrying the rows as they are stored */
    private const string MAPPING_SECTION = 'mappingConfig';

    private const string MAPPING_SLOT = 'mapping';

    /** additive shape: a list of mapping rows, rendered by the importer's own surface */
    private const string SHAPE_MAPPING_LIST = 'import-mapping-list';

    public function hydrate(SubjectRef $subject, array $tree): array
    {
        $slots = [];

        foreach (self::SLOTS as $slot => $sections) {
            $values = [];
            foreach ($sections as $section) {
                if (!array_key_exists($section, $tree)) {
                    continue;
                }
                $values += $this->flatten([$section], $tree[$section]);
            }
            if ($values !== []) {
                $slots[$slot] = new SlotDetail(SlotDetail::SHAPE_RECORD, $values);
            }
        }

        if (array_key_exists(self::MAPPING_SECTION, $tree)) {
            $slots[self::MAPPING_SLOT] = new SlotDetail(
                self::SHAPE_MAPPING_LIST,
                [self::MAPPING_SECTION => $tree[self::MAPPING_SECTION]],
            );
        }

        // a section this does not name still has to reach the reviewer
        foreach ($tree as $key => $value) {
            if ($this->isKnown((string) $key)) {
                continue;
            }
            $slots[(string) $key] = new SlotDetail(SlotDetail::SHAPE_RECORD, $this->flatten([(string) $key], $value));
        }

        return $slots;
    }

    public function dehydrate(SubjectRef $subject, array $patch, array $proposed, ?UserInterface $user = null): array
    {
        // a create has no tree yet: the patch IS the document, every section of it
        if ($proposed === []) {
            return $patch;
        }

        // the surface is read-only, so a patch can only name a section the tree already has;
        // anything else is a rendering artefact and never becomes a stored leaf
        return array_filter(
            $patch,
            static fn (int|string $address): bool => array_key_exists(LeafPath::split((string) $address)[0], $proposed),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Flattens to scalar leaves. A list stays whole: its members have no stable address, and
     * a reviewer reads "the allowed types changed", not "member 2 changed".
     *
     * @param list<string> $prefix
     *
     * @return array<string, mixed>
     */
    private function flatten(array $prefix, mixed $value): array
    {
        if (!is_array($value) || $value === [] || array_is_list($value)) {
            return [LeafPath::join($prefix) => $value];
        }

        $leaves = [];
        foreach ($value as $key => $child) {
            $leaves += $this->flatten([...$prefix, (string) $key], $child);
        }

        return $leaves;
    }

    private function isKnown(string $section): bool
    {
        if ($section === self::MAPPING_SECTION) {
            return true;
        }

        foreach (self::SLOTS as $sections) {
            if (in_array($section, $sections, true)) {
                return true;
            }
        }

        return false;
    }
}
