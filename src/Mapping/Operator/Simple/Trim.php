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
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class Trim extends AbstractOperator implements SchemaAwareInterface
{
    const MODE_BOTH = 'both';

    const MODE_LEFT = 'left';

    const MODE_RIGHT = 'right';

    /**
     * @var string
     */
    protected $mode;

    public function setSettings(array $settings): void
    {
        $this->mode = $settings['mode'] ?? self::MODE_BOTH;
    }

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return array|false|mixed|null
     */
    public function process($inputData, bool $dryRun = false)
    {
        $returnScalar = false;
        if (!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }

        if ($this->mode == self::MODE_BOTH) {
            foreach ($inputData as &$data) {
                $data = trim($data);
            }
        }
        if ($this->mode == self::MODE_LEFT) {
            foreach ($inputData as &$data) {
                $data = ltrim($data);
            }
        }
        if ($this->mode == self::MODE_RIGHT) {
            foreach ($inputData as &$data) {
                $data = rtrim($data);
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

    /**
     * @param string $inputType
     * @param int|null $index
     *
     * @return string
     *
     * @throws InvalidConfigurationException
     */
    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        if (!in_array($inputType, [TransformationDataTypeService::DEFAULT_TYPE, TransformationDataTypeService::DEFAULT_ARRAY])) {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for trim operator at transformation position %s", $inputType, $index));
        }

        return $inputType;
    }

    public function getSchemaDescription(): string
    {
        return 'Trims whitespace from string values. Supports trimming both sides, left only, or right only.';
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
                    ->info('Trimming mode: "both" (default), "left", or "right"')
                    ->values([self::MODE_BOTH, self::MODE_LEFT, self::MODE_RIGHT])
                    ->defaultValue(self::MODE_BOTH)
                ->end()
            ->end();

        return $treeBuilder;
    }
}
