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

/**
 * What a file holds under its settings; $refusal says why it cannot be imported under them.
 *
 * @internal
 */
final readonly class FileInspection
{
    /**
     * @param array{
     *     delimiter?: string,
     *     enclosure?: string,
     *     escape?: string,
     *     hasHeaderRow: bool,
     *     sheetName?: string
     * } $settings
     * @param string[] $sheets
     * @param array<int, array{dataIndex: string, label: string, position?: int}> $columns
     * @param array<int, array<int, string|null>> $firstRows the values in the order of $columns
     */
    public function __construct(
        public array $settings,
        public ?string $detectedDelimiter = null,
        public array $sheets = [],
        public int $rowCount = 0,
        public array $columns = [],
        public array $firstRows = [],
        public ?RefusalReason $refusal = null,
    ) {
    }
}
