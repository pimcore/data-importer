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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\ChangeControl;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\MappingRowIds;

class MappingRowIdsTest extends Unit
{
    private const string NOT_A_ROW = 'not a row';

    public function testTheRowIdsAreDroppedAndNothingElse(): void
    {
        $state = [
            'general' => ['name' => 'car-import', 'mappingId' => 'kept'],
            'mappingConfig' => [$this->row('VIN', 'vin', 'm1'), $this->row('Model', 'name'), self::NOT_A_ROW],
        ];

        static::assertSame([
            'general' => ['name' => 'car-import', 'mappingId' => 'kept'],
            'mappingConfig' => [$this->row('VIN', 'vin'), $this->row('Model', 'name'), self::NOT_A_ROW],
        ], MappingRowIds::dropped($state));
    }

    public function testAStateWithoutAMappingListIsLeftAsItIs(): void
    {
        static::assertSame(['general' => []], MappingRowIds::dropped(['general' => []]));
        static::assertSame(['mappingConfig' => null], MappingRowIds::dropped(['mappingConfig' => null]));
        static::assertSame(
            ['mappingConfig' => []],
            MappingRowIds::restored(['mappingConfig' => []], ['mappingConfig' => [$this->row('VIN', 'vin', 'm1')]]),
        );
        static::assertSame(
            ['mappingConfig' => [$this->row('VIN', 'vin')]],
            MappingRowIds::restored(['mappingConfig' => [$this->row('VIN', 'vin')]], []),
        );
    }

    public function testAnUnchangedRowGetsItsStoredIdBackWhereverItMoved(): void
    {
        $stored = ['mappingConfig' => [$this->row('VIN', 'vin', 'm1'), $this->row('Model', 'name', 'm2')]];
        $approved = ['mappingConfig' => [$this->row('Model', 'name'), $this->row('VIN', 'vin')]];

        static::assertSame(
            ['mappingConfig' => [$this->row('Model', 'name', 'm2'), $this->row('VIN', 'vin', 'm1')]],
            MappingRowIds::restored($approved, $stored),
        );
    }

    public function testAnEditedRowKeepsTheIdOfTheRowWithItsLabelAndSource(): void
    {
        $stored = ['mappingConfig' => [$this->row('VIN', 'vin', 'm1'), $this->row('Model', 'name', 'm2')]];
        // the edited row moved to the front, so its position names another stored row
        $approved = ['mappingConfig' => [$this->row('Model', 'title'), $this->row('VIN', 'vin')]];

        static::assertSame(
            ['mappingConfig' => [$this->row('Model', 'title', 'm2'), $this->row('VIN', 'vin', 'm1')]],
            MappingRowIds::restored($approved, $stored),
        );
    }

    public function testARelabelledRowKeepsTheIdOfTheRowAtItsPosition(): void
    {
        $stored = ['mappingConfig' => [$this->row('VIN', 'vin', 'm1'), $this->row('Model', 'name', 'm2')]];
        $approved = ['mappingConfig' => [$this->row('VIN', 'vin'), $this->row('Model name', 'name')]];

        static::assertSame(
            ['mappingConfig' => [$this->row('VIN', 'vin', 'm1'), $this->row('Model name', 'name', 'm2')]],
            MappingRowIds::restored($approved, $stored),
        );
    }

    public function testANewRowGetsNoIdAndNoIdIsGivenOutTwice(): void
    {
        $stored = ['mappingConfig' => [$this->row('VIN', 'vin', 'm1'), $this->row('Price', 'price')]];
        $approved = ['mappingConfig' => [
            $this->row('VIN', 'vin'),
            $this->row('VIN', 'vin'),
            $this->row('Price', 'price'),
            $this->row('Year', 'year'),
        ]];

        static::assertSame(
            ['mappingConfig' => [
                $this->row('VIN', 'vin', 'm1'),
                $this->row('VIN', 'vin'),
                $this->row('Price', 'price'),
                $this->row('Year', 'year'),
            ]],
            MappingRowIds::restored($approved, $stored),
        );
    }

    public function testAnIdTheApprovedStateCarriesStaysAndIsNotGivenToAnotherRow(): void
    {
        $stored = ['mappingConfig' => [$this->row('VIN', 'vin', 'm1'), $this->row('Model', 'name', 'm2')]];
        $approved = ['mappingConfig' => [$this->row('VIN', 'vin'), $this->row('Chassis', 'vin', 'm1'), self::NOT_A_ROW]];

        static::assertSame(
            ['mappingConfig' => [$this->row('VIN', 'vin'), $this->row('Chassis', 'vin', 'm1'), self::NOT_A_ROW]],
            MappingRowIds::restored($approved, $stored),
        );
    }

    public function testAStoredRowThatIsNoArrayGivesNoId(): void
    {
        $stored = ['mappingConfig' => [self::NOT_A_ROW, $this->row('VIN', 'vin', 'm1')]];
        $approved = ['mappingConfig' => [$this->row('Year', 'year'), $this->row('VIN', 'vin')]];

        static::assertSame(
            ['mappingConfig' => [$this->row('Year', 'year'), $this->row('VIN', 'vin', 'm1')]],
            MappingRowIds::restored($approved, $stored),
        );
    }

    public function testKeyOrderAndAnIdlessStoredRowDoNotMatter(): void
    {
        $storedRow = ['dataTarget' => ['settings' => ['fieldName' => 'vin'], 'type' => 'direct'], 'label' => 'VIN',
            'dataSourceIndex' => ['vin'], 'mappingId' => 'm1'];
        $stored = ['mappingConfig' => [$this->row('Year', 'year', ''), $storedRow]];
        $approved = ['mappingConfig' => [$this->row('Year', 'year'), $this->row('VIN', 'vin')]];

        static::assertSame(
            ['mappingConfig' => [$this->row('Year', 'year'), $this->row('VIN', 'vin', 'm1')]],
            MappingRowIds::restored($approved, $stored),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $label, string $field, ?string $id = null): array
    {
        $row = [
            'label' => $label,
            'dataSourceIndex' => [$label === 'Chassis' ? 'vin' : $label],
            'dataTarget' => ['type' => 'direct', 'settings' => ['fieldName' => $field]],
        ];
        if ($id !== null) {
            $row['mappingId'] = $id;
        }

        return $row;
    }
}
