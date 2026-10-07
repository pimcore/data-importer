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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Processing\Scheduler\CronScheduler;
use Pimcore\Bundle\DataImporterBundle\Processing\Scheduler\JobScheduler;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * The schedule type the schema accepts has to be the set SchedulerFactory dispatches on, and
 * that set is what Studio writes: `recurring` for a cron expression and `job` for a single run.
 * A value missing from the enum makes validation reject a configuration the UI produces.
 */
class ExecutionScheduleDefinitionTest extends Unit
{
    /**
     * @var \Pimcore\Bundle\DataImporterBundle\Tests\UnitTester
     */
    protected $tester;

    private function processExecutionConfig(array $config): array
    {
        $definition = $this->tester->grabService(ConfigurationDefinition::class);

        return (new Processor())->process(
            $definition->getExecutionConfigTreeBuilder()->buildTree(),
            [$config]
        );
    }

    public function testASingleRunScheduleIsAccepted(): void
    {
        $config = $this->processExecutionConfig([
            'scheduleType' => JobScheduler::NAME,
            'scheduledAt' => '01-01-2027 08:00',
        ]);

        $this->assertSame(JobScheduler::NAME, $config['scheduleType']);
    }

    public function testTheRecurringScheduleStudioWritesIsAccepted(): void
    {
        $config = $this->processExecutionConfig([
            'scheduleType' => 'recurring',
            'cronDefinition' => '0 2 * * *',
        ]);

        $this->assertSame('recurring', $config['scheduleType']);
    }

    public function testTheStoredCronScheduleTypeStaysAccepted(): void
    {
        $config = $this->processExecutionConfig(['scheduleType' => CronScheduler::NAME]);

        $this->assertSame(CronScheduler::NAME, $config['scheduleType']);
    }

    public function testAnUnknownScheduleTypeIsStillRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('scheduleType');

        $this->processExecutionConfig(['scheduleType' => 'whenever']);
    }
}
