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
 * @internal
 */
final class CsvDelimiterDetector
{
    private const string DEFAULT_DELIMITER = ',';

    // on a tie the first one wins
    private const array CANDIDATES = [';', ',', "\t", '|'];

    private const int SAMPLE_BYTES = 65536;

    private const int SAMPLE_LINES = 20;

    /**
     * The candidate that splits the first lines into the same number of columns, more than one.
     */
    public function detect(string $path): string
    {
        $lines = $this->readSampleLines($path);
        if ($lines === []) {
            return self::DEFAULT_DELIMITER;
        }

        $best = self::DEFAULT_DELIMITER;
        $bestScore = [0, 0];

        foreach (self::CANDIDATES as $candidate) {
            $counts = array_map(
                static fn (string $line): int => count(str_getcsv($line, $candidate, '"', '\\')),
                $lines
            );
            $columns = $counts[0];
            if ($columns < 2) {
                continue;
            }

            $score = [count(array_keys($counts, $columns, true)), $columns];
            if ($score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * @return string[]
     */
    private function readSampleLines(string $path): array
    {
        $sample = @file_get_contents($path, false, null, 0, self::SAMPLE_BYTES);
        if ($sample === false || $sample === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\n|\r/', $sample);
        // the sample may end inside a line
        if (strlen($sample) === self::SAMPLE_BYTES && count($lines) > 1) {
            array_pop($lines);
        }

        $nonEmpty = array_filter($lines, static fn (string $line): bool => $line !== '');

        return array_slice(array_values($nonEmpty), 0, self::SAMPLE_LINES);
    }
}
