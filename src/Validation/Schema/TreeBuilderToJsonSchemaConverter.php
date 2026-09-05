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

namespace Pimcore\Bundle\DataImporterBundle\Validation\Schema;

use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BooleanNode;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\EnumNode;
use Symfony\Component\Config\Definition\FloatNode;
use Symfony\Component\Config\Definition\IntegerNode;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Config\Definition\ScalarNode;
use Symfony\Component\Config\Definition\VariableNode;

/**
 * Converts Symfony Config TreeBuilder to JSON Schema format
 *
 * This allows us to use Symfony's native configuration system while
 * still providing JSON Schema output for AI agents and external tools
 */
class TreeBuilderToJsonSchemaConverter
{
    /**
     * Convert a TreeBuilder to JSON Schema array
     *
     * @param TreeBuilder $treeBuilder
     *
     * @return array JSON Schema compatible array
     */
    public function convert(TreeBuilder $treeBuilder): array
    {
        $rootNode = $treeBuilder->buildTree();

        return $this->convertNode($rootNode);
    }

    /**
     * Convert a configuration node to JSON Schema
     *
     * @param NodeInterface $node
     *
     * @return array JSON Schema representation
     */
    private function convertNode(NodeInterface $node): array
    {
        $schema = $this->determineTypeSchema($node);

        if ($node instanceof PrototypedArrayNode) {
            // Handle array with repeated items (prototype pattern)
            $this->populatePrototypedArraySchema($node, $schema);
        } elseif ($node instanceof ArrayNode) {
            // Handle object with named properties
            $this->populateArrayNodeSchema($node, $schema);
        }

        $this->augmentWithDescription($node, $schema);
        $this->augmentWithDefault($node, $schema);

        return $schema;
    }

    private function determineTypeSchema(NodeInterface $node): array
    {
        $schema = ['type' => 'string'];

        if ($node instanceof PrototypedArrayNode) {
            // PrototypedArrayNode is an array with repeated items
            $schema = ['type' => 'array'];
        } elseif ($node instanceof ArrayNode) {
            $schema = ['type' => 'object'];
        } elseif ($node instanceof BooleanNode) {
            $schema = ['type' => 'boolean'];
        } elseif ($node instanceof IntegerNode) {
            $schema = ['type' => 'integer'];
            if ($node->getMin() !== null) {
                $schema['minimum'] = $node->getMin();
            }
            if ($node->getMax() !== null) {
                $schema['maximum'] = $node->getMax();
            }
        } elseif ($node instanceof FloatNode) {
            $schema = ['type' => 'number'];
        } elseif ($node instanceof EnumNode) {
            $schema = ['type' => 'string', 'enum' => $node->getValues()];
        } elseif ($node instanceof ScalarNode) {
            $schema = ['type' => 'string'];
        } elseif ($node instanceof VariableNode) {
            // A bare variableNode holds a nested structure, not a scalar.
            $schema = ['type' => 'object'];
        }

        return $schema;
    }

    private function populateArrayNodeSchema(ArrayNode $node, array &$schema): void
    {
        $properties = [];
        $required = [];

        foreach ($node->getChildren() as $childName => $child) {
            $properties[$childName] = $this->convertNode($child);
            if ($child->isRequired()) {
                $required[] = $childName;
            }
        }

        if (!empty($properties)) {
            $schema['properties'] = $properties;
        }
        if (!empty($required)) {
            $schema['required'] = $required;
        }
    }

    private function populatePrototypedArraySchema(
        PrototypedArrayNode $node,
        array &$schema
    ): void {
        // Convert the prototype (template) to JSON Schema
        $prototype = $node->getPrototype();
        $schema['items'] = $this->convertNode($prototype);
    }

    private function augmentWithDescription(NodeInterface $node, array &$schema): void
    {
        if (method_exists($node, 'getInfo') && $node->getInfo()) {
            $schema['description'] = $node->getInfo();
        }
    }

    private function augmentWithDefault(NodeInterface $node, array &$schema): void
    {
        if ($node->hasDefaultValue()) {
            $defaultValue = $node->getDefaultValue();
            if ($defaultValue !== null) {
                $schema['default'] = $defaultValue;
            }
        }
    }

    /**
     * Convert TreeBuilder to a settings-only JSON Schema
     * (without the root 'settings' wrapper)
     *
     * @param TreeBuilder $treeBuilder
     *
     * @return array Unwrapped settings schema
     */
    public function convertToSettingsSchema(TreeBuilder $treeBuilder): array
    {
        $schema = $this->convert($treeBuilder);

        // If the root is 'settings', unwrap it
        if (isset($schema['properties'])) {
            return $schema['properties'];
        }

        return $schema;
    }

    /**
     * Get required fields from TreeBuilder
     *
     * @param TreeBuilder $treeBuilder
     *
     * @return array List of required field names
     */
    public function getRequiredFields(TreeBuilder $treeBuilder): array
    {
        $rootNode = $treeBuilder->buildTree();

        if (!$rootNode instanceof ArrayNode) {
            return [];
        }

        $required = [];
        foreach ($rootNode->getChildren() as $childName => $child) {
            if ($child->isRequired()) {
                $required[] = $childName;
            }
        }

        return $required;
    }
}
