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
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportDryRunParameters;

/**
 * The dry run's request as the service reads it.
 */
class AdHocImportDryRunParametersTest extends Unit
{
    public function testTheRequestKeepsItsMatchMappingAndFileSettings(): void
    {
        $columns = [['column' => '0', 'field' => 'name']];
        $parameters = new AdHocImportDryRunParameters(
            folderId: 7,
            classId: 'CAR',
            matchBy: AdHocImportDryRunParameters::MATCH_BY_FIELD,
            matchColumn: '2',
            matchField: 'sku',
            matchLanguage: 'en',
            newObjectsFolder: '/cars/new',
            columns: $columns,
            configuration: 'cars',
            configurationColumns: ['sku' => '2'],
            delimiter: ';',
            enclosure: "'",
            escape: '\\',
            hasHeaderRow: false,
            sheetName: 'Products',
        );

        $this->assertSame(
            [7, 'CAR', 'field', '2', 'sku', 'en', '/cars/new', $columns, 'cars', ['sku' => '2']],
            [
                $parameters->getFolderId(),
                $parameters->getClassId(),
                $parameters->getMatchBy(),
                $parameters->getMatchColumn(),
                $parameters->getMatchField(),
                $parameters->getMatchLanguage(),
                $parameters->getNewObjectsFolder(),
                $parameters->getColumns(),
                $parameters->getConfiguration(),
                $parameters->getConfigurationColumns(),
            ]
        );

        $settings = $parameters->toSettingsInput();
        $this->assertSame(
            [';', "'", '\\', false, 'Products'],
            [
                $settings->delimiter,
                $settings->enclosure,
                $settings->escape,
                $settings->hasHeaderRow,
                $settings->sheetName,
            ]
        );
    }

    public function testWithoutFileSettingsTheFileIsReadWithItsDefaults(): void
    {
        $settings = (new AdHocImportDryRunParameters(1, 'CAR', AdHocImportDryRunParameters::MATCH_BY_ID, '0'))
            ->toSettingsInput();

        $this->assertSame(
            [null, null, null, true, null],
            [
                $settings->delimiter,
                $settings->enclosure,
                $settings->escape,
                $settings->hasHeaderRow,
                $settings->sheetName,
            ]
        );
    }
}
