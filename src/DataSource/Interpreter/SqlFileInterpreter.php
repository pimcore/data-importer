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

namespace Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter;

use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;

class SqlFileInterpreter extends JsonFileInterpreter implements SchemaAwareInterface
{
    public function getSchemaDescription(): string
    {
        return 'Interpret SQL query results (inherits JSON interpreter settings)';
    }

    public function getConfigTreeBuilder(): \Symfony\Component\Config\Definition\Builder\TreeBuilder
    {
        return parent::getConfigTreeBuilder();
    }
}
