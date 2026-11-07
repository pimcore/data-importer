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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion;

/**
 * Proposes a data object field for a source column by comparing their names.
 *
 * @internal
 *
 * @phpstan-type Column array{index: string, label: string}
 * @phpstan-type Target array{key: string, title: string, localized: bool, transformationResultType: string}
 * @phpstan-type Suggestion array{
 *     sourceIndex: string,
 *     sourceLabel: string,
 *     targetFieldName: string,
 *     targetFieldLabel: string,
 *     transformationResultType: string,
 *     score: int,
 *     language: string|null
 * }
 * @phpstan-type Name array{normalized: string, tokens: array<array-key, int>, bigrams: array<array-key, int>}
 * @phpstan-type Candidate array{target: Target, names: list<Name>}
 * @phpstan-type BestMatch array{target: Target, score: int, language: string|null}
 */
final readonly class ColumnMatcher
{
    private const int MIN_SCORE = 40;

    private const int FULL_SCORE = 100;

    // No real column name is this long: it is a header row a wrong delimiter left in one piece.
    private const int MAX_LABEL_LENGTH = 255;

    private const string LANGUAGE_PATTERN = '/^(.+?)[\s_.-]+[(\[]?([a-z]{2})[)\]]?$/iu';

    private const string REGIONAL_LANGUAGE_PATTERN = '/^(.+?)[\s_.-]+[(\[]?([a-z]{2})[_-]([a-z]{2,4})[)\]]?$/iu';

    /**
     * @param list<Column> $columns
     * @param list<Target> $targets
     * @param list<string> $validLanguages
     *
     * @return list<Suggestion> best score first
     */
    public function match(array $columns, array $targets, array $validLanguages): array
    {
        $candidates = [];
        foreach ($targets as $target) {
            $candidates[] = ['target' => $target, 'names' => $this->prepareTargetNames($target)];
        }

        $languages = [];
        foreach ($validLanguages as $language) {
            $languages[strtolower($language)] = $language;
        }

        $suggestions = [];
        foreach ($columns as $column) {
            if (mb_strlen($column['label']) > self::MAX_LABEL_LENGTH) {
                continue;
            }

            $best = $this->findBestMatch($column['label'], $candidates, $languages);
            if ($best === null) {
                continue;
            }

            $suggestions[] = [
                'sourceIndex' => $column['index'],
                'sourceLabel' => $column['label'],
                'targetFieldName' => $best['target']['key'],
                'targetFieldLabel' => $best['target']['title'],
                'transformationResultType' => $best['target']['transformationResultType'],
                'score' => $best['score'],
                'language' => $best['language'],
            ];
        }

        usort($suggestions, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $suggestions;
    }

    /**
     * The full name and the name without its language are tracked apart, so that the result does not depend on
     * which of two equally good fields comes first. On a tie the localized field with a language wins.
     *
     * @param list<Candidate> $candidates
     * @param array<string, string> $languages valid languages by their lower case spelling
     *
     * @return BestMatch|null
     */
    private function findBestMatch(string $label, array $candidates, array $languages): ?array
    {
        $name = $this->prepareName($label);
        $suffix = $this->detectLanguage($label, $languages);
        $baseName = $suffix !== null ? $this->prepareName($suffix['base']) : null;

        $fullScore = -1;
        $fullTarget = null;
        $baseScore = -1;
        $baseTarget = null;

        foreach ($candidates as $candidate) {
            $score = $this->score($name, $candidate['names']);
            if ($score > $fullScore) {
                $fullScore = $score;
                $fullTarget = $candidate['target'];
            }

            if ($baseName !== null && $candidate['target']['localized']) {
                $score = $this->score($baseName, $candidate['names']);
                if ($score > $baseScore) {
                    $baseScore = $score;
                    $baseTarget = $candidate['target'];
                }
            }
        }

        if ($baseTarget !== null && $baseScore >= $fullScore && $baseScore >= self::MIN_SCORE) {
            return ['target' => $baseTarget, 'score' => $baseScore, 'language' => $suffix['language'] ?? null];
        }

        if ($fullTarget === null || $fullScore < self::MIN_SCORE) {
            return null;
        }

        return ['target' => $fullTarget, 'score' => $fullScore, 'language' => null];
    }

    /**
     * A regional language needs to be a valid one; a two letter region of an unknown one falls back to its language.
     * Spelled like a locale (`en_DE`), the suffix is that fallback even when its region is a valid language too;
     * otherwise of two languages (`de_en`) the last one counts.
     *
     * @param array<string, string> $languages valid languages by their lower case spelling
     *
     * @return array{base: string, language: string}|null
     */
    private function detectLanguage(string $label, array $languages): ?array
    {
        $label = trim($label);
        $regional = preg_match(self::REGIONAL_LANGUAGE_PATTERN, $label, $regionalMatch) === 1;

        $fallback = null;
        if ($regional) {
            $language = $languages[strtolower($regionalMatch[2] . '_' . $regionalMatch[3])] ?? null;
            if ($language !== null) {
                return ['base' => $regionalMatch[1], 'language' => $language];
            }

            $language = strlen($regionalMatch[3]) === 2 ? $languages[strtolower($regionalMatch[2])] ?? null : null;
            if ($language !== null) {
                $fallback = ['base' => $regionalMatch[1], 'language' => $language];
                if (ctype_lower($regionalMatch[2]) && ctype_upper($regionalMatch[3])) {
                    return $fallback;
                }
            }
        }

        if (preg_match(self::LANGUAGE_PATTERN, $label, $match) === 1) {
            $language = $languages[strtolower($match[2])] ?? null;
            if ($language !== null) {
                return ['base' => $match[1], 'language' => $language];
            }
        }

        return $fallback;
    }

    /**
     * @param Name $name
     * @param list<Name> $targetNames
     */
    private function score(array $name, array $targetNames): int
    {
        $best = 0.0;
        foreach ($targetNames as $targetName) {
            if ($name['normalized'] !== '' && $name['normalized'] === $targetName['normalized']) {
                return self::FULL_SCORE;
            }

            $best = max(
                $best,
                $this->jaccard($name['tokens'], $targetName['tokens']),
                $this->jaccard($name['bigrams'], $targetName['bigrams'])
            );
        }

        return (int) round($best * self::FULL_SCORE);
    }

    /**
     * @param array<array-key, int> $a
     * @param array<array-key, int> $b
     */
    private function jaccard(array $a, array $b): float
    {
        $intersection = count(array_intersect_key($a, $b));
        $union = count($a) + count($b) - $intersection;

        return $union === 0 ? 0.0 : $intersection / $union;
    }

    /**
     * A field is known by its title, its key and, for a dotted brick path, the last part of the key.
     *
     * @param Target $target
     *
     * @return list<Name>
     */
    private function prepareTargetNames(array $target): array
    {
        $key = $target['key'];
        // a class attribute's title ends in its key, which is not part of the name a person gave the field
        $keySuffix = ' [' . $key . ']';
        $title = str_ends_with($target['title'], $keySuffix)
            ? substr($target['title'], 0, -strlen($keySuffix))
            : $target['title'];

        $names = [$this->prepareName($title), $this->prepareName($key)];

        $lastDot = strrpos($key, '.');
        if ($lastDot !== false) {
            $names[] = $this->prepareName(substr($key, $lastDot + 1));
        }

        return $names;
    }

    /**
     * @return Name
     */
    private function prepareName(string $name): array
    {
        $normalized = (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($name));

        $bigrams = [];
        for ($i = 0, $last = strlen($normalized) - 1; $i < $last; $i++) {
            $bigrams[] = substr($normalized, $i, 2);
        }

        return [
            'normalized' => $normalized,
            'tokens' => array_flip($this->tokenize($name)),
            'bigrams' => array_flip($bigrams),
        ];
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $name): array
    {
        $parts = preg_split(
            '/(?<=[a-z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])|[\s_.-]+/u',
            $name,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $tokens = [];
        foreach ($parts === false ? [] : $parts as $part) {
            $tokens[] = mb_strtolower($part);
        }

        return $tokens;
    }
}
