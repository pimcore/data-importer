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
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\PreviewRowsServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\NotFoundException;
use Pimcore\Contracts\PimcoreAgent\AgentTaskRequest;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use ValueError;

class MappingSuggestionServiceAiTest extends MappingSuggestionServiceTestCase
{
    // how the task's instructions name the sample values
    private const string SAMPLES_KEY = '"samples"';

    /**
     * With the agent: the name comparison answers at once and the agent is asked about the rest.
     */
    public function testTheAgentAssignsTheColumnsTheNameComparisonLeavesOpen(): void
    {
        $agent = $this->agent(['mappings' => [
            ['column' => 'col_1', 'field' => 'color', 'language' => null, 'confidence' => 0.92],
        ]]);
        $service = $this->service(
            ['default' => [$this->attribute('sku'), $this->attribute('color')]],
            aiStage: $this->aiStage($agent)
        );

        $response = $service->suggest($this->parameters('SKU', 'Farbe'));

        $this->assertSame(['sku'], array_column($response->getSuggestions(), 'targetFieldName'));
        $this->assertSame(['name'], array_column($response->getSuggestions(), 'source'));
        $this->assertSame('running', $response->getAiStatus());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', (string) $response->getAiRequestId());

        $request = $agent->requests[0];
        $this->assertSame(self::USER_ID, $request->actingUserId);
        $this->assertSame(['col_1'], $this->outputEnum($request, 'column'));
        $this->assertSame(['color'], $this->outputEnum($request, 'field'));
        $this->assertStringContainsString('Farbe', $request->instructions);
        $this->assertStringNotContainsString('SKU', $request->instructions);

        $loaded = $service->loadAi((string) $response->getAiRequestId());

        $this->assertSame('finished', $loaded->getAiStatus());
        $this->assertSame($response->getAiRequestId(), $loaded->getAiRequestId());
        $this->assertSame([[
            'sourceIndex' => 'col_1',
            'sourceLabel' => 'Farbe',
            'targetFieldName' => 'color',
            'targetFieldLabel' => 'color [color]',
            'transformationResultType' => 'default',
            'score' => 92,
            'language' => null,
            'source' => 'ai',
        ]], $loaded->getSuggestions());
    }

    /**
     * @dataProvider stagesThatStartNoTask
     */
    public function testWithoutTheAgentOrSwitchedOffItIsTheNameComparisonAlone(
        ?bool $agentInstalled,
        bool $enabled
    ): void {
        $agent = $agentInstalled ? $this->agent() : null;
        $attributes = ['default' => [$this->attribute('sku'), $this->attribute('color')]];
        $plain = $this->service($attributes)->suggest($this->parameters('SKU', 'Farbe'));

        $response = $this->service($attributes, aiStage: $this->aiStage($agent, $enabled))
            ->suggest($this->parameters('SKU', 'Farbe'));

        $this->assertSame($plain->getSuggestions(), $response->getSuggestions());
        $this->assertSame('none', $response->getAiStatus());
        $this->assertNull($response->getAiRequestId());
        if ($agent !== null) {
            $this->assertSame([], $agent->requests);
        }
    }

    public function stagesThatStartNoTask(): array
    {
        return [
            'no agent bundle, switched on' => [false, true],
            'agent bundle, switched off' => [true, false],
            'no agent bundle, switched off' => [false, false],
        ];
    }

    public function testNothingOpenStartsNoTask(): void
    {
        $agent = $this->agent();
        $attributes = ['default' => [$this->attribute('sku'), $this->attribute('color')]];

        $noColumnOpen = $this->service($attributes, aiStage: $this->aiStage($agent))
            ->suggest($this->parameters('SKU'));
        $noFieldOpen = $this->service(['default' => [$this->attribute('sku')]], aiStage: $this->aiStage($agent))
            ->suggest($this->parameters('SKU', 'Farbe'));

        $this->assertSame('none', $noColumnOpen->getAiStatus());
        $this->assertSame('none', $noFieldOpen->getAiStatus());
        $this->assertSame([], $agent->requests);
    }

    public function testALocalizedFieldStaysOpenForItsOtherLanguagesAndItsTakenLanguageIsNotSuggestedAgain(): void
    {
        $agent = $this->agent(['mappings' => [
            ['column' => 'col_1', 'field' => 'title', 'language' => 'de', 'confidence' => 0.99],
            ['column' => 'col_2', 'field' => 'title', 'language' => 'en', 'confidence' => 0.9],
        ]]);
        $service = $this->service(
            ['default' => [$this->attribute('title', true), $this->attribute('sku')]],
            aiStage: $this->aiStage($agent)
        );

        $response = $service->suggest($this->parameters('Title DE', 'Überschrift', 'Heading'));

        $this->assertSame(['title', 'sku'], $this->outputEnum($agent->requests[0], 'field'));
        $this->assertSame(
            [['title', 'en']],
            array_map(
                static fn (array $suggestion): array => [$suggestion['targetFieldName'], $suggestion['language']],
                $service->loadAi((string) $response->getAiRequestId())->getSuggestions()
            )
        );
    }

    public function testALabelTheNameComparisonRefusesAndABlankOneNeverReachTheAgent(): void
    {
        $agent = $this->agent();
        $service = $this->service(['default' => [$this->attribute('color')]], aiStage: $this->aiStage($agent));

        $response = $service->suggest($this->parameters(str_repeat('a;', 200), '  ', 'Farbe'));

        $this->assertSame('running', $response->getAiStatus());
        $this->assertSame(['col_2'], $this->outputEnum($agent->requests[0], 'column'));

        $agent = $this->agent();
        $service = $this->service(['default' => [$this->attribute('color')]], aiStage: $this->aiStage($agent));

        $this->assertSame('none', $service->suggest($this->parameters('  '))->getAiStatus());
        $this->assertSame([], $agent->requests);
    }

    public function testAColumnSentTwiceGoesToTheAgentOnceAndAtMostSoManyColumnsGo(): void
    {
        $agent = $this->agent();
        $service = $this->service(['default' => [$this->attribute('color')]], aiStage: $this->aiStage($agent));

        $service->suggest(new MappingSuggestionParameters('Product', [
            ['index' => 'col_0', 'label' => 'Farbe'],
            ['index' => 'col_0', 'label' => 'Farbe'],
        ]));
        $this->assertSame(['col_0'], $this->outputEnum($agent->requests[0], 'column'));

        $labels = array_map(static fn (int $number): string => 'x' . $number, range(1, 201));
        $service->suggest($this->parameters(...$labels));
        $this->assertCount(200, $this->outputEnum($agent->requests[1], 'column'));
    }

    public function testSampleValuesAreSentOnlyWhenSwitchedOnAndOnlyForOpenColumns(): void
    {
        $previewRows = $this->createMock(PreviewRowsServiceInterface::class);
        $previewRows->expects($this->once())
            ->method('read')
            ->with('products', ['interpreterConfig' => ['type' => 'csv']])
            ->willReturn([
                ['sku' => 'SECRET-SKU-1', 'farbe' => 'red'],
                ['sku' => 'SECRET-SKU-2', 'farbe' => 'blue'],
            ]);

        $agent = $this->agent();
        $service = $this->service(
            ['default' => [$this->attribute('sku'), $this->attribute('color')]],
            aiStage: $this->aiStage($agent, true, true),
            previewRows: $previewRows
        );

        $service->suggest($this->parametersFromPreview());

        $instructions = $agent->requests[0]->instructions;
        $this->assertStringContainsString(self::SAMPLES_KEY, $instructions);
        $this->assertStringContainsString('red', $instructions);
        $this->assertStringContainsString('blue', $instructions);
        $this->assertStringNotContainsString('SECRET-SKU', $instructions);
    }

    public function testSwitchedOffNoRowIsEvenRead(): void
    {
        $previewRows = $this->createMock(PreviewRowsServiceInterface::class);
        $previewRows->expects($this->never())->method('read');

        $agent = $this->agent();
        $service = $this->service(
            ['default' => [$this->attribute('sku'), $this->attribute('color')]],
            aiStage: $this->aiStage($agent),
            previewRows: $previewRows
        );

        $service->suggest($this->parametersFromPreview());

        $this->assertStringNotContainsString(self::SAMPLES_KEY, $agent->requests[0]->instructions);
    }

    public function testACallerWithoutAPreviewFileGetsNoSamplesButStillTheAgent(): void
    {
        $previewRows = $this->createMock(PreviewRowsServiceInterface::class);
        $previewRows->expects($this->never())->method('read');

        $agent = $this->agent();
        $response = $this->service(
            ['default' => [$this->attribute('color')]],
            aiStage: $this->aiStage($agent, true, true),
            previewRows: $previewRows
        )->suggest($this->parameters('Farbe'));

        $this->assertSame('running', $response->getAiStatus());
        $this->assertStringNotContainsString(self::SAMPLES_KEY, $agent->requests[0]->instructions);
    }

    /**
     * @dataProvider unreadableRows
     */
    public function testUnreadableRowsCostTheSamplesNotTheAgent(Throwable $failure): void
    {
        $previewRows = $this->createStub(PreviewRowsServiceInterface::class);
        $previewRows->method('read')->willThrowException($failure);

        $agent = $this->agent();
        $response = $this->service(
            ['default' => [$this->attribute('sku'), $this->attribute('color')]],
            aiStage: $this->aiStage($agent, true, true),
            previewRows: $previewRows
        )->suggest($this->parametersFromPreview());

        $this->assertSame('running', $response->getAiStatus());
        $this->assertStringNotContainsString(self::SAMPLES_KEY, $agent->requests[0]->instructions);
    }

    public function unreadableRows(): array
    {
        return [
            'a format that cannot read rows' => [new InvalidConfigurationException('xml')],
            'a broken file' => [new InvalidInputException('row 3')],
            'a storage failure' => [new RuntimeException('storage is down')],
            'an invalid delimiter' => [new ValueError('delimiter must be a single character')],
        ];
    }

    /**
     * @dataProvider refusals
     */
    public function testARefusedConfigurationAnswersTheRefusalAndStartsNoTask(
        Throwable $refusal,
        string $expected
    ): void {
        $previewRows = $this->createStub(PreviewRowsServiceInterface::class);
        $previewRows->method('read')->willThrowException($refusal);

        $agent = $this->agent();

        try {
            $this->service(
                ['default' => [$this->attribute('sku'), $this->attribute('color')]],
                aiStage: $this->aiStage($agent, true, true),
                previewRows: $previewRows
            )->suggest($this->parametersFromPreview());
            $this->fail('the refusal was swallowed');
        } catch (Throwable $thrown) {
            $this->assertInstanceOf($expected, $thrown);
        }

        $this->assertSame([], $agent->requests);
    }

    public function refusals(): array
    {
        return [
            'may not read' => [new ForbiddenException('denied'), ForbiddenException::class],
            'unknown configuration' => [new NotFoundHttpException('unknown'), NotFoundHttpException::class],
            'denied while preparing' => [new AccessDeniedHttpException('denied'), AccessDeniedHttpException::class],
        ];
    }

    public function testAnUnknownRequestIsNotFound(): void
    {
        $service = $this->service([], aiStage: $this->aiStage($this->agent()));

        $this->expectException(NotFoundException::class);

        $service->loadAi(str_repeat('a', 32));
    }

    public function testAnotherUserCannotLoadTheSuggestionsOfARequest(): void
    {
        $stage = $this->aiStage($this->agent(['mappings' => [
            ['column' => 'col_0', 'field' => 'color', 'language' => null, 'confidence' => 0.9],
        ]]));
        $attributes = ['default' => [$this->attribute('color')]];
        $requestId = $this->service($attributes, aiStage: $stage)
            ->suggest($this->parameters('Farbe'))
            ->getAiRequestId();

        $this->expectException(NotFoundException::class);

        $this->service($attributes, aiStage: $stage, userId: self::USER_ID + 1)->loadAi((string) $requestId);
    }

    public function testAnAdHocUserLoadsTheAgentsSuggestionsOfTheirOwnRequest(): void
    {
        $service = $this->service(
            ['default' => [$this->attribute('color')]],
            aiStage: $this->aiStage($this->agent(['mappings' => [
                ['column' => 'col_0', 'field' => 'color', 'language' => null, 'confidence' => 0.9],
            ]])),
            rights: [PermissionConstants::PLUGIN_DATA_IMPORTER_AD_HOC_IMPORT],
            creatableClasses: ['Product']
        );

        $requestId = (string) $service->suggest($this->parameters('Farbe'))->getAiRequestId();
        $loaded = $service->loadAi($requestId);

        $this->assertSame('finished', $loaded->getAiStatus());
        $this->assertSame(['color'], array_column($loaded->getSuggestions(), 'targetFieldName'));
    }

    public function testAUserWithNeitherRightCannotLoadARequest(): void
    {
        $stage = $this->aiStage($this->agent());
        $attributes = ['default' => [$this->attribute('color')]];
        $requestId = (string) $this->service($attributes, aiStage: $stage)
            ->suggest($this->parameters('Farbe'))
            ->getAiRequestId();

        $this->expectException(ForbiddenException::class);

        $this->service($attributes, aiStage: $stage, rights: [])->loadAi($requestId);
    }

    public function testLoadingTheAgentsSuggestionsDispatchesTheEvent(): void
    {
        $dispatcher = new EventDispatcher();
        $events = [];
        $dispatcher->addListener(
            MappingSuggestionsEvent::EVENT_NAME,
            static function (MappingSuggestionsEvent $event) use (&$events): void {
                $events[] = $event->getMappingSuggestions()->getAiStatus();
            }
        );

        $service = $this->service(
            ['default' => [$this->attribute('color')]],
            $dispatcher,
            aiStage: $this->aiStage($this->agent())
        );

        $service->loadAi((string) $service->suggest($this->parameters('Farbe'))->getAiRequestId());

        $this->assertSame(['running', 'finished'], $events);
    }

    private function parametersFromPreview(): MappingSuggestionParameters
    {
        return new MappingSuggestionParameters(
            'Product',
            [['index' => 'sku', 'label' => 'SKU'], ['index' => 'farbe', 'label' => 'Farbe']],
            'products',
            ['interpreterConfig' => ['type' => 'csv']]
        );
    }

    /**
     * @return list<string|null>
     */
    private function outputEnum(AgentTaskRequest $request, string $property): array
    {
        return $request->outputSchema['properties']['mappings']['items']['properties'][$property]['enum'];
    }
}
