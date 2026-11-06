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
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Tool\DataObjectLoader;

class DataObjectLoaderAttributeNameTest extends Unit
{
    public static function invalidAttributeNames(): array
    {
        return [
            'condition in a partial match' => ['sku = 1 OR 1=1 --'],
            'condition in a brick attribute' => ['attributes.Brick.size) OR (1=1'],
            'too many brick parts' => ['attributes.Brick.size.extra'],
            'two brick parts' => ['attributes.Brick'],
            'trailing newline' => ["sku\n"],
            'quote' => ["sku'"],
            'empty' => [''],
        ];
    }

    /**
     * @dataProvider invalidAttributeNames
     */
    public function testAnAttributeNameThatIsNoFieldNameIsRefused(string $attributeName): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new DataObjectLoader())->loadByAttribute(
            '\\Pimcore\\Model\\DataObject\\Concrete',
            $attributeName,
            'A-100',
            '',
            false,
            1,
            'LIKE'
        );
    }
}
