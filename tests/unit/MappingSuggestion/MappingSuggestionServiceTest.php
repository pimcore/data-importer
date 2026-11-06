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

use Codeception\Test\Unit;
use LogicException;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\MappingSuggestionsEvent;
use Pimcore\Bundle\DataImporterBundle\Hydrator\MappingSuggestionHydrator;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion\AiSuggestionStage;
use Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion\ColumnMatcher;
use Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion\SampleValuePicker;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassAttributesResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\MappingSuggestionService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\PreviewRowsServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Lib\ToolResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\Tool\TmpStoreResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\NotFoundException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Contracts\PimcoreAgent\AgentTaskInfo;
use Pimcore\Contracts\PimcoreAgent\AgentTaskRequest;
use Pimcore\Contracts\PimcoreAgent\AgentTaskResult;
use Pimcore\Contracts\PimcoreAgent\AgentTaskServiceInterface;
use Pimcore\Contracts\PimcoreAgent\AgentTaskStatus;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\Tool\TmpStore;
use Pimcore\Model\User;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use ValueError;

class MappingSuggestionServiceTest extends Unit
{
    private const int USER_ID = 7;

    /**
     * @param array<string, list<array<string, mixed>>> $attributesByType
     */
    private function service(
        array $attributesByType,
        ?EventDispatcherInterface $dispatcher = null,
        bool $classExists = true,
        array &$requests = [],
        ?AiSuggestionStage $aiStage = null,
        ?PreviewRowsServiceInterface $previewRows = null,
        int $userId = self::USER_ID
    ): MappingSuggestionService {
        $dataTypes = $this->createStub(DataTypeServiceInterface::class);
        $dataTypes->method('loadClassAttributes')->willReturnCallback(
            static function (
                string $classId,
                string|array $type,
                bool $read,
                bool $write,
                bool $advanced
            ) use ($attributesByType, &$requests): ClassAttributesResponse {
                $requests[] = [$classId, $type, $read, $write, $advanced];

                return new ClassAttributesResponse($attributesByType[is_array($type) ? $type[0] : $type] ?? []);
            }
        );

        $classes = $this->createStub(ClassDefinitionResolverInterface::class);
        $classes->method('getById')->willReturn($classExists ? new ClassDefinition() : null);

        $tool = $this->createStub(ToolResolverInterface::class);
        $tool->method('getValidLanguages')->willReturn(['de', 'en']);

        return new MappingSuggestionService(
            $dataTypes,
            $classes,
            $tool,
            new ColumnMatcher(),
            new MappingSuggestionHydrator(),
            $dispatcher ?? new EventDispatcher(),
            $aiStage ?? $this->aiStage(null),
            new SampleValuePicker(),
            $previewRows ?? $this->createStub(PreviewRowsServiceInterface::class),
            $this->security($userId),
            new NullLogger()
        );
    }

    private function security(int $userId): SecurityServiceInterface
    {
        $user = new User();
        $user->setId($userId);

        $security = $this->createStub(SecurityServiceInterface::class);
        $security->method('getCurrentUser')->willReturn($user);

        return $security;
    }

    /**
     * A task service that remembers its requests and answers with the given output.
     */
    private function agent(array $output = []): AgentTaskServiceInterface
    {
        return new class($output) implements AgentTaskServiceInterface {
            /** @var list<AgentTaskRequest> */
            public array $requests = [];

            public function __construct(private readonly array $output)
            {
            }

            public function start(AgentTaskRequest $request): AgentTaskInfo
            {
                $this->requests[] = $request;

                return new AgentTaskInfo('task-1', 'session-1', '', AgentTaskStatus::Running, null);
            }

            public function continueTask(
                string $previousTaskId,
                string $instructions,
                ?int $deadlineMinutes = null,
                ?int $maxAutoContinues = null,
                ?array $outputSchema = null
            ): AgentTaskInfo {
                throw new LogicException('not used');
            }

            public function get(string $taskId): AgentTaskInfo
            {
                throw new LogicException('not used');
            }

            public function waitFor(string $taskId, int $timeoutSeconds): AgentTaskResult
            {
                return new AgentTaskResult($taskId, AgentTaskStatus::Succeeded, ['output' => $this->output]);
            }

            public function cancel(string $taskId): void
            {
            }
        };
    }

    /**
     * A stage on an in-memory temporary store.
     */
    private function aiStage(
        ?AgentTaskServiceInterface $agent,
        bool $enabled = true,
        bool $sendSampleValues = false
    ): AiSuggestionStage {
        $store = new class() {
            public array $entries = [];
        };

        $tmpStore = $this->createStub(TmpStoreResolverInterface::class);
        $tmpStore->method('add')->willReturnCallback(static function (string $id, mixed $data) use ($store): bool {
            $store->entries[$id] = $data;

            return true;
        });
        $tmpStore->method('get')->willReturnCallback(static function (string $id) use ($store): ?TmpStore {
            if (!isset($store->entries[$id])) {
                return null;
            }

            $entry = new TmpStore();
            $entry->setData($store->entries[$id]);

            return $entry;
        });

        return new AiSuggestionStage($agent, $tmpStore, new NullLogger(), $enabled, $sendSampleValues);
    }

    private function parameters(string ...$labels): MappingSuggestionParameters
    {
        $columns = [];
        foreach ($labels as $index => $label) {
            $columns[] = ['index' => 'col_' . $index, 'label' => $label];
        }

        return new MappingSuggestionParameters('Product', $columns);
    }

    private function attribute(string $key, bool $localized = false): array
    {
        return ['key' => $key, 'title' => $key . ' [' . $key . ']', 'localized' => $localized];
    }

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
        $this->assertStringContainsString('"samples"', $instructions);
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

        $this->assertStringNotContainsString('"samples"', $agent->requests[0]->instructions);
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
        $this->assertStringNotContainsString('"samples"', $agent->requests[0]->instructions);
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
        $this->assertStringNotContainsString('"samples"', $agent->requests[0]->instructions);
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
