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

namespace Pimcore\Bundle\DataImporterBundle\Tests\Unit\ChangeControl;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\LoaderSettingsCheck;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\AssetLoader;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\DataLoaderInterface;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\HttpLoader;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;

class LoaderSettingsCheckTest extends Unit
{
    private const array CONFIGURATION = ['loaderConfig' => ['type' => 'vendor.loader', 'settings' => ['a' => 1]]];

    private int $errorReporting;

    protected function _before(): void
    {
        $this->errorReporting = error_reporting(E_ALL);
    }

    protected function _after(): void
    {
        error_reporting($this->errorReporting);
    }

    public function testASilencedNoticeWhileReadingTheSettingsIsNoProblem(): void
    {
        $check = $this->check(static function (): void {
            @trigger_error('a silenced notice', E_USER_NOTICE);
        });

        static::assertSame([], $check->problems(self::CONFIGURATION));
    }

    public function testADeprecationWhileReadingTheSettingsIsNoProblem(): void
    {
        $check = $this->check(static function (): void {
            trigger_error('a deprecated option', E_USER_DEPRECATED);
        });

        static::assertSame([], $check->problems(self::CONFIGURATION));
    }

    public function testAWarningWhileReadingTheSettingsIsAProblem(): void
    {
        $check = $this->check(static function (): void {
            trigger_error('a missing setting', E_USER_WARNING);
        });

        static::assertSame(
            ['loaderConfig: the "vendor.loader" loader cannot read its settings: a missing setting.'],
            $check->problems(self::CONFIGURATION)
        );
    }

    public function testAWarningIsAProblemWhenNothingIsReportedAnyway(): void
    {
        error_reporting(0);
        $check = $this->check(static function (): void {
            trigger_error('a missing setting', E_USER_WARNING);
        });

        static::assertSame(
            ['loaderConfig: the "vendor.loader" loader cannot read its settings: a missing setting.'],
            $check->problems(self::CONFIGURATION)
        );
    }

    public function testAnHttpLoaderWithoutItsSchemaNamesTheSettingsItNeeds(): void
    {
        $problems = $this->realLoaders()->problems(
            ['loaderConfig' => ['type' => 'http', 'settings' => ['url' => 'https://example.com/feed.json']]]
        );

        static::assertCount(1, $problems);
        static::assertStringStartsWith('loaderConfig: Empty Schema.', $problems[0]);
        static::assertStringContainsString('settings.schema ("https://" or "http://")', $problems[0]);
        static::assertStringContainsString('settings.url (the address without its scheme', $problems[0]);
    }

    public function testAnHttpLoaderWithoutSettingsNamesTheSettingsItNeeds(): void
    {
        $problems = $this->realLoaders()->problems(['loaderConfig' => ['type' => 'http']]);

        static::assertCount(1, $problems);
        static::assertStringStartsWith('loaderConfig: Empty URL.', $problems[0]);
        static::assertStringContainsString('settings.schema', $problems[0]);
        static::assertStringContainsString('settings.url', $problems[0]);
    }

    public function testAnHttpLoaderWithSchemaAndUrlIsNoProblem(): void
    {
        static::assertSame([], $this->realLoaders()->problems(['loaderConfig' => [
            'type' => 'http',
            'settings' => ['schema' => 'https://', 'url' => 'example.com/feed.json'],
        ]]));
    }

    public function testALoaderWithoutAKnownShapeKeepsItsOwnMessage(): void
    {
        static::assertSame(
            ['loaderConfig: Empty asset path.'],
            $this->realLoaders()->problems(['loaderConfig' => ['type' => 'asset', 'settings' => ['assetPath' => '']]])
        );
    }

    public function testAWarningOfAnHttpLoaderAlsoNamesTheSettingsItNeeds(): void
    {
        $check = $this->check(static function (): void {
            trigger_error('a missing setting', E_USER_WARNING);
        }, 'http');

        static::assertSame(
            ['loaderConfig: the "http" loader cannot read its settings: a missing setting. '
                . 'The "http" loader needs settings.schema ("https://" or "http://") and settings.url '
                . '(the address without its scheme, e.g. "example.com/feed.json").'],
            $check->problems(['loaderConfig' => ['type' => 'http', 'settings' => []]])
        );
    }

    private function realLoaders(): LoaderSettingsCheck
    {
        return new LoaderSettingsCheck(new ServiceLocator([
            'asset' => static fn (): AssetLoader => new AssetLoader(),
            'http' => static fn (): HttpLoader => new HttpLoader(new Filesystem()),
        ]));
    }

    private function check(callable $onSettings, string $type = 'vendor.loader'): LoaderSettingsCheck
    {
        $loader = new class($onSettings) implements DataLoaderInterface {
            /** @var callable */
            private $onSettings;

            public function __construct(callable $onSettings)
            {
                $this->onSettings = $onSettings;
            }

            public function setSettings(array $settings): void
            {
                ($this->onSettings)();
            }

            public function loadData(): string
            {
                return '';
            }

            public function cleanup(): void
            {
                // nothing to clean up: the check never loads data
            }
        };

        return new LoaderSettingsCheck(
            new ServiceLocator([$type => static fn (): DataLoaderInterface => $loader])
        );
    }
}
