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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileType;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportTransformationCell;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportTransformationParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassAttributesResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportTransformationService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\MappingSuggestionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Class\Schema\ClassDefinitionList;
use Pimcore\Bundle\StudioBackendBundle\Class\Service\ClassDefinitionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Pimcore\Bundle\StudioBackendBundle\Response\ElementIcon;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\ElementIconTypes;
use Pimcore\Model\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdHocImportTransformationServiceTest extends Unit
{
    private const USER_ID = 4712;

    private const OTHER_USER_ID = 4713;

    private const CSV = "sku;name\nA-1;Chair\n";

    // a text, a numeric and an image field, besides the key no column goes into
    private const FIELD_TYPES = [
        TransformationDataTypeService::DEFAULT_TYPE => ['key', 'name'],
        TransformationDataTypeService::NUMERIC => ['price'],
        TransformationDataTypeService::ASSET => ['image'],
    ];

    private string $storage;

    private int $currentUserId = self::USER_ID;

    protected function _before(): void
    {
        $this->storage = sys_get_temp_dir() . '/' . uniqid('ad-hoc-store-', true);
        mkdir($this->storage);
    }

    protected function _after(): void
    {
        foreach (glob($this->storage . '/*/*') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->storage . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            @rmdir($directory);
        }
        @rmdir($this->storage);
    }

    public function testTheTransformationsRunOnTheColumnInTheFirstRowsOfTheStoredFile(): void
    {
        $fileId = $this->storedCsv("sku;name;price\nA-1; Chair ;12.50\nA-2;Table;3\n");

        $trimmed = $this->service()->previewTransformations(
            $fileId,
            new AdHocImportTransformationParameters('PROD', 'name', 'name', [['type' => 'trim']])
        );

        $this->assertSame([[' Chair '], ['Table']], $this->items($trimmed->getValues()));
        $this->assertSame([['Chair'], ['Table']], $this->items($trimmed->getSteps()[0]['values']));
        $this->assertSame([['Chair'], ['Table']], $this->items($trimmed->getResults()));
        $this->assertTrue($trimmed->getFits());

        $price = $this->service()->previewTransformations(
            $fileId,
            new AdHocImportTransformationParameters('PROD', 'price', 'price')
        );

        $this->assertSame('numeric', $price->getConversion());
        $this->assertSame([['12.5'], ['3']], $this->items($price->getResults()));
    }

    public function testTheFileIsReadUnderTheSettingsGiven(): void
    {
        $fileId = $this->storedCsv(self::CSV);

        $result = $this->service()->previewTransformations(
            $fileId,
            new AdHocImportTransformationParameters('PROD', '1', 'name', hasHeaderRow: false)
        );

        $this->assertSame([['name'], ['Chair']], $this->items($result->getValues()));
    }

    public function testAColumnGoesIntoAnImageOnlyOnceItsAssetIsLoaded(): void
    {
        $fileId = $this->storedCsv("sku;image\nA-1;/images/chair.jpg\n");

        $result = $this->service()->previewTransformations(
            $fileId,
            new AdHocImportTransformationParameters('PROD', 'image', 'image')
        );

        $this->assertFalse($result->getFits());
        $this->assertSame([], $result->getResults());
        $this->assertSame(['trim', 'stringReplace', 'conditionalConversion', 'loadAsset'], $result->getOffered());
    }

    public function testAColumnTheFileDoesNotHaveIsNotFound(): void
    {
        $fileId = $this->storedCsv(self::CSV);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Column Name not found');

        $this->service()->previewTransformations($fileId, new AdHocImportTransformationParameters('PROD', 'Name', 'name'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fieldsTransformationsCannotGoInto(): iterable
    {
        yield 'a field the class does not have' => ['colour'];
        yield 'a field an ad hoc import does not write' => ['key'];
    }

    /**
     * @dataProvider fieldsTransformationsCannotGoInto
     */
    public function testAFieldTheMappingDoesNotOfferIsNotFound(string $field): void
    {
        $fileId = $this->storedCsv(self::CSV);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Field ' . $field . ' not found');

        $this->service()->previewTransformations($fileId, new AdHocImportTransformationParameters('PROD', 'name', $field));
    }

    public function testTransformationsForAClassTheUserMayNotCreateAreForbidden(): void
    {
        $fileId = $this->storedCsv(self::CSV);

        $this->expectException(ForbiddenException::class);

        $this->service(['OTHER'])->previewTransformations(
            $fileId,
            new AdHocImportTransformationParameters('PROD', 'name', 'name')
        );
    }

    public function testAnotherUsersFileIsNotFound(): void
    {
        $fileId = $this->storedCsv(self::CSV);
        $this->currentUserId = self::OTHER_USER_ID;

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('File ' . $fileId . ' not found');

        $this->service()->previewTransformations($fileId, new AdHocImportTransformationParameters('PROD', 'name', 'name'));
    }

    public function testATransformationTheAdHocImportDoesNotOfferIsRefused(): void
    {
        $fileId = $this->storedCsv(self::CSV);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transformation 2');

        $this->service()->previewTransformations(
            $fileId,
            new AdHocImportTransformationParameters('PROD', 'name', 'name', [['type' => 'trim'], ['type' => 'combine']])
        );
    }

    public function testAnOperatorNoBundleRegisteredIsRefused(): void
    {
        $fileId = $this->storedCsv(self::CSV);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown operator type `stringReplace`');

        $this->service(without: ['stringReplace'])->previewTransformations(
            $fileId,
            new AdHocImportTransformationParameters('PROD', 'name', 'name', [['type' => 'stringReplace']])
        );
    }

    private function storedCsv(string $content): string
    {
        $path = $this->storage . '/upload.csv';
        file_put_contents($path, $content);
        $fileId = AdHocCsvFiles::store($this->storage)->store(self::USER_ID, FileType::Csv, $path);
        @unlink($path);

        return $fileId;
    }

    /**
     * @param list<AdHocImportTransformationCell> $cells
     *
     * @return list<list<string>>
     */
    private function items(array $cells): array
    {
        return array_map(static fn (AdHocImportTransformationCell $cell): array => $cell->getItems(), $cells);
    }

    /**
     * @param string[] $creatableClasses
     * @param list<string> $without operator types no bundle registered
     */
    private function service(array $creatableClasses = ['PROD'], array $without = []): AdHocImportTransformationService
    {
        $security = $this->createStub(SecurityServiceInterface::class);
        $security->method('getCurrentUser')->willReturnCallback(function (): User {
            $user = new User();
            $user->setId($this->currentUserId);

            return $user;
        });

        $classes = $this->createStub(ClassDefinitionServiceInterface::class);
        $classes->method('getClassDefinitionCollection')->willReturnCallback(
            static fn (bool $creatableOnly = false): array => $creatableOnly ? array_map(
                static fn (string $id): ClassDefinitionList => new ClassDefinitionList(
                    $id,
                    $id,
                    $id,
                    new ElementIcon(ElementIconTypes::NAME->value, 'class')
                ),
                $creatableClasses
            ) : []
        );

        $suggestions = $this->createStub(MappingSuggestionServiceInterface::class);
        $suggestions->method('loadTargets')->willReturn(array_map(
            static fn (string $key): array => [
                'key' => $key,
                'title' => $key,
                'localized' => false,
                'transformationResultType' => 'default',
            ],
            ['key', 'name', 'price', 'image']
        ));

        $types = $this->createStub(DataTypeServiceInterface::class);
        $types->method('loadClassAttributes')->willReturnCallback(
            static fn (string $classId, string $type): ClassAttributesResponse => new ClassAttributesResponse(array_map(
                static fn (string $key): array => ['key' => $key, 'title' => $key, 'localized' => false],
                self::FIELD_TYPES[$type] ?? []
            ))
        );

        return new AdHocImportTransformationService(
            AdHocCsvFiles::store($this->storage),
            AdHocCsvFiles::inspector(),
            $security,
            $classes,
            $suggestions,
            AdHocTransformations::chain($types, $without)
        );
    }
}
