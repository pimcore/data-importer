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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\MappingSuggestion;

use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\MappingSuggestionsEvent;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\NotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcher;

class MappingSuggestionServiceTest extends MappingSuggestionServiceTestCase
{
    public function testItSuggestsFromTheClassAttributesWithTheLanguageGuessedFromTheColumnName(): void
    {
        $response = $this->service([
            'default' => [$this->attribute('bezeichnung', true), $this->attribute('sku')],
        ])->suggest($this->parameters('Bezeichnung DE', 'SKU', 'unrelated'));

        $this->assertSame([
            [
                'sourceIndex' => 'col_0',
                'sourceLabel' => 'Bezeichnung DE',
                'targetFieldName' => 'bezeichnung',
                'targetFieldLabel' => 'bezeichnung [bezeichnung]',
                'transformationResultType' => 'default',
                'score' => 100,
                'language' => 'de',
                'source' => 'name',
            ],
            [
                'sourceIndex' => 'col_1',
                'sourceLabel' => 'SKU',
                'targetFieldName' => 'sku',
                'targetFieldLabel' => 'sku [sku]',
                'transformationResultType' => 'default',
                'score' => 100,
                'language' => null,
                'source' => 'name',
            ],
        ], $response->getSuggestions());
    }

    public function testAFieldOfAnotherResultTypeIsSuggestedWithThatType(): void
    {
        $response = $this->service(['date' => [$this->attribute('validFrom')]])
            ->suggest($this->parameters('valid_from'));

        $this->assertSame('date', $response->getSuggestions()[0]['transformationResultType']);
    }

    public function testAFieldOfferedBySeveralResultTypesKeepsTheFirst(): void
    {
        $response = $this->service([
            'boolean' => [$this->attribute('name'), $this->attribute('image')],
            'default' => [$this->attribute('name')],
            'asset' => [$this->attribute('image')],
        ])->suggest($this->parameters('name', 'image'));

        $this->assertSame(
            ['default', 'boolean'],
            array_column($response->getSuggestions(), 'transformationResultType')
        );
    }

    public function testItAsksForTheWritableFieldsOfEveryResultTypeASuggestionCanWrite(): void
    {
        $requests = [];
        $this->service([], null, true, $requests)->suggest($this->parameters('name'));

        $this->assertSame([
            ['Product', ['default', 'numeric'], false, true, false],
            ['Product', 'array', false, true, false],
            ['Product', 'boolean', false, true, false],
            ['Product', 'date', false, true, false],
            ['Product', 'quantityValue', false, true, false],
            ['Product', 'asset', false, true, false],
            ['Product', 'assetArray', false, true, false],
            ['Product', 'gallery', false, true, false],
            ['Product', 'dataObject', false, true, false],
            ['Product', 'dataObjectArray', false, true, false],
        ], $requests);
    }

    public function testAnAttributeWithoutTheLocalizedFlagIsNotLocalized(): void
    {
        $response = $this->service(['default' => [['key' => 'name', 'title' => 'Name']]])
            ->suggest($this->parameters('name_de'));

        $this->assertNull($response->getSuggestions()[0]['language']);
    }

    public function testAnUnknownClassIsNotFoundAndLoadsNothing(): void
    {
        $requests = [];
        $service = $this->service([], null, false, $requests);

        try {
            $service->suggest($this->parameters('name'));
            $this->fail('expected a NotFoundException');
        } catch (NotFoundException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        $this->assertSame([], $requests);
    }

    public function testThePreResponseEventCarriesTheResponse(): void
    {
        $dispatcher = new EventDispatcher();
        $seen = null;
        $dispatcher->addListener(
            MappingSuggestionsEvent::EVENT_NAME,
            static function (MappingSuggestionsEvent $event) use (&$seen): void {
                $seen = $event->getMappingSuggestions();
            }
        );

        $response = $this->service([], $dispatcher)->suggest($this->parameters('name'));

        $this->assertSame($response, $seen);
    }

    /**
     * One case per role: the endpoint has no route-level right, so these are its access rules.
     *
     * @dataProvider roles
     *
     * @param string[] $rights
     * @param string[] $creatableClasses
     */
    public function testWhoGetsSuggestions(array $rights, array $creatableClasses, bool $allowed): void
    {
        $requests = [];
        $service = $this->service(
            ['default' => [$this->attribute('sku')]],
            null,
            true,
            $requests,
            rights: $rights,
            creatableClasses: $creatableClasses
        );

        if (!$allowed) {
            try {
                $service->suggest($this->parameters('SKU'));
                $this->fail('expected a ForbiddenException');
            } catch (ForbiddenException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
            $this->assertSame([], $requests, 'a refused request reads no class field');

            return;
        }

        $suggestions = $service->suggest($this->parameters('SKU'))->getSuggestions();
        $this->assertSame([[
            'sourceIndex' => 'col_0',
            'sourceLabel' => 'SKU',
            'targetFieldName' => 'sku',
            'targetFieldLabel' => 'sku [sku]',
            'transformationResultType' => 'default',
            'score' => 100,
            'language' => null,
            'source' => 'name',
        ]], $suggestions);
    }

    public function roles(): array
    {
        $config = PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG;
        $adHoc = PermissionConstants::PLUGIN_DATA_IMPORTER_AD_HOC_IMPORT;

        return [
            'neither right' => [[], ['Product'], false],
            'another data hub right only' => [[PermissionConstants::PLUGIN_DATA_IMPORTER_ADAPTER], ['Product'], false],
            'only the ad hoc right, a class it may create' => [[$adHoc], ['Product'], true],
            'only the ad hoc right, a class it may not create' => [[$adHoc], ['Category'], false],
            'only the configuration right, any class' => [[$config], [], true],
            'both rights, any class' => [[$config, $adHoc], [], true],
        ];
    }

    public function testAnAdHocUserAskingForAnUnknownClassIsNotFound(): void
    {
        $requests = [];
        $this->expectException(NotFoundException::class);

        $this->service(
            [],
            null,
            false,
            $requests,
            rights: [PermissionConstants::PLUGIN_DATA_IMPORTER_AD_HOC_IMPORT],
            creatableClasses: ['Product']
        )->suggest($this->parameters('SKU'));
    }
}
