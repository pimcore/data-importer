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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Apply;

use Pimcore\Bundle\DataImporterBundle\Exception\MappingApplicationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfiguration;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Model\Element\ElementInterface;

/**
 * A mapping built once by MappingApplier::prepare(), to be applied to any number of elements and rows.
 */
final class PreparedMapping
{
    /**
     * @internal use MappingApplier::prepare()
     *
     * @param array<int|string, MappingConfiguration> $items
     */
    public function __construct(
        private readonly array $items,
        private readonly ImportProcessingService $importProcessingService,
        private readonly MappingApplicationScope $scope,
    ) {
    }

    /**
     * Applies the mapping to the element in memory. The element is not saved, no Data Importer event is dispatched and
     * the shipped operators write nothing to the application logger: what they would log during an import is returned
     * instead, e.g. a reference they could not resolve. The value of such an item is null, which the Direct data target
     * writes unless `writeIfSourceIsEmpty` is disabled.
     *
     * The data targets read the current values of the element (e.g. `writeIfTargetIsNotEmpty`, the merge mode of
     * many-to-many relations, active classification store groups), so pass the element the result should build on.
     *
     * @param array<int|string, mixed> $row import data row, keyed like the rows of the Data Importer
     *
     * @return list<MappingIssue> the warnings, in the order of the items
     *
     * @throws MappingApplicationException with the warnings up to and including the failing item
     */
    public function apply(
        ElementInterface $element,
        array $row,
        ?ReferenceLookupInterface $referenceLookup = null
    ): array {
        return $this->scope->run($referenceLookup, function () use ($element, $row): array {
            $warnings = [];
            foreach ($this->items as $index => $item) {
                try {
                    $this->importProcessingService->processElementTransformations($element, $row, [$item]);
                } catch (\Throwable $exception) {
                    // operators report invalid input as \TypeError as well
                    throw new MappingApplicationException(
                        $index,
                        $item->getLabel(),
                        $exception,
                        [...$warnings, ...$this->takeWarnings($index, $item)]
                    );
                }

                array_push($warnings, ...$this->takeWarnings($index, $item));
            }

            return $warnings;
        });
    }

    /**
     * @return list<MappingIssue>
     */
    private function takeWarnings(int|string $index, MappingConfiguration $item): array
    {
        return array_map(
            static fn (string $message): MappingIssue => new MappingIssue($index, $item->getLabel(), $message),
            $this->scope->takeWarnings()
        );
    }
}
