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
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\CsvFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass\InterpreterConfigurationFactoryPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class InterpreterCompilerPassTest extends Unit
{
    protected $tester;

    private const INTERPRETER_TAG = 'pimcore.datahub.data_importer.interpreter';

    private function processContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(InterpreterFactory::class, new Definition(InterpreterFactory::class));

        $csv = new Definition(CsvFileInterpreter::class);
        $csv->addTag(self::INTERPRETER_TAG, ['type' => 'csv']);
        $container->setDefinition(CsvFileInterpreter::class, $csv);

        // a tagged interpreter without a setEventDispatcher() method
        $plain = new Definition(\stdClass::class);
        $plain->addTag(self::INTERPRETER_TAG, ['type' => 'plain']);
        $container->setDefinition('test.plain_interpreter', $plain);

        (new InterpreterConfigurationFactoryPass())->process($container);

        return $container;
    }

    public function testEventDispatcherIsWiredIntoSupportingInterpreters(): void
    {
        $container = $this->processContainer();

        $calls = $container->getDefinition(CsvFileInterpreter::class)->getMethodCalls();
        $dispatcherCalls = array_values(array_filter(
            $calls,
            static fn (array $call): bool => $call[0] === 'setEventDispatcher'
        ));

        $this->assertCount(1, $dispatcherCalls);
        $this->assertEquals([new Reference('event_dispatcher')], $dispatcherCalls[0][1]);
    }

    public function testInterpretersWithoutTheSetterAreLeftAlone(): void
    {
        $container = $this->processContainer();

        $this->assertSame([], $container->getDefinition('test.plain_interpreter')->getMethodCalls());
    }

    public function testAllTaggedInterpretersEndUpInTheFactoryBlueprints(): void
    {
        $container = $this->processContainer();

        $bluePrints = $container->getDefinition(InterpreterFactory::class)->getArgument('$interpreterBluePrints');

        $this->assertEquals(
            ['csv' => new Reference(CsvFileInterpreter::class), 'plain' => new Reference('test.plain_interpreter')],
            $bluePrints
        );
    }
}
