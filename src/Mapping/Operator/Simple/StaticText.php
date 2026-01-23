<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Pimcore\Bundle\DataImporterBundle\Settings\TransformationTypeAwareInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class StaticText extends AbstractOperator implements SchemaAwareInterface,
    TransformationTypeAwareInterface
{
    const MODE_APPEND = 'append';

    const MODE_PREPEND = 'prepend';

    /**
     * @var string
     */
    protected $mode;

    /**
     * @var string
     */
    protected $text;

    /**
     * @var bool
     */
    protected $alwaysAdd;

    public function setSettings(array $settings): void
    {
        $this->mode = $settings['mode'] ?? self::MODE_APPEND;
        $this->text = $settings['text'] ?? '';
        $this->alwaysAdd = $settings['alwaysAdd'] ?? false;
    }

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return array|false|mixed|null
     *
     * @throws InvalidConfigurationException
     */
    public function process($inputData, bool $dryRun = false)
    {
        $returnScalar = false;
        if (!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }

        if ($this->text !== '') {
            foreach ($inputData as &$data) {
                if (!empty($data) || $this->alwaysAdd) {
                    switch ($this->mode) {
                        case self::MODE_APPEND:
                            $data = $data . $this->text;

                            break;

                        case self::MODE_PREPEND:
                            $data = $this->text . $data;

                            break;

                        default:
                            throw new InvalidConfigurationException(sprintf('Invalid mode: %s', $this->mode));
                    }
                }
            }
        }

        if ($returnScalar) {
            if (!empty($inputData)) {
                return reset($inputData);
            }

            return null;
        } else {
            return $inputData;
        }
    }

    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        if (!in_array($inputType, [
            TransformationDataTypeService::DEFAULT_TYPE,
            TransformationDataTypeService::DEFAULT_ARRAY
        ])) {
            throw new InvalidConfigurationException(sprintf(
                "Unsupported input type '%s' for static text operator at transformation position %s",
                $inputType,
                $index
            ));
        }

        return $inputType;
    }

    public function getSchemaDescription(): string
    {
        return 'Appends or prepends static text to input values. Can optionally add text even when input is empty.';
    }


    public function getAcceptedInputTypes(): array
    {
        return [
            TransformationDataTypeService::DEFAULT_TYPE,
            TransformationDataTypeService::DEFAULT_ARRAY
        ];
    }

    public function getOutputTypes(): array
    {
        return $this->getAcceptedInputTypes();
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        $treeBuilder = new TreeBuilder('settings');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @phpstan-ignore-next-line */
        $rootNode
            ->children()
                ->enumNode('mode')
                    ->info('Mode for adding text: "append" (default) or "prepend"')
                    ->values([self::MODE_APPEND, self::MODE_PREPEND])
                    ->defaultValue(self::MODE_APPEND)
                ->end()
                ->scalarNode('text')
                    ->info('The static text to add')
                    ->defaultValue('')
                ->end()
                ->booleanNode('alwaysAdd')
                    ->info('If true, adds text even when input is empty')
                    ->defaultValue(false)
                ->end()
            ->end();

        return $treeBuilder;
    }
}
