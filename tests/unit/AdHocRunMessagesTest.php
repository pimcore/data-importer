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
        ];

        foreach ($keys as $key) {
            $this->assertArrayHasKey('data-importer.ad-hoc-import.' . $key, $catalogue);
        }
    }
}
