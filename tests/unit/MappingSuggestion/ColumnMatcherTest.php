<?php declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\MappingSuggestion;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion\ColumnMatcher;

class ColumnMatcherTest extends Unit
{
    private const array LANGUAGES = ['de', 'en', 'de_AT', 'zh_Hans'];

    private function target(string $key, string $title = '', bool $localized = false, string $type = 'default'): array
    {
        return [
            'key' => $key,
            'title' => $title === '' ? $key . ' [' . $key . ']' : $title,
            'localized' => $localized,
            'transformationResultType' => $type,
        ];
    }

    private function matchOne(string $label, array $targets, array $languages = self::LANGUAGES): ?array
    {
        $suggestions = (new ColumnMatcher())->match([['index' => 'col', 'label' => $label]], $targets, $languages);

        return $suggestions[0] ?? null;
    }

    public function testAColumnNamedLikeTheKeyIsAFullMatchWithTheWholeSuggestion(): void
    {
        $suggestion = $this->matchOne('Product_Name', [
            $this->target('sku'),
            $this->target('productName', 'Name [productName]', false, 'array'),
        ]);

        $this->assertSame([
            'sourceIndex' => 'col',
            'sourceLabel' => 'Product_Name',
            'targetFieldName' => 'productName',
            'targetFieldLabel' => 'Name [productName]',
            'transformationResultType' => 'array',
            'score' => 100,
            'language' => null,
        ], $suggestion);
    }

    public function testAColumnNamedLikeTheFieldTitleIsAFullMatch(): void
    {
        $suggestion = $this->matchOne('Bezeichnung', [$this->target('description', 'Bezeichnung [description]')]);

        $this->assertSame('description', $suggestion['targetFieldName']);
        $this->assertSame(100, $suggestion['score']);
    }

    public function testTheLastPartOfADottedBrickKeyCounts(): void
    {
        $suggestion = $this->matchOne('cylinders', [$this->target('attributes.Engine.cylinders', 'Zylinder')]);

        $this->assertSame('attributes.Engine.cylinders', $suggestion['targetFieldName']);
        $this->assertSame(100, $suggestion['score']);
    }

    public function testASimilarNameScoresBelowAFullMatch(): void
    {
        $suggestion = $this->matchOne('product name long', [$this->target('productName')]);

        $this->assertSame(71, $suggestion['score']);
    }

    public function testAMatchBelowTheMinimumScoreIsNoSuggestion(): void
    {
        $this->assertNull($this->matchOne('weight', [$this->target('color')]));
    }

    public function testTheScoreAtTheMinimumIsStillASuggestion(): void
    {
        // two of five tokens are shared
        $suggestion = $this->matchOne('a b', [$this->target('a_b_c_d_e', 'x')]);

        $this->assertSame(40, $suggestion['score']);
    }

    public function testTheBestFieldWinsAndTheFirstOfEquallyGoodOnes(): void
    {
        $targets = [$this->target('nameOne', 'Size'), $this->target('nameTwo', 'Size'), $this->target('size')];

        $this->assertSame('nameOne', $this->matchOne('Size', $targets)['targetFieldName']);
        $this->assertSame('size', $this->matchOne('size', array_reverse($targets))['targetFieldName']);
    }

    public function testNamesWithoutAnyLatinLetterDoNotMatchEachOther(): void
    {
        $this->assertNull($this->matchOne('Цена', [$this->target('price', '価格')]));
    }

    public function testANameWithoutLatinLettersStillMatchesTheSameTitle(): void
    {
        $this->assertSame(100, $this->matchOne('Цена', [$this->target('price', 'Цена')])['score']);
    }

    public function testAnEmptyHeaderCellIsNoSuggestion(): void
    {
        $this->assertNull($this->matchOne('', [$this->target('name')]));
    }

    public function testAnOverlongLabelIsNoSuggestion(): void
    {
        $this->assertNull($this->matchOne(str_repeat('name;', 60), [$this->target('name')]));
    }

    /**
     * @dataProvider languageSuffixes
     */
    public function testTheLanguageIsGuessedFromTheColumnName(string $label, string $language): void
    {
        $suggestion = $this->matchOne($label, [
            $this->target('sku'),
            $this->target('bezeichnung', 'Bezeichnung [bezeichnung]', true),
        ]);

        $this->assertSame('bezeichnung', $suggestion['targetFieldName']);
        $this->assertSame(100, $suggestion['score']);
        $this->assertSame($language, $suggestion['language']);
    }

    public function languageSuffixes(): array
    {
        return [
            'space, upper case' => ['Bezeichnung DE', 'de'],
            'underscore' => ['bezeichnung_en', 'en'],
            'dot' => ['bezeichnung.de', 'de'],
            'hyphen' => ['bezeichnung-DE', 'de'],
            'parentheses' => ['Bezeichnung (DE)', 'de'],
            'brackets' => ['Bezeichnung [de]', 'de'],
            'surrounding space' => [' Bezeichnung DE ', 'de'],
            'a valid regional language keeps its spelling' => ['bezeichnung_de_at', 'de_AT'],
            'a valid script language keeps its spelling' => ['Bezeichnung zh_HANS', 'zh_Hans'],
            'an unknown region falls back to its language' => ['bezeichnung_en_GB', 'en'],
            'a lower case unknown region falls back to its language' => ['bezeichnung_en_gb', 'en'],
            'a region that is a valid language too falls back to the language' => ['bezeichnung_en_DE', 'en'],
            'a bracketed region that is a valid language too' => ['Bezeichnung (en_DE)', 'en'],
        ];
    }

    public function testOfTwoLanguagesTheLastOneCounts(): void
    {
        $suggestion = $this->matchOne('bezeichnung de_en', [$this->target('bezeichnung', 'x', true)]);

        $this->assertSame('en', $suggestion['language']);
    }

    /**
     * @dataProvider notALanguage
     */
    public function testNoLanguageIsGuessedFromANameWithoutAValidOne(string $label): void
    {
        $suggestion = $this->matchOne($label, [$this->target('bezeichnung', 'Bezeichnung [bezeichnung]', true)]);

        $this->assertSame('bezeichnung', $suggestion['targetFieldName']);
        $this->assertNull($suggestion['language']);
    }

    public function notALanguage(): array
    {
        return [
            'not a language of this system' => ['Bezeichnung FR'],
            'no separator' => ['BezeichnungDE'],
            'a word after the language' => ['Bezeichnung_de_long'],
            'no language at all' => ['Bezeichnung'],
        ];
    }

    public function testTheSuffixOnlyCountsAsALanguageForALocalizedField(): void
    {
        $suggestion = $this->matchOne('Name DE', [$this->target('name')]);

        $this->assertSame('name', $suggestion['targetFieldName']);
        $this->assertLessThan(100, $suggestion['score']);
        $this->assertNull($suggestion['language']);
    }

    public function testALocalizedFieldWithALanguageWinsATieOverTheFullName(): void
    {
        $suggestion = $this->matchOne('name_de', [$this->target('name_de'), $this->target('name', 'Name', true)]);

        $this->assertSame('name', $suggestion['targetFieldName']);
        $this->assertSame('de', $suggestion['language']);
    }

    public function testABetterFullNameWinsOverALocalizedFieldThatMatchesLess(): void
    {
        $suggestion = $this->matchOne('short_name_de', [
            $this->target('short_name_de'),
            $this->target('shortNameLong', 'x', true),
        ]);

        $this->assertSame('short_name_de', $suggestion['targetFieldName']);
        $this->assertNull($suggestion['language']);
    }

    public function testSuggestionsComeBestScoreFirstAndKeepTheColumnOrderOnATie(): void
    {
        $suggestions = (new ColumnMatcher())->match(
            [
                ['index' => '0', 'label' => 'product name long'],
                ['index' => '1', 'label' => 'sku'],
                ['index' => '2', 'label' => 'nothing alike here'],
                ['index' => '3', 'label' => 'SKU'],
            ],
            [$this->target('sku'), $this->target('productName')],
            []
        );

        $this->assertSame(['1', '3', '0'], array_column($suggestions, 'sourceIndex'));
    }

    public function testWithoutFieldsOrColumnsThereAreNoSuggestions(): void
    {
        $matcher = new ColumnMatcher();

        $this->assertSame([], $matcher->match([['index' => '0', 'label' => 'sku']], [], self::LANGUAGES));
        $this->assertSame([], $matcher->match([], [$this->target('sku')], self::LANGUAGES));
    }
}
