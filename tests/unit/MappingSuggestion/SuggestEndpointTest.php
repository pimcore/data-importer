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
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\MappingSuggestion\LoadAiController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\MappingSuggestion\SuggestController;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionsResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\MappingSuggestionServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use ReflectionMethod;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;

/**
 * The bundle's only suite runs without HTTP, so the endpoint's contract is checked on its parts.
 */
class SuggestEndpointTest extends Unit
{
    private const string SNAPSHOT_PATH = __DIR__ . '/../../../assets/studio/build/api/docs.jsonopenapi.json';

    private const string GENERATED_CLIENT_PATH =
        __DIR__ . '/../../../assets/studio/js/src/modules/data-importer/data-importer-api-slice.gen.ts';

    private const string SOURCE_LABEL = 'Bezeichnung DE';

    private const array SUGGESTION = [
        'sourceIndex' => 'col_0',
        'sourceLabel' => self::SOURCE_LABEL,
        'targetFieldName' => 'bezeichnung',
        'targetFieldLabel' => 'Bezeichnung [bezeichnung]',
        'transformationResultType' => 'default',
        'score' => 100,
        'language' => 'de',
        'source' => 'name',
    ];

    private function violations(MappingSuggestionParameters $parameters): int
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validate($parameters)
            ->count();
    }

    public function testTheRouteTakesAPostAndNeedsTheDataImporterConfigPermission(): void
    {
        $method = new ReflectionMethod(SuggestController::class, 'suggest');

        $route = $method->getAttributes(Route::class)[0]->getArguments();
        $this->assertSame('/mapping-suggestions', $route['path']);
        $this->assertSame(['POST'], $route['methods']);

        $this->assertSame(
            PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG,
            $method->getAttributes(IsGranted::class)[0]->newInstance()->attribute
        );
    }

    public function testTheAnswerIsTheServicesSuggestionsAsJson(): void
    {
        $parameters = new MappingSuggestionParameters('Product', [['index' => 'col_0', 'label' => self::SOURCE_LABEL]]);

        $service = $this->createMock(MappingSuggestionServiceInterface::class);
        $service->expects($this->once())
            ->method('suggest')
            ->with($parameters)
            ->willReturn(new MappingSuggestionsResponse([self::SUGGESTION]));

        $controller = new SuggestController(
            new Serializer([new ObjectNormalizer()], [new JsonEncoder()]),
            $service
        );

        $response = $controller->suggest($parameters);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            [self::SUGGESTION],
            json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)['suggestions']
        );
    }

    public function testTheAiRouteTakesAGetForAnIdAndNeedsTheDataImporterConfigPermission(): void
    {
        $method = new ReflectionMethod(LoadAiController::class, 'load');

        $route = $method->getAttributes(Route::class)[0]->getArguments();
        $this->assertSame('/mapping-suggestions/ai/{requestId}', $route['path']);
        $this->assertSame(['GET'], $route['methods']);
        $this->assertMatchesRegularExpression('/^' . $route['requirements']['requestId'] . '$/', str_repeat('a', 32));
        $this->assertDoesNotMatchRegularExpression('/^' . $route['requirements']['requestId'] . '$/', '../secret');

        $this->assertSame(
            PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG,
            $method->getAttributes(IsGranted::class)[0]->newInstance()->attribute
        );
    }

    public function testTheAiAnswerIsTheStagesStatusAndItsSuggestionsAsJson(): void
    {
        $requestId = str_repeat('a', 32);

        $service = $this->createMock(MappingSuggestionServiceInterface::class);
        $service->expects($this->once())
            ->method('loadAi')
            ->with($requestId)
            ->willReturn(new MappingSuggestionsResponse([self::SUGGESTION], 'finished', $requestId));

        $controller = new LoadAiController(new Serializer([new ObjectNormalizer()], [new JsonEncoder()]), $service);

        $answer = json_decode($controller->load($requestId)->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([self::SUGGESTION], $answer['suggestions']);
        $this->assertSame('finished', $answer['aiStatus']);
        $this->assertSame($requestId, $answer['aiRequestId']);
    }

    public function testARequestMayNameAConfigurationForItsPreviewFile(): void
    {
        $parameters = new MappingSuggestionParameters('Product', [], 'products', ['interpreterConfig' => []]);

        $this->assertSame(0, $this->violations($parameters));
        $this->assertSame('products', $parameters->getConfigName());
        $this->assertSame(['interpreterConfig' => []], $parameters->getCurrentConfig());
    }

    /**
     * What the ad-hoc import and the mapping step share: a class and column names, no stored configuration.
     */
    public function testARequestNeedsOnlyAClassAndColumnsAndAcceptsNoColumnsAtAll(): void
    {
        $this->assertSame(0, $this->violations(new MappingSuggestionParameters('Product', [
            ['index' => 'col_0', 'label' => self::SOURCE_LABEL],
        ])));
        $this->assertSame(0, $this->violations(new MappingSuggestionParameters('Product', [])));
    }

    /**
     * @dataProvider invalidParameters
     */
    public function testAnInvalidRequestIsRefused(string $classId, array $columns): void
    {
        $this->assertGreaterThan(0, $this->violations(new MappingSuggestionParameters($classId, $columns)));
    }

    public function invalidParameters(): array
    {
        return [
            'blank class' => ['', []],
            'a column is no object' => ['Product', ['sku']],
            'a column lacks its label' => ['Product', [['index' => 'col_0']]],
            'a column lacks its index' => ['Product', [['label' => 'SKU']]],
            'a label is no string' => ['Product', [['index' => 'col_0', 'label' => ['SKU']]]],
            'an index is null' => ['Product', [['index' => null, 'label' => 'SKU']]],
            'an unknown field' => ['Product', [['index' => 'col_0', 'label' => 'SKU', 'value' => 'A-1']]],
            'more columns than a spreadsheet has' => [
                'Product',
                array_fill(0, 16385, ['index' => 'col_0', 'label' => 'SKU']),
            ],
        ];
    }

    public function testACommittedSnapshotAndGeneratedClientKnowTheEndpointAndItsFields(): void
    {
        $snapshot = json_decode(file_get_contents(self::SNAPSHOT_PATH), true, 512, JSON_THROW_ON_ERROR);

        $operation = $snapshot['paths']['/pimcore-studio/api/bundle/data-importer/mapping-suggestions']['post'];
        $this->assertSame('bundle_data_importer_mapping_suggestion_suggest', $operation['operationId']);

        $aiPath = '/pimcore-studio/api/bundle/data-importer/mapping-suggestions/ai/{requestId}';
        $this->assertSame(
            'bundle_data_importer_mapping_suggestion_load_ai',
            $snapshot['paths'][$aiPath]['get']['operationId']
        );

        $response = $snapshot['components']['schemas']['BundleDataImporterMappingSuggestionsResponse'];
        $this->assertSame(['suggestions', 'aiStatus', 'aiRequestId'], $response['required']);
        $this->assertSame(
            array_keys(self::SUGGESTION),
            array_keys($response['properties']['suggestions']['items']['properties'])
        );

        $client = file_get_contents(self::GENERATED_CLIENT_PATH);
        $this->assertStringContainsString('useBundleDataImporterMappingSuggestionSuggestMutation', $client);
        $this->assertStringContainsString('useBundleDataImporterMappingSuggestionLoadAiQuery', $client);
        foreach (array_keys(self::SUGGESTION) as $property) {
            $this->assertStringContainsString($property . ':', $client);
        }
    }
}
