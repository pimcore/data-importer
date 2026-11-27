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
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\Classificationstore;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\ClassificationstoreBatch;

/**
 * The Studio UI only offers a language for localized classification store fields, so mappings to a non-localized
 * field are saved without `language`. Such a mapping must not throw, for the single key and the batch target.
 */
class ClassificationstoreDataTargetTest extends Unit
{
    protected $tester;

    private const REQUIRED = ['fieldName' => 'attributes', 'keyId' => '5-29'];

    private function readProperty(object $object, string $name): mixed
    {
        return (new \ReflectionProperty($object, $name))->getValue($object);
    }

    public function testWithoutLanguage(): void
    {
        $target = new Classificationstore();

        $target->setSettings(self::REQUIRED);

        $this->assertNull($this->readProperty($target, 'language'));
        $this->assertSame(5, $this->readProperty($target, 'groupId'));
        $this->assertSame(29, $this->readProperty($target, 'keyId'));
    }

    public function testKeepsLanguage(): void
    {
        $target = new Classificationstore();

        $target->setSettings(self::REQUIRED + ['language' => 'en']);

        $this->assertSame('en', $this->readProperty($target, 'language'));
    }

    public function testBatchWithoutLanguage(): void
    {
        $target = new ClassificationstoreBatch();

        $target->setSettings(['fieldName' => 'attributes']);

        $this->assertNull($this->readProperty($target, 'language'));
    }

    public function testBatchKeepsLanguage(): void
    {
        $target = new ClassificationstoreBatch();

        $target->setSettings(['fieldName' => 'attributes', 'language' => 'en']);

        $this->assertSame('en', $this->readProperty($target, 'language'));
    }
}
