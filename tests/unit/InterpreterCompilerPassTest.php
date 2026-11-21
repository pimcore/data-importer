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
use Symfony\Component\DependencyInjection\ChildDefinition;
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

    private function emptyContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(InterpreterFactory::class, new Definition(InterpreterFactory::class));

        return $container;
    }

    /**
     * @return list<array<mixed>> the setEventDispatcher calls of the definition
     */
    private function dispatcherCalls(Definition $definition): array
    {
        return array_values(array_filter(
            $definition->getMethodCalls(),
            static fn (array $call): bool => $call[0] === 'setEventDispatcher'
        ));
    }

    public function testEventDispatcherIsWiredIntoSupportingInterpreters(): void
    {
        $container = $this->processContainer();

        $dispatcherCalls = $this->dispatcherCalls($container->getDefinition(CsvFileInterpreter::class));

        $this->assertCount(1, $dispatcherCalls);
        $this->assertEquals([new Reference('event_dispatcher')], $dispatcherCalls[0][1]);
    }

    public function testClassInheritedFromAnUntaggedParentIsResolved(): void
    {
        $container = $this->emptyContainer();
        $parent = (new Definition(CsvFileInterpreter::class))->setAbstract(true);
        $container->setDefinition('test.interpreter_parent', $parent);
        $child = new ChildDefinition('test.interpreter_parent');
        $child->addTag(self::INTERPRETER_TAG, ['type' => 'child']);
        $container->setDefinition('test.child_interpreter', $child);

        (new InterpreterConfigurationFactoryPass())->process($container);

        $this->assertCount(1, $this->dispatcherCalls($container->getDefinition('test.child_interpreter')));
    }

    public function testClassParameterIsResolved(): void
    {
        $container = $this->emptyContainer();
        $container->setParameter('test.interpreter_class', CsvFileInterpreter::class);
        $definition = new Definition('%test.interpreter_class%');
        $definition->addTag(self::INTERPRETER_TAG, ['type' => 'parameterized']);
        $container->setDefinition('test.parameterized_interpreter', $definition);

        (new InterpreterConfigurationFactoryPass())->process($container);

        $this->assertCount(1, $this->dispatcherCalls($container->getDefinition('test.parameterized_interpreter')));
    }

    public function testDispatcherCallInheritedFromAParentIsNotDuplicated(): void
    {
        // the child is registered first, so the parent's call must be added before the child is checked
        $container = $this->emptyContainer();
        $child = new ChildDefinition('test.parent_interpreter');
        $child->addTag(self::INTERPRETER_TAG, ['type' => 'child']);
        $container->setDefinition('test.child_interpreter', $child);
        $parent = new Definition(CsvFileInterpreter::class);
        $parent->addTag(self::INTERPRETER_TAG, ['type' => 'parent']);
        $container->setDefinition('test.parent_interpreter', $parent);

        (new InterpreterConfigurationFactoryPass())->process($container);

        $this->assertCount(1, $this->dispatcherCalls($container->getDefinition('test.parent_interpreter')));
        $this->assertSame([], $this->dispatcherCalls($container->getDefinition('test.child_interpreter')));
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
