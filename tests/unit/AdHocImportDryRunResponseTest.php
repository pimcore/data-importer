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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportDryRunResponse;

class AdHocImportDryRunResponseTest extends Unit
{
    public function testTheResponseGivesWhatTheDryRunFound(): void
    {
        $fields = [['key' => 'name', 'language' => 'en', 'label' => 'Name']];
        $rows = [[
            'row' => 2,
            'matchValue' => 'A-1',
            'action' => 'update',
            'error' => null,
            'values' => [['old' => 'Stool', 'new' => 'Chair']],
            'invalid' => [],
        ]];
        $response = new AdHocImportDryRunResponse('SKU', '/Products', $fields, $rows);

        $this->assertSame('SKU', $response->getMatchColumn());
        $this->assertSame('/Products', $response->getNewObjectsFolder());
        $this->assertSame($fields, $response->getFields());
        $this->assertSame($rows, $response->getRows());
    }
}
