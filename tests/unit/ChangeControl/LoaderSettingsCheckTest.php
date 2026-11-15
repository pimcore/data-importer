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
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\DataLoaderInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

class LoaderSettingsCheckTest extends Unit
{
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

        static::assertSame([], $check->problems(['loaderConfig' => ['type' => 'vendor.loader', 'settings' => ['a' => 1]]]));
    }

    public function testADeprecationWhileReadingTheSettingsIsNoProblem(): void
    {
        $check = $this->check(static function (): void {
            trigger_error('a deprecated option', E_USER_DEPRECATED);
        });

        static::assertSame([], $check->problems(['loaderConfig' => ['type' => 'vendor.loader', 'settings' => ['a' => 1]]]));
    }

    public function testAWarningWhileReadingTheSettingsIsAProblem(): void
    {
        $check = $this->check(static function (): void {
            trigger_error('a missing setting', E_USER_WARNING);
        });

        static::assertSame(
            ['loaderConfig: the "vendor.loader" loader cannot read its settings: a missing setting.'],
            $check->problems(['loaderConfig' => ['type' => 'vendor.loader', 'settings' => ['a' => 1]]])
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
            $check->problems(['loaderConfig' => ['type' => 'vendor.loader', 'settings' => ['a' => 1]]])
        );
    }

    private function check(callable $onSettings): LoaderSettingsCheck
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
            }
        };

        return new LoaderSettingsCheck(new ServiceLocator(['vendor.loader' => static fn (): DataLoaderInterface => $loader]));
    }
}
