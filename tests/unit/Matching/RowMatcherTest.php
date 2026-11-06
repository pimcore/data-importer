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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Matching;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplier;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\MatchingConfiguration;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatch;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatcher;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatcherFactory;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatchOutcome;
use Pimcore\Bundle\DataImporterBundle\Tool\DataObjectLoader;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Objectbrick;
use Pimcore\Model\DataObject\Service;
use Pimcore\Tests\Support\Util\TestHelper;

class RowMatcherTest extends Unit
{
    private const CLASS_NAME = 'DiRowMatcherClass';

    private const OTHER_CLASS_NAME = 'DiRowMatcherOtherClass';

    private const BRICK_NAME = 'DiRowMatcherBrick';

    private const OTHER_BRICK_NAME = 'DiRowMatcherOtherBrick';

    private const CLASS_NAMESPACE = '\\Pimcore\\Model\\DataObject\\';

    private const ROOT = '/di-row-matcher';

    private const TARGET = self::ROOT . '/target';

    private const COLUMN = 'match';

    private const NEW_FOLDER = self::ROOT . '/new-objects';

    private const ELSEWHERE = self::ROOT . '/elsewhere';

    private const MISSING = self::ROOT . '/missing';

    private const BRICK_ATTRIBUTE = '.code';

    /**
     * @var \Pimcore\Bundle\DataImporterBundle\Tests\UnitTester
     */
    protected $tester;

    private ClassDefinition $class;

    protected function _before(): void
    {
        $this->class = ClassDefinition::getByName(self::CLASS_NAME) ?? $this->createClass();
        Service::createFolderByPath(self::TARGET);
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
    }

    public function testAFieldMatchInTheTargetFolderIsTheExistingObject(): void
    {
        $object = $this->createObject(self::TARGET, 'SKU-1');

        $match = $this->matchOne($this->byField(), 'SKU-1');

        $this->assertSame(RowMatchOutcome::Existing, $match->outcome);
        $this->assertSame('SKU-1', $match->matchValue);
        $this->assertSame($object->getId(), $match->element?->getId());
        $this->assertFalse($match->isError());
    }

    public function testAFieldMatchBelowTheTargetFolderKeepsItsPlaceAndKey(): void
    {
        $object = $this->createObject(self::TARGET . '/2026/spring', 'SKU-1', 'own-key');

        $match = $this->matchOne($this->byField(), 'SKU-1');

        $this->assertSame($object->getId(), $match->element?->getId());
        $this->assertSame($object->getParentId(), $match->element->getParentId());
        $this->assertSame('own-key', $match->element->getKey());
    }

    public function testAnExistingObjectKeepsItsPublishedState(): void
    {
        $published = $this->createObject(self::TARGET, 'SKU-1');
        $unpublished = $this->createObject(self::TARGET, 'SKU-2', null, false);
        $matcher = $this->matcher($this->byField());

        $first = $matcher->match($this->row('SKU-1'), 2);
        $second = $matcher->match($this->row('SKU-2'), 3);

        $this->assertSame($published->getId(), $first->element?->getId());
        $this->assertTrue($first->element->getPublished());
        $this->assertSame($unpublished->getId(), $second->element?->getId());
        $this->assertFalse($second->element->getPublished());
    }

    public function testAnUnpublishedObjectIsFoundWhileUnpublishedOnesAreHiddenAndTheSettingStays(): void
    {
        $object = $this->createObject(self::TARGET, 'SKU-1', null, false);
        $hideUnpublished = DataObject::doHideUnpublished();
        DataObject::setHideUnpublished(true);

        try {
            $match = $this->matchOne($this->byField(), 'SKU-1');

            $this->assertSame($object->getId(), $match->element?->getId());
            $this->assertTrue(DataObject::doHideUnpublished());
        } finally {
            DataObject::setHideUnpublished($hideUnpublished);
        }
    }

    public function testSeveralFieldMatchesAreAnErrorRowNamingThem(): void
    {
        $first = $this->createObject(self::TARGET, 'SKU-1');
        $second = $this->createObject(self::TARGET . '/sub', 'SKU-1');

        $match = $this->matchOne($this->byField(), 'SKU-1');

        $this->assertSame(RowMatchOutcome::SeveralMatches, $match->outcome);
        $this->assertTrue($match->isError());
        $this->assertNull($match->element);
        $this->assertEqualsCanonicalizing([$first->getId(), $second->getId()], $match->objectIds);
    }

    public function testAFieldMatchOnlyOutsideTheTargetFolderIsAnErrorRowNamingIt(): void
    {
        $outside = $this->createObject(self::ELSEWHERE, 'SKU-1');

        $match = $this->matchOne($this->byField(), 'SKU-1');

        $this->assertSame(RowMatchOutcome::OutsideTargetFolder, $match->outcome);
        $this->assertSame([$outside->getId()], $match->objectIds);
        $this->assertNull($match->element);
    }

    public function testASiblingFolderWithTheSamePrefixIsOutsideTheTargetFolder(): void
    {
        $sibling = $this->createObject(self::TARGET . '-2', 'SKU-1');

        $match = $this->matchOne($this->byField(), 'SKU-1');

        $this->assertSame(RowMatchOutcome::OutsideTargetFolder, $match->outcome);
        $this->assertSame([$sibling->getId()], $match->objectIds);
    }

    public function testAMatchInTheTargetFolderWinsOverOneOutsideIt(): void
    {
        $this->createObject(self::ELSEWHERE, 'SKU-1');
        $inside = $this->createObject(self::TARGET, 'SKU-1');

        $match = $this->matchOne($this->byField(), 'SKU-1');

        $this->assertSame(RowMatchOutcome::Existing, $match->outcome);
        $this->assertSame($inside->getId(), $match->element?->getId());
    }

    public function testTheTreeRootAsTargetFolderMatchesEverywhere(): void
    {
        $object = $this->createObject(self::ELSEWHERE, 'SKU-1');
        $configuration = $this->configuration(targetFolder: '/', attributeName: 'sku');

        $this->assertSame($object->getId(), $this->matchOne($configuration, 'SKU-1')->element?->getId());
        $this->assertSame(
            $object->getId(),
            $this->matchOne($this->configuration(targetFolder: '/'), (string) $object->getId())->element?->getId()
        );
    }

    public function testAnObjectIdInTheTargetFolderIsTheExistingObject(): void
    {
        $object = $this->createObject(self::TARGET . '/sub', 'SKU-1', null, false);

        $match = $this->matchOne($this->byObjectId(), (string) $object->getId());

        $this->assertSame(RowMatchOutcome::Existing, $match->outcome);
        $this->assertSame($object->getId(), $match->element?->getId());
        $this->assertFalse($match->element->getPublished());
    }

    public function testAnObjectIdOutsideTheTargetFolderIsAnErrorRowNamingIt(): void
    {
        $outside = $this->createObject(self::ELSEWHERE, 'SKU-1');

        $match = $this->matchOne($this->byObjectId(), (string) $outside->getId());

        $this->assertSame(RowMatchOutcome::OutsideTargetFolder, $match->outcome);
        $this->assertSame([$outside->getId()], $match->objectIds);
    }

    public function testAnObjectIdOfAFolderOrAnotherClassIsAnErrorRow(): void
    {
        $folder = Service::createFolderByPath(self::TARGET . '/sub');
        $other = $this->createObject(self::TARGET, 'SKU-1', null, true, self::OTHER_CLASS_NAME);
        $matcher = $this->matcher($this->byObjectId());

        $folderMatch = $matcher->match($this->row((string) $folder->getId()), 2);
        $otherMatch = $matcher->match($this->row((string) $other->getId()), 3);

        $this->assertSame(RowMatchOutcome::OtherClass, $folderMatch->outcome);
        $this->assertSame([$folder->getId()], $folderMatch->objectIds);
        $this->assertSame(RowMatchOutcome::OtherClass, $otherMatch->outcome);
        $this->assertSame([$other->getId()], $otherMatch->objectIds);
    }

    public function testAnObjectIdThatFindsNothingIsAnErrorRow(): void
    {
        $match = $this->matchOne($this->byObjectId(), '999999999');

        $this->assertSame(RowMatchOutcome::UnknownObjectId, $match->outcome);
        $this->assertSame('999999999', $match->matchValue);
        $this->assertNull($match->element);
    }

    public static function valuesThatAreNoObjectId(): array
    {
        return [
            'text after the digits' => ['%dabc'],
            'a decimal' => ['%d.5'],
            'negative' => ['-%d'],
        ];
    }

    /**
     * @dataProvider valuesThatAreNoObjectId
     */
    public function testAValueThatIsNoObjectIdIsAnErrorRowAndNotCastToOne(string $format): void
    {
        $object = $this->createObject(self::TARGET, 'SKU-1');
        $value = sprintf($format, $object->getId());

        $match = $this->matchOne($this->byObjectId(), $value);

        $this->assertSame(RowMatchOutcome::UnknownObjectId, $match->outcome);
        $this->assertSame($value, $match->matchValue);
        $this->assertNull($match->element);
    }

    public function testANewObjectIsUnsavedUnpublishedInTheTargetFolderAndKeyedByTheValue(): void
    {
        $target = DataObject::getByPath(self::TARGET);

        $match = $this->matchOne($this->byField(), 'SKU-1');

        $this->assertSame(RowMatchOutcome::New, $match->outcome);
        $this->assertInstanceOf(ltrim(self::CLASS_NAMESPACE, '\\') . self::CLASS_NAME, $match->element);
        $this->assertNull($match->element->getId());
        $this->assertSame('SKU-1', $match->element->getKey());
        $this->assertFalse($match->element->getPublished());
        $this->assertSame($target->getId(), $match->element->getParentId());
        $this->assertNull(DataObject::getByPath(self::TARGET . '/SKU-1'));
    }

    public function testANewObjectGoesToTheFolderForNewObjects(): void
    {
        $folder = Service::createFolderByPath(self::NEW_FOLDER);
        $configuration = $this->configuration(attributeName: 'sku', newObjectFolder: self::NEW_FOLDER);

        $match = $this->matchOne($configuration, 'SKU-1');

        $this->assertSame($folder->getId(), $match->element?->getParentId());
    }

    public function testAnObjectInTheFolderForNewObjectsIsFoundWhenTheFolderLiesOutsideTheTarget(): void
    {
        $made = $this->createObject(self::NEW_FOLDER, 'SKU-1');
        $byField = $this->configuration(attributeName: 'sku', newObjectFolder: self::NEW_FOLDER);
        $byId = $this->configuration(newObjectFolder: self::NEW_FOLDER);

        $this->assertSame($made->getId(), $this->matchOne($byField, 'SKU-1')->element?->getId());
        $this->assertSame($made->getId(), $this->matchOne($byId, (string) $made->getId())->element?->getId());
    }

    public function testAMatchInTheTargetAndOneInTheFolderForNewObjectsAreSeveralMatches(): void
    {
        $inside = $this->createObject(self::TARGET, 'SKU-1');
        $made = $this->createObject(self::NEW_FOLDER, 'SKU-1');
        $configuration = $this->configuration(attributeName: 'sku', newObjectFolder: self::NEW_FOLDER);

        $match = $this->matchOne($configuration, 'SKU-1');

        $this->assertSame(RowMatchOutcome::SeveralMatches, $match->outcome);
        $this->assertEqualsCanonicalizing([$inside->getId(), $made->getId()], $match->objectIds);
    }

    public function testAMatchInNeitherFolderStaysOutside(): void
    {
        Service::createFolderByPath(self::NEW_FOLDER);
        $outside = $this->createObject(self::ELSEWHERE, 'SKU-1');
        $byField = $this->configuration(attributeName: 'sku', newObjectFolder: self::NEW_FOLDER);
        $byId = $this->configuration(newObjectFolder: self::NEW_FOLDER);

        $this->assertSame(RowMatchOutcome::OutsideTargetFolder, $this->matchOne($byField, 'SKU-1')->outcome);
        $this->assertSame(
            RowMatchOutcome::OutsideTargetFolder,
            $this->matchOne($byId, (string) $outside->getId())->outcome
        );
    }

    public function testTheKeyOfANewObjectIsAValidKey(): void
    {
        $match = $this->matchOne($this->byField(), 'A/B');

        $this->assertSame('A/B', $match->matchValue);
        $this->assertSame('A-B', $match->element?->getKey());
    }

    public function testAKeyTakenByAnObjectThatDoesNotMatchIsAnErrorRowNamingIt(): void
    {
        $taken = $this->createObject(self::TARGET, 'other value', 'SKU-1');

        $match = $this->matchOne($this->byField(), 'SKU-1');

        $this->assertSame(RowMatchOutcome::KeyTaken, $match->outcome);
        $this->assertSame([$taken->getId()], $match->objectIds);
        $this->assertNull($match->element);
    }

    public function testAKeyAnEarlierNewObjectClaimedIsAnErrorRowNamingTheRow(): void
    {
        $matcher = $this->matcher($this->byField());

        $first = $matcher->match($this->row('A/B'), 2);
        $second = $matcher->match($this->row('A-B'), 7);

        $this->assertSame(RowMatchOutcome::New, $first->outcome);
        $this->assertSame(RowMatchOutcome::KeyTaken, $second->outcome);
        $this->assertSame(2, $second->firstRow);
    }

    public static function emptyCells(): array
    {
        return [
            'empty' => [''],
            'blanks' => ['   '],
            'null' => [null],
            'only characters a key cannot hold' => ["\u{200B}"],
        ];
    }

    /**
     * @dataProvider emptyCells
     */
    public function testAnEmptyMatchValueIsAnErrorRow(?string $cell): void
    {
        $this->createObject(self::TARGET, null);

        $match = $this->matcher($this->byField())->match([self::COLUMN => $cell], 2);

        $this->assertSame(RowMatchOutcome::EmptyValue, $match->outcome);
        $this->assertNull($match->element);
    }

    public function testAMissingColumnIsAnErrorRow(): void
    {
        $match = $this->matcher($this->byField())->match(['another column' => 'SKU-1'], 2);

        $this->assertSame(RowMatchOutcome::EmptyValue, $match->outcome);
    }

    public function testAValueTwiceInTheFileMakesTheSecondRowAnErrorRow(): void
    {
        $object = $this->createObject(self::TARGET, 'SKU-1');
        $matcher = $this->matcher($this->byField());

        $first = $matcher->match($this->row('SKU-1'), 2);
        $second = $matcher->match($this->row(' SKU-1 '), 5);
        $third = $matcher->match($this->row('SKU-1'), 9);

        $this->assertSame($object->getId(), $first->element?->getId());
        $this->assertSame(RowMatchOutcome::DuplicateInFile, $second->outcome);
        $this->assertSame('SKU-1', $second->matchValue);
        $this->assertSame(2, $second->firstRow);
        $this->assertNull($second->element);
        $this->assertSame(2, $third->firstRow);
    }

    public function testAValueTwiceInTheFileCountsForNewObjectsAndAfterAnErrorRow(): void
    {
        $this->createObject(self::ELSEWHERE, 'SKU-2');
        $matcher = $this->matcher($this->byField());

        $matcher->match($this->row('SKU-1'), 2);
        $matcher->match($this->row('SKU-2'), 3);

        $this->assertSame(2, $matcher->match($this->row('SKU-1'), 4)->firstRow);
        $this->assertSame(3, $matcher->match($this->row('SKU-2'), 5)->firstRow);
    }

    public function testValuesTheFieldTreatsAsEqualAreOneValueWhenNoObjectHasThemYet(): void
    {
        $matcher = $this->matcher($this->byField());

        $this->assertSame(RowMatchOutcome::New, $matcher->match($this->row('abc'), 2)->outcome);
        $this->assertSame(RowMatchOutcome::New, $matcher->match($this->row('Muller'), 3)->outcome);

        $sameCase = $matcher->match($this->row('ABC'), 4);
        $sameAccent = $matcher->match($this->row('Müller'), 5);

        $this->assertSame(RowMatchOutcome::DuplicateInFile, $sameCase->outcome);
        $this->assertSame(2, $sameCase->firstRow);
        $this->assertSame('ABC', $sameCase->matchValue);
        $this->assertSame(RowMatchOutcome::DuplicateInFile, $sameAccent->outcome);
        $this->assertSame(3, $sameAccent->firstRow);
    }

    public function testTheDatabaseFindsWhatTheMatcherTreatsAsOneValue(): void
    {
        $object = $this->createObject(self::TARGET, 'Müller');

        $this->assertSame($object->getId(), $this->matchOne($this->byField(), 'MULLER')->element?->getId());
    }

    public function testTwoValuesForOneObjectMakeTheSecondRowAnErrorRow(): void
    {
        $object = $this->createObject(self::TARGET, 'SKU-1');
        $matcher = $this->matcher($this->byObjectId());

        $first = $matcher->match($this->row((string) $object->getId()), 2);
        $second = $matcher->match($this->row('+' . $object->getId()), 3);

        $this->assertSame(RowMatchOutcome::Existing, $first->outcome);
        $this->assertSame(RowMatchOutcome::DuplicateInFile, $second->outcome);
        $this->assertSame(2, $second->firstRow);
        $this->assertSame([$object->getId()], $second->objectIds);
    }

    public function testALocalizedFieldIsMatchedInItsLanguage(): void
    {
        $object = $this->createObject(self::TARGET, 'SKU-1', null, true, self::CLASS_NAME, 'Title-1');
        $this->createObject(self::ELSEWHERE, 'SKU-2', null, true, self::CLASS_NAME, 'Title-2');
        $matcher = $this->matcher($this->configuration(attributeName: 'title', language: 'en'));

        $this->assertSame($object->getId(), $matcher->match($this->row('Title-1'), 2)->element?->getId());
        $this->assertSame(RowMatchOutcome::OutsideTargetFolder, $matcher->match($this->row('Title-2'), 3)->outcome);
    }

    public function testABrickAttributeIsMatched(): void
    {
        $object = $this->createObject(self::TARGET, 'SKU-1', null, true, self::CLASS_NAME, null, 'Code-1');
        $this->createObject(self::ELSEWHERE, 'SKU-2', null, true, self::CLASS_NAME, null, 'Code-2');
        $attributeName = 'bricks.' . self::BRICK_NAME . self::BRICK_ATTRIBUTE;
        $matcher = $this->matcher($this->configuration(attributeName: $attributeName));

        $this->assertSame($object->getId(), $matcher->match($this->row('Code-1'), 2)->element?->getId());
        $this->assertSame(RowMatchOutcome::OutsideTargetFolder, $matcher->match($this->row('Code-2'), 3)->outcome);
    }

    public function testATargetFolderWithATrailingSlashIsTheFolder(): void
    {
        $object = $this->createObject(self::TARGET, 'SKU-1');
        $configuration = $this->configuration(targetFolder: self::TARGET . '/', attributeName: 'sku');

        $this->assertSame($object->getId(), $this->matchOne($configuration, 'SKU-1')->element?->getId());
    }

    public function testAnEmptyCellClearsTheFieldOfAMatchedObject(): void
    {
        // one save only: updating an object reaches the search index, which the test environment does not have
        $object = $this->unsavedObject(self::TARGET, 'SKU-1');
        $object->setName('old name');
        $object->save();

        /** @var MappingApplier $applier */
        $applier = $this->tester->grabService(MappingApplier::class);
        $mapping = $applier->prepare([[
            'label' => 'name',
            'dataSourceIndex' => ['name'],
            'transformationPipeline' => [],
            'dataTarget' => ['type' => 'direct', 'settings' => ['fieldName' => 'name']],
        ]]);

        $row = [self::COLUMN => 'SKU-1', 'name' => ''];
        $match = $this->matcher($this->byField())->match($row, 2);
        $this->assertSame('old name', $match->element?->getName());

        $mapping->apply($match->element, $row);

        $this->assertEmpty($match->element->getName());
    }

    public static function unusableConfigurations(): array
    {
        return [
            'unknown class' => [['classId' => 'no-such-class']],
            'unknown field' => [['attributeName' => 'nope']],
            'field name with SQL' => [['attributeName' => "sku' OR 1=1 --"]],
            'relation field' => [['attributeName' => 'related']],
            'field over several columns' => [['attributeName' => 'weight']],
            'numeric field, where 12abc would compare as 12' => [['attributeName' => 'articleNo']],
            'checkbox field, where n/a would compare as 0' => [['attributeName' => 'active']],
            'date field' => [['attributeName' => 'releaseDate']],
            'localized field without a language' => [['attributeName' => 'title']],
            'invalid language' => [['attributeName' => 'title', 'language' => 'en_xx-nope']],
            'brick the field does not allow' => [
                ['attributeName' => 'bricks.' . self::OTHER_BRICK_NAME . self::BRICK_ATTRIBUTE],
            ],
            'unknown brick attribute' => [['attributeName' => 'bricks.' . self::BRICK_NAME . '.nope']],
            'not a brick container' => [['attributeName' => 'sku.' . self::BRICK_NAME . self::BRICK_ATTRIBUTE]],
            'missing target folder' => [['targetFolder' => self::MISSING]],
            'missing folder for new objects' => [['newObjectFolder' => self::MISSING]],
        ];
    }

    /**
     * @dataProvider unusableConfigurations
     *
     * @param array<string, string> $settings
     */
    public function testAConfigurationThatCannotBeUsedIsRefusedAndCreatesNoFolder(array $settings): void
    {
        try {
            $this->matcher($this->configuration(...$settings));
            $this->fail('The configuration was accepted.');
        } catch (InvalidConfigurationException) {
            $this->assertNull(DataObject::getByPath(self::MISSING));
        }
    }

    public function testTheLookupRefusesAnAttributeNameThatIsNoFieldName(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new DataObjectLoader())->findIdsByAttribute(
            self::CLASS_NAMESPACE . self::CLASS_NAME,
            'sku = 1 OR 1=1 --',
            'SKU-1',
            '',
            '',
            1
        );
    }

    public function testTheLookupStopsAtTheLimit(): void
    {
        $this->createObject(self::TARGET, 'SKU-1');
        $this->createObject(self::TARGET, 'SKU-1');
        $this->createObject(self::TARGET, 'SKU-1');

        $ids = (new DataObjectLoader())->findIdsByAttribute(
            self::CLASS_NAMESPACE . self::CLASS_NAME,
            'sku',
            'SKU-1',
            '',
            self::TARGET . '/',
            2
        );

        $this->assertCount(2, $ids);
    }

    private function matchOne(MatchingConfiguration $configuration, string $value): RowMatch
    {
        return $this->matcher($configuration)->match($this->row($value), 2);
    }

    private function matcher(MatchingConfiguration $configuration): RowMatcher
    {
        /** @var RowMatcherFactory $factory */
        $factory = $this->tester->grabService(RowMatcherFactory::class);

        return $factory->create($configuration);
    }

    private function byField(): MatchingConfiguration
    {
        return $this->configuration(attributeName: 'sku');
    }

    private function byObjectId(): MatchingConfiguration
    {
        return $this->configuration();
    }

    private function configuration(
        ?string $classId = null,
        string $targetFolder = self::TARGET,
        ?string $attributeName = null,
        string $language = '',
        ?string $newObjectFolder = null
    ): MatchingConfiguration {
        return new MatchingConfiguration(
            $classId ?? $this->class->getId(),
            self::COLUMN,
            $targetFolder,
            $attributeName,
            $language,
            $newObjectFolder
        );
    }

    /**
     * @return array<string, string>
     */
    private function row(string $value): array
    {
        return [self::COLUMN => $value];
    }

    private function createObject(
        string $folder,
        ?string $sku,
        ?string $key = null,
        bool $published = true,
        string $className = self::CLASS_NAME,
        ?string $title = null,
        ?string $brickCode = null
    ): Concrete {
        $object = $this->unsavedObject($folder, $sku, $key, $published, $className);
        if ($title !== null) {
            $object->setTitle($title, 'en');
        }
        if ($brickCode !== null) {
            $brickClass = self::CLASS_NAMESPACE . 'Objectbrick\\Data\\' . self::BRICK_NAME;
            $brick = new $brickClass($object);
            $brick->setCode($brickCode);
            $object->getBricks()->setDiRowMatcherBrick($brick);
        }
        $object->save();

        return $object;
    }

    private function unsavedObject(
        string $folder,
        ?string $sku,
        ?string $key = null,
        bool $published = true,
        string $className = self::CLASS_NAME
    ): Concrete {
        ClassDefinition::getByName($className) ?? $this->createClassNamed($className);

        $fqcn = self::CLASS_NAMESPACE . $className;

        /** @var Concrete $object */
        $object = new $fqcn();
        $object->setParent(Service::createFolderByPath($folder));
        $object->setKey($key ?? uniqid('o', true));
        $object->setPublished($published);
        $object->setSku($sku);

        return $object;
    }

    private function createClass(): ClassDefinition
    {
        $definition = $this->createClassNamed(self::CLASS_NAME);
        $this->createBrick(self::BRICK_NAME, [['classname' => self::CLASS_NAME, 'fieldname' => 'bricks']]);
        $this->createBrick(self::OTHER_BRICK_NAME, []);

        return $definition;
    }

    private function createClassNamed(string $name): ClassDefinition
    {
        $panel = new ClassDefinition\Layout\Panel();
        $panel->setName('pimcore_root');

        foreach (['sku', 'name'] as $field) {
            $input = new ClassDefinition\Data\Input();
            $input->setName($field);
            $input->setTitle($field);
            $panel->addChild($input);
        }

        if ($name === self::CLASS_NAME) {
            $title = new ClassDefinition\Data\Input();
            $title->setName('title');
            $title->setTitle('title');

            $localizedFields = new ClassDefinition\Data\Localizedfields();
            $localizedFields->setName('localizedfields');
            $localizedFields->addChild($title);
            $panel->addChild($localizedFields);

            $bricks = new ClassDefinition\Data\Objectbricks();
            $bricks->setName('bricks');
            $bricks->setAllowedTypes([self::BRICK_NAME]);
            $panel->addChild($bricks);

            $related = new ClassDefinition\Data\ManyToOneRelation();
            $related->setName('related');
            $related->setTitle('related');
            $related->setObjectsAllowed(true);
            $panel->addChild($related);

            $weight = new ClassDefinition\Data\QuantityValue();
            $weight->setName('weight');
            $weight->setTitle('weight');
            $panel->addChild($weight);

            $articleNo = new ClassDefinition\Data\Numeric();
            $articleNo->setName('articleNo');
            $articleNo->setTitle('articleNo');
            $panel->addChild($articleNo);

            $active = new ClassDefinition\Data\Checkbox();
            $active->setName('active');
            $active->setTitle('active');
            $panel->addChild($active);

            $releaseDate = new ClassDefinition\Data\Date();
            $releaseDate->setName('releaseDate');
            $releaseDate->setTitle('releaseDate');
            $panel->addChild($releaseDate);
        }

        $definition = new ClassDefinition();
        $definition->setName($name);
        $definition->setLayoutDefinitions($panel);
        $definition->save();

        return $definition;
    }

    /**
     * @param list<array{classname: string, fieldname: string}> $classDefinitions
     */
    private function createBrick(string $name, array $classDefinitions): void
    {
        if (Objectbrick\Definition::getByKey($name) instanceof Objectbrick\Definition) {
            return;
        }

        $code = new ClassDefinition\Data\Input();
        $code->setName('code');
        $code->setTitle('code');

        $panel = new ClassDefinition\Layout\Panel();
        $panel->setName('pimcore_root');
        $panel->addChild($code);

        $definition = new Objectbrick\Definition();
        $definition->setKey($name);
        $definition->setLayoutDefinitions($panel);
        $definition->setClassDefinitions($classDefinitions);
        $definition->save();
    }
}
