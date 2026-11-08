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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\StoreKey;

class AdHocStoreKeyTest extends Unit
{
    public function testAKeyIsReadFromTheDataTargetsId(): void
    {
        $key = StoreKey::fromId('3-17');

        $this->assertNotNull($key);
        $this->assertSame(3, $key->groupId);
        $this->assertSame(17, $key->keyId);
        $this->assertSame('3-17', $key->id());
    }

    public function testAnythingElseIsNoKey(): void
    {
        foreach (['', '3', '3-', '-17', '0-17', '3-0', '3-17-1', ' 3-17', 'a-b', '03-17'] as $id) {
            $this->assertNull(StoreKey::fromId($id), var_export($id, true));
        }
        $this->assertNull(StoreKey::fromId(317));
        $this->assertNull(StoreKey::fromId(null));
    }
}
