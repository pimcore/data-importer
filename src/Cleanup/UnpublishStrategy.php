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

namespace Pimcore\Bundle\DataImporterBundle\Cleanup;

use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * @internal
 */
final class UnpublishStrategy implements CleanupStrategyInterface, SchemaAwareInterface
{
    public function doCleanup(ElementInterface $element): void
    {
        if (method_exists($element, 'setPublished')) {
            $element->setPublished(false);
            $element->save();
        }
    }

    public function getSchemaDescription(): string
    {
        return 'Unpublish elements that are no longer in the import data';
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        // No configuration options - return null for better performance
        return null;
    }
}
