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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RecordedRow;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowFailure;
use Symfony\Component\Yaml\Yaml;

class AdHocRunMessagesTest extends Unit
{
    private const string CATALOGUE = __DIR__ . '/../../src/Resources/translations/studio.en.yaml';

    public function testANotificationCountsWhatTheRunDid(): void
    {
        $text = AdHocRunFixtures::runMessages()->notificationText(
            AdHocRunFixtures::run(['updated' => 2, 'created' => 1, 'failed' => 0, 'skipped' => 3]),
            [],
            AdHocRunFixtures::user()
        );

        $this->assertStringContainsString('"{{updated}}":"2"', $text);
        $this->assertStringContainsString('"{{created}}":"1"', $text);
        $this->assertStringContainsString('"{{folder}}":"/Products"', $text);
        $this->assertStringContainsString('"{{skipped}}":"3"', $text);
        $this->assertSame(0, substr_count($text, "\n"), 'no rows to list');
    }

    public function testANotificationListsTheRowsNotSavedUpToALimit(): void
    {
        $failures = [];
        for ($row = 2; $row < 26; $row++) {
            $failures[] = new RowFailure($row, 'SKU-' . $row, 'update', 'no', []);
        }

        $text = AdHocRunFixtures::runMessages()->notificationText(
            AdHocRunFixtures::run(['failed' => 24]),
            $failures,
            AdHocRunFixtures::user()
        );
        $lines = explode("\n", $text);

        $this->assertCount(22, $lines, 'the summary, 20 rows and how many more');
        $this->assertStringContainsString('"{{row}}":"2"', $lines[1]);
        $this->assertStringContainsString('"{{value}}":"SKU-2"', $lines[1]);
        $this->assertStringContainsString('notification.more {"{{count}}":"4"}', $lines[21]);
    }

    public function testEveryTextOfARunIsInTheCatalogue(): void
    {
        $catalogue = Yaml::parseFile(self::CATALOGUE);
        $keys = [
            'run.may-not-save', 'run.may-not-create', 'run.may-not-edit-language', 'run.left-out-by-listener',
            'run.cannot-go-on', 'run.must-be-unique', 'run.save-failed', 'run.save-failed-unknown',
            'run.notification.title', 'run.notification.summary', 'run.notification.row', 'run.notification.more',
            'merge.branch-name', 'merge.object-gone', 'merge.invalid-value', 'merge.missing-new-object',
            'merge.interrupted', 'merge.notice', 'merge.timed-out', 'merge.decision.title', 'merge.decision.summary',
            'check.key-taken',
        ];

        foreach ($keys as $key) {
            $this->assertArrayHasKey('data-importer.ad-hoc-import.' . $key, $catalogue);
        }
    }

    public function testEachKindOfMergeProblemIsToldInTheImportsOwnWords(): void
    {
        $messages = AdHocRunFixtures::runMessages();
        $user = AdHocRunFixtures::user();
        $update = new RecordedRow(2, 42, null, 'SKU-2', 'update', 'SKU-2', '/Products/Archive', [], []);
        $create = new RecordedRow(3, null, 'row-3', 'SKU-3', 'create', 'SKU-3', '/Products', [], []);

        $this->assertSame([
            'data-importer.ad-hoc-import.run.may-not-save {"{{folder}}":"/Products/Archive"}',
            'data-importer.ad-hoc-import.run.may-not-create {"{{folder}}":"/Products"}',
            'data-importer.ad-hoc-import.check.key-taken {"{{key}}":"SKU-3","{{folder}}":"/Products"}',
            'data-importer.ad-hoc-import.run.save-failed-unknown',
            'data-importer.ad-hoc-import.merge.object-gone',
            'data-importer.ad-hoc-import.merge.invalid-value',
            'data-importer.ad-hoc-import.merge.missing-new-object',
            'data-importer.ad-hoc-import.merge.interrupted',
            'data-importer.ad-hoc-import.run.save-failed-unknown',
            'data-importer.ad-hoc-import.run.save-failed-unknown',
            'data-importer.ad-hoc-import.run.save-failed-unknown',
        ], [
            $messages->mergeProblem('not_allowed', $update, $user),
            $messages->mergeProblem('not_allowed', $create, $user),
            $messages->mergeProblem('invalid_place', $create, $user),
            $messages->mergeProblem('invalid_place', $update, $user),
            $messages->mergeProblem('object_gone', $update, $user),
            $messages->mergeProblem('invalid_value', $update, $user),
            $messages->mergeProblem('missing_new_object', $create, $user),
            $messages->mergeProblem('interrupted', $update, $user),
            $messages->mergeProblem('write_failed', $update, $user),
            $messages->mergeProblem('conflict', $update, $user),
            $messages->mergeProblem('a_kind_added_later', null, $user),
        ]);
    }

    public function testADecisionIsAskedForWithTheCountsAndTheRows(): void
    {
        $text = AdHocRunFixtures::runMessages()->decisionText(
            AdHocRunFixtures::run(['updated' => 300, 'created' => 5, 'failed' => 4, 'targetFolder' => '/Products/Outdoor']),
            [new RowFailure(41, 'NH-1090', 'create', 'taken', [])],
            AdHocRunFixtures::user()
        );
        $lines = explode("\n", $text);

        $this->assertStringContainsString('"{{ready}}":"305","{{failed}}":"4","{{folder}}":"/Products/Outdoor"', $lines[0]);
        $this->assertStringContainsString('"{{row}}":"41"', $lines[1]);
    }

    public function testANotificationTellsWhatConcernsNoRow(): void
    {
        $text = AdHocRunFixtures::runMessages()->notificationText(
            AdHocRunFixtures::run()->withNotice('The merge did not finish in time.'),
            [],
            AdHocRunFixtures::user()
        );

        $this->assertSame('The merge did not finish in time.', explode("\n", $text)[1]);
    }
}
