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
use Pimcore\Bundle\DataImporterBundle\Hydrator\MappingSuggestionHydrator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion\AiSuggestionStage;
use Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion\ColumnMatcher;
use Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion\SampleValuePicker;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassAttributesResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\MappingSuggestionService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\PreviewRowsServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\SampleValueReader;
use Pimcore\Bundle\StaticResolverBundle\Lib\ToolResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\User;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

abstract class MappingSuggestionServiceTestCase extends Unit
{
    use ScriptedAiStageTrait;

    protected const int USER_ID = 7;

    /**
     * @param array<string, list<array<string, mixed>>> $attributesByType
     */
    protected function service(
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
            new SampleValueReader(
                new SampleValuePicker(),
                $previewRows ?? $this->createStub(PreviewRowsServiceInterface::class),
                new NullLogger()
            ),
            $this->security($userId)
        );
    }

    protected function security(int $userId): SecurityServiceInterface
    {
        $user = new User();
        $user->setId($userId);

        $security = $this->createStub(SecurityServiceInterface::class);
        $security->method('getCurrentUser')->willReturn($user);

        return $security;
    }

    protected function parameters(string ...$labels): MappingSuggestionParameters
    {
        $columns = [];
        foreach ($labels as $index => $label) {
            $columns[] = ['index' => 'col_' . $index, 'label' => $label];
        }

        return new MappingSuggestionParameters('Product', $columns);
    }

    protected function attribute(string $key, bool $localized = false): array
    {
        return ['key' => $key, 'title' => $key . ' [' . $key . ']', 'localized' => $localized];
    }
}
