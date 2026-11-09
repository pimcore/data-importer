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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch\BranchMerge;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\BranchMergeInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\DirectRowWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWriterInterface;
use Pimcore\Bundle\DataImporterBundle\Tests\UnitTester;
use Pimcore\Contracts\ChangeControl\BranchServiceInterface;

class AdHocBranchWiringTest extends Unit
{
    protected UnitTester $tester;

    public function testWithoutChangeControlTheRowsAreWrittenDirectly(): void
    {
        $this->assertTrue(
            interface_exists(BranchServiceInterface::class),
            'the contract is there, from core or its copy'
        );
        $this->assertFalse(
            $this->tester->grabService('service_container')->has(BranchServiceInterface::class),
            'no Change Control in this kernel'
        );

        $this->assertInstanceOf(DirectRowWriter::class, $this->tester->grabService(RowWriterInterface::class));
        $this->assertInstanceOf(BranchMerge::class, $this->tester->grabService(BranchMergeInterface::class));
    }
}
