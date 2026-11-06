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
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\MatchingConfiguration;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatch;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatcher;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatcherFactory;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatchOutcome;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\RowMatcherModel;
use Pimcore\Bundle\DataImporterBundle\Tool\DataObjectLoader;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Service;
use Pimcore\Tests\Support\Util\TestHelper;

class RowMatcherTest extends Unit
{
    private const CLASS_NAME = RowMatcherModel::CLASS_NAME;

    private const OTHER_CLASS_NAME = 'DiRowMatcherOtherClass';

    private const BRICK_NAME = RowMatcherModel::BRICK_NAME;

    private const OTHER_BRICK_NAME = RowMatcherModel::OTHER_BRICK_NAME;

    private const LOCALIZED_BRICK_NAME = RowMatcherModel::LOCALIZED_BRICK_NAME;

    private const INHERITING_CLASS_NAME = RowMatcherModel::INHERITING_CLASS_NAME;

    private const CLASS_NAMESPACE = '\\Pimcore\\Model\\DataObject\\';

    private const BRICK_DATA_NAMESPACE = self::CLASS_NAMESPACE . 'Objectbrick\\Data\\';

    private const OTHER_VALUE = 'other value';

    private const SAME_TITLE = 'Same title';

    private const NOT_A_NUMBER = '12abc';

    private const SIGNED_NUMBER = '+12.50';

    private const ROOT = '/di-row-matcher';

    private const TARGET = self::ROOT . '/target';

    private const COLUMN = 'match';

    private const NEW_FOLDER = self::ROOT . '/new-objects';

    private const ELSEWHERE = self::ROOT . '/elsewhere';

    private const MISSING = self::ROOT . '/missing';

    private const BRICK_ATTRIBUTE = '.code';

    private const BRICK_NUMBER = '.number';

    /**
     * @var \Pimcore\Bundle\DataImporterBundle\Tests\UnitTester
     */
    protected $tester;

    private ClassDefinition $class;

    protected function _before(): void
    {
        $this->class = ClassDefinition::getByName(self::CLASS_NAME) ?? RowMatcherModel::createClass();
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

    public function testSeveralMatchesInTheTargetReportAtMostFiveObjects(): void
    {
        $this->createSameValueObjects(self::TARGET, 6);

        $match = $this->matchOne($this->byField(), 'SKU-1');

        $this->assertSame(RowMatchOutcome::SeveralMatches, $match->outcome);
        $this->assertCount(5, $match->objectIds);
    }

    public function testMatchesInTheTargetAndTheFolderForNewObjectsReportAtMostFiveObjects(): void
    {
        $inTarget = $this->createSameValueObjects(self::TARGET, 5);
        $this->createSameValueObjects(self::NEW_FOLDER, 5);
        $configuration = $this->configuration(attributeName: 'sku', newObjectFolder: self::NEW_FOLDER);

        $match = $this->matchOne($configuration, 'SKU-1');

        $this->assertSame(RowMatchOutcome::SeveralMatches, $match->outcome);
        $this->assertEqualsCanonicalizing($inTarget, $match->objectIds);
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

    public static function foldersAboveTheTarget(): array
    {
        return [
            'an ancestor of the target folder' => [self::ROOT],
            'the tree root' => ['/'],
        ];
    }

    /**
     * @dataProvider foldersAboveTheTarget
     */
    public function testAFolderForNewObjectsAboveTheTargetDoesNotWidenTheSearch(string $newObjectFolder): void
    {
        $outside = $this->createObject(self::ELSEWHERE, 'SKU-1');
        $byField = $this->configuration(attributeName: 'sku', newObjectFolder: $newObjectFolder);
        $byId = $this->configuration(newObjectFolder: $newObjectFolder);

        $this->assertSame(RowMatchOutcome::OutsideTargetFolder, $this->matchOne($byField, 'SKU-1')->outcome);
        $this->assertSame(
            RowMatchOutcome::OutsideTargetFolder,
            $this->matchOne($byId, (string) $outside->getId())->outcome
        );
    }

    public function testAnObjectDirectlyInAFolderForNewObjectsAboveTheTargetIsFound(): void
    {
        $made = $this->createObject(self::ROOT, 'SKU-1');
        $byField = $this->configuration(attributeName: 'sku', newObjectFolder: self::ROOT);
        $byId = $this->configuration(newObjectFolder: self::ROOT);

        $this->assertSame($made->getId(), $this->matchOne($byField, 'SKU-1')->element?->getId());
        $this->assertSame($made->getId(), $this->matchOne($byId, (string) $made->getId())->element?->getId());
    }

    public function testAnObjectBelowTheFolderForNewObjectsStaysOutside(): void
    {
        Service::createFolderByPath(self::NEW_FOLDER);
        $below = $this->createObject(self::NEW_FOLDER . '/sub', 'SKU-1');
        $byField = $this->configuration(attributeName: 'sku', newObjectFolder: self::NEW_FOLDER);
        $byId = $this->configuration(newObjectFolder: self::NEW_FOLDER);

        $this->assertSame(RowMatchOutcome::OutsideTargetFolder, $this->matchOne($byField, 'SKU-1')->outcome);
        $this->assertSame(
            RowMatchOutcome::OutsideTargetFolder,
            $this->matchOne($byId, (string) $below->getId())->outcome
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
        $taken = $this->createObject(self::TARGET, self::OTHER_VALUE, 'SKU-1');

        $match = $this->matchOne($this->byField(), 'SKU-1');

        $this->assertSame(RowMatchOutcome::KeyTaken, $match->outcome);
        $this->assertSame([$taken->getId()], $match->objectIds);
        $this->assertNull($match->element);
    }

    public function testAKeyIsCheckedInTheFolderForNewObjectsAndNotInTheTarget(): void
    {
        $taken = $this->createObject(self::NEW_FOLDER, self::OTHER_VALUE, 'SKU-1');
        $this->createObject(self::TARGET, self::OTHER_VALUE, 'SKU-2');
        $configuration = $this->configuration(attributeName: 'sku', newObjectFolder: self::NEW_FOLDER);

        $takenThere = $this->matchOne($configuration, 'SKU-1');
        $takenInTarget = $this->matchOne($configuration, 'SKU-2');

        $this->assertSame(RowMatchOutcome::KeyTaken, $takenThere->outcome);
        $this->assertSame([$taken->getId()], $takenThere->objectIds);
        $this->assertSame(RowMatchOutcome::New, $takenInTarget->outcome);
        $this->assertSame(DataObject::getByPath(self::NEW_FOLDER)?->getId(), $takenInTarget->element?->getParentId());
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

    public function testALocalizedFieldIsMatchedOnlyInTheNamedLanguage(): void
    {
        $english = $this->unsavedObject(self::TARGET, 'SKU-1');
        $english->setTitle(self::SAME_TITLE, 'en');
        $english->save();
        $german = $this->unsavedObject(self::TARGET, 'SKU-2');
        $german->setTitle(self::SAME_TITLE, 'de');
        $german->save();

        $inGerman = $this->matchOne($this->configuration(attributeName: 'title', language: 'de'), self::SAME_TITLE);
        $inEnglish = $this->matchOne($this->configuration(attributeName: 'title', language: 'en'), self::SAME_TITLE);

        $this->assertSame($german->getId(), $inGerman->element?->getId());
        $this->assertSame($english->getId(), $inEnglish->element?->getId());
    }

    public function testABrickWhoseNameIsAReservedWordIsMatched(): void
    {
        $object = $this->unsavedObject(self::TARGET, 'SKU-1');
        $brickClass = self::BRICK_DATA_NAMESPACE . RowMatcherModel::RESERVED_WORD_BRICK_NAME;
        $brick = new $brickClass($object);
        $brick->setCode('Code-1');
        $object->getBricks()->setOrder($brick);
        $object->save();
        $attributeName = 'bricks.' . RowMatcherModel::RESERVED_WORD_BRICK_NAME . self::BRICK_ATTRIBUTE;

        $match = $this->matchOne($this->configuration(attributeName: $attributeName), 'Code-1');

        $this->assertSame(RowMatchOutcome::Existing, $match->outcome);
        $this->assertSame($object->getId(), $match->element?->getId());
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

    public function testABrickAttributeOnlyMatchesInTheNamedField(): void
    {
        $object = $this->unsavedObject(self::TARGET, 'SKU-1');
        $brickClass = self::BRICK_DATA_NAMESPACE . self::BRICK_NAME;
        $brick = new $brickClass($object);
        $brick->setCode('Code-1');
        $object->getOtherBricks()->setDiRowMatcherBrick($brick);
        $object->save();
        $named = 'otherBricks.' . self::BRICK_NAME . self::BRICK_ATTRIBUTE;
        $other = 'bricks.' . self::BRICK_NAME . self::BRICK_ATTRIBUTE;

        $this->assertSame(
            $object->getId(),
            $this->matchOne($this->configuration(attributeName: $named), 'Code-1')->element?->getId()
        );
        $this->assertSame(
            RowMatchOutcome::New,
            $this->matchOne($this->configuration(attributeName: $other), 'Code-1')->outcome
        );
    }

    public static function fieldsThatKeepTheTextAsItIs(): array
    {
        return [
            'textarea' => ['notes', 'setNotes'],
            'select' => ['state', 'setState'],
        ];
    }

    /**
     * @dataProvider fieldsThatKeepTheTextAsItIs
     */
    public function testAFieldThatKeepsTheTextAsItIsIsMatched(string $field, string $setter): void
    {
        $object = $this->unsavedObject(self::TARGET, 'SKU-1');
        $object->$setter('red');
        $object->save();

        $match = $this->matchOne($this->configuration(attributeName: $field), 'red');

        $this->assertSame($object->getId(), $match->element?->getId());
    }

    public static function valuesANumericFieldCannotHold(): array
    {
        return [
            'text after the digits' => [self::NOT_A_NUMBER],
            'no number' => ['n/a'],
            'an exponent' => ['1e1'],
            'a comma' => ['12,5'],
            'a leading zero' => ['012'],
        ];
    }

    /**
     * @dataProvider valuesANumericFieldCannotHold
     */
    public function testAValueANumericFieldCannotHoldIsAnErrorRowAndNeitherMatchNorNewObject(string $value): void
    {
        $this->createObjectWith(self::TARGET, ['setArticleNo' => 12]);
        $this->createObjectWith(self::TARGET, ['setArticleNo' => 0]);

        $match = $this->matchOne($this->configuration(attributeName: 'articleNo'), $value);

        $this->assertSame(RowMatchOutcome::InvalidValue, $match->outcome);
        $this->assertSame($value, $match->matchValue);
        $this->assertNull($match->element);
        $this->assertTrue($match->isError());
    }

    public function testANumericFieldFindsTheNumberInAnySpellingAndReportsTheFilesOne(): void
    {
        $object = $this->createObjectWith(self::TARGET, ['setArticleNo' => 12.5]);

        $match = $this->matchOne($this->configuration(attributeName: 'articleNo'), self::SIGNED_NUMBER);

        $this->assertSame(RowMatchOutcome::Existing, $match->outcome);
        $this->assertSame($object->getId(), $match->element?->getId());
        $this->assertSame(self::SIGNED_NUMBER, $match->matchValue);
    }

    public function testANumericFieldTellsZeroFromNoValue(): void
    {
        $this->createObjectWith(self::TARGET, []);
        $zero = $this->createObjectWith(self::TARGET, ['setArticleNo' => 0]);

        $match = $this->matchOne($this->configuration(attributeName: 'articleNo'), '0');

        $this->assertSame($zero->getId(), $match->element?->getId());
    }

    public function testANumericMatchOnlyOutsideTheTargetFolderIsAnErrorRow(): void
    {
        $outside = $this->createObjectWith(self::ELSEWHERE, ['setArticleNo' => 12]);

        $match = $this->matchOne($this->configuration(attributeName: 'articleNo'), '12');

        $this->assertSame(RowMatchOutcome::OutsideTargetFolder, $match->outcome);
        $this->assertSame([$outside->getId()], $match->objectIds);
    }

    public function testAnIntegerFieldTellsBigNumbersADoubleCannotTellApart(): void
    {
        $object = $this->createObjectWith(self::TARGET, ['setStock' => 9007199254740993]);
        $this->createObjectWith(self::TARGET, ['setStock' => 9007199254740992]);

        $match = $this->matchOne($this->configuration(attributeName: 'stock'), '9007199254740993');

        $this->assertSame(RowMatchOutcome::Existing, $match->outcome);
        $this->assertSame($object->getId(), $match->element?->getId());
    }

    public function testADecimalFieldIsMatchedByTheNumber(): void
    {
        $object = $this->createObjectWith(self::TARGET, ['setPrice' => '19.90']);

        $match = $this->matchOne($this->configuration(attributeName: 'price'), '19.9');

        $this->assertSame($object->getId(), $match->element?->getId());
    }

    public function testADecimalFieldRefusesMoreDecimalsThanItHolds(): void
    {
        $this->createObjectWith(self::TARGET, ['setPrice' => '19.90']);

        $match = $this->matchOne($this->configuration(attributeName: 'price'), '19.901');

        $this->assertSame(RowMatchOutcome::InvalidValue, $match->outcome);
    }

    public function testANumberTwiceInTheFileInTwoSpellingsMakesTheSecondRowAnErrorRow(): void
    {
        $matcher = $this->matcher($this->configuration(attributeName: 'articleNo'));

        $first = $matcher->match($this->row('12.5'), 2);
        $second = $matcher->match($this->row('12.50'), 3);
        $otherSign = $matcher->match($this->row('-12.5'), 4);

        $this->assertSame(RowMatchOutcome::New, $first->outcome);
        $this->assertSame(RowMatchOutcome::DuplicateInFile, $second->outcome);
        $this->assertSame(2, $second->firstRow);
        $this->assertSame('12.50', $second->matchValue);
        $this->assertSame(RowMatchOutcome::New, $otherSign->outcome);
    }

    public function testANewObjectIsKeyedByTheNumberAndNotItsSpelling(): void
    {
        $match = $this->matchOne($this->configuration(attributeName: 'articleNo'), self::SIGNED_NUMBER);

        $this->assertSame(RowMatchOutcome::New, $match->outcome);
        $this->assertSame('12.5', $match->element?->getKey());
        $this->assertSame(self::SIGNED_NUMBER, $match->matchValue);
    }

    public function testANumericBrickAttributeIsMatched(): void
    {
        $object = $this->unsavedObject(self::TARGET, 'SKU-1');
        $brickClass = self::BRICK_DATA_NAMESPACE . self::BRICK_NAME;
        $brick = new $brickClass($object);
        $brick->setNumber(12);
        $object->getBricks()->setDiRowMatcherBrick($brick);
        $object->save();
        $configuration = $this->configuration(attributeName: 'bricks.' . self::BRICK_NAME . self::BRICK_NUMBER);

        $this->assertSame($object->getId(), $this->matchOne($configuration, '12')->element?->getId());
        $this->assertSame(RowMatchOutcome::InvalidValue, $this->matchOne($configuration, self::NOT_A_NUMBER)->outcome);
    }

    public function testALocalizedNumericFieldIsMatchedInItsLanguage(): void
    {
        $object = $this->unsavedObject(self::TARGET, 'SKU-1');
        $object->setLocalizedNumber(12, 'en');
        $object->save();
        $configuration = $this->configuration(attributeName: 'localizedNumber', language: 'en');

        $this->assertSame($object->getId(), $this->matchOne($configuration, '12')->element?->getId());
        $this->assertSame(RowMatchOutcome::InvalidValue, $this->matchOne($configuration, self::NOT_A_NUMBER)->outcome);
    }

    public function testAVariantIsFoundByItsOwnValueAndItsObjectId(): void
    {
        $parent = $this->createObject(self::TARGET, 'PARENT', null, true, self::INHERITING_CLASS_NAME);
        $variant = $this->createVariant($parent, 'VARIANT');
        $classId = ClassDefinition::getByName(self::INHERITING_CLASS_NAME)->getId();

        $byField = $this->matchOne($this->configuration(classId: $classId, attributeName: 'sku'), 'VARIANT');
        $byId = $this->matchOne($this->configuration(classId: $classId), (string) $variant->getId());

        $this->assertSame(RowMatchOutcome::Existing, $byField->outcome);
        $this->assertSame($variant->getId(), $byField->element?->getId());
        $this->assertSame(RowMatchOutcome::Existing, $byId->outcome);
        $this->assertSame($variant->getId(), $byId->element?->getId());
    }

    public function testAParentAndAVariantInheritingItsValueAreSeveralMatches(): void
    {
        $parent = $this->createObject(self::TARGET, 'SKU-1', null, true, self::INHERITING_CLASS_NAME);
        $variant = $this->createVariant($parent, null);
        $classId = ClassDefinition::getByName(self::INHERITING_CLASS_NAME)->getId();

        $match = $this->matchOne($this->configuration(classId: $classId, attributeName: 'sku'), 'SKU-1');

        $this->assertSame(RowMatchOutcome::SeveralMatches, $match->outcome);
        $this->assertEqualsCanonicalizing([$parent->getId(), $variant->getId()], $match->objectIds);
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
            'many-to-many object relation, whose column wraps the ids in commas' => [
                ['attributeName' => 'relatedMany'],
            ],
            'many-to-many relation' => [['attributeName' => 'relatedElements']],
            'multiselect field, whose column wraps the values in commas' => [['attributeName' => 'colors']],
            'password field, whose column holds a hash' => [['attributeName' => 'secret']],
            'wysiwyg field, whose column holds markup' => [['attributeName' => 'description']],
            'slider field, where 12abc would compare as 12' => [['attributeName' => 'rating']],
            'link field, whose column holds a serialized value' => [['attributeName' => 'website']],
            'field over several columns' => [['attributeName' => 'weight']],
            'checkbox field, where n/a would compare as 0' => [['attributeName' => 'active']],
            'date field' => [['attributeName' => 'releaseDate']],
            'localized field without a language' => [['attributeName' => 'title']],
            'invalid language' => [['attributeName' => 'title', 'language' => 'en_xx-nope']],
            'brick the field does not allow' => [
                ['attributeName' => 'bricks.' . self::OTHER_BRICK_NAME . self::BRICK_ATTRIBUTE],
            ],
            'localized brick attribute, whose column is in a table the lookup does not join' => [
                ['attributeName' => 'bricks.' . self::LOCALIZED_BRICK_NAME . self::BRICK_ATTRIBUTE, 'language' => 'en'],
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

    public function testAnAttributeListFilteredByTheRuleOffersOnlyWhatAMatcherAccepts(): void
    {
        /** @var RowMatcherFactory $factory */
        $factory = $this->tester->grabService(RowMatcherFactory::class);
        $attributes = (new TransformationDataTypeService())->getPimcoreDataTypes(
            $this->class->getId(),
            [TransformationDataTypeService::DEFAULT_TYPE, TransformationDataTypeService::NUMERIC],
            false,
            false,
            false
        );

        $matchable = [];
        $refused = [];
        foreach ($attributes as $attribute) {
            $configuration = $this->configuration(
                attributeName: $attribute['key'],
                language: $attribute['localized'] ? 'en' : ''
            );
            if ($factory->isMatchableField($this->class, $attribute['key'])) {
                $factory->create($configuration);
                $matchable[] = $attribute['key'];

                continue;
            }

            try {
                $factory->create($configuration);
                $this->fail("`{$attribute['key']}` is not offered, but a matcher accepts it.");
            } catch (InvalidConfigurationException) {
                $refused[] = $attribute['key'];
            }
        }

        $this->assertEqualsCanonicalizing(
            [
                'sku',
                'name',
                'title',
                'notes',
                'state',
                'articleNo',
                'stock',
                'price',
                'localizedNumber',
                'bricks.' . self::BRICK_NAME . self::BRICK_ATTRIBUTE,
                'bricks.' . self::BRICK_NAME . self::BRICK_NUMBER,
                'bricks.' . RowMatcherModel::RESERVED_WORD_BRICK_NAME . self::BRICK_ATTRIBUTE,
                'bricks.' . RowMatcherModel::RESERVED_WORD_BRICK_NAME . self::BRICK_NUMBER,
                'otherBricks.' . self::BRICK_NAME . self::BRICK_ATTRIBUTE,
                'otherBricks.' . self::BRICK_NAME . self::BRICK_NUMBER,
            ],
            $matchable
        );
        $this->assertEqualsCanonicalizing(
            [
                'secret',
                'description',
                'rating',
                'bricks.' . self::LOCALIZED_BRICK_NAME . self::BRICK_ATTRIBUTE,
            ],
            $refused
        );
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

    public function testTheNumberLookupRefusesWhatIsNoNumber(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new DataObjectLoader())->findIdsByNumber(
            self::CLASS_NAMESPACE . self::CLASS_NAME,
            'articleNo',
            '1 OR 1=1',
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
            $brickClass = self::BRICK_DATA_NAMESPACE . self::BRICK_NAME;
            $brick = new $brickClass($object);
            $brick->setCode($brickCode);
            $object->getBricks()->setDiRowMatcherBrick($brick);
        }
        $object->save();

        return $object;
    }

    /**
     * @param array<string, int|float|string> $values the setters to call and their values
     */
    private function createObjectWith(string $folder, array $values): Concrete
    {
        $object = $this->unsavedObject($folder, null);
        foreach ($values as $setter => $value) {
            $object->$setter($value);
        }
        $object->save();

        return $object;
    }

    /**
     * @return list<int>
     */
    private function createSameValueObjects(string $folder, int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = (int) $this->createObject($folder, 'SKU-1')->getId();
        }

        return $ids;
    }

    private function unsavedObject(
        string $folder,
        ?string $sku,
        ?string $key = null,
        bool $published = true,
        string $className = self::CLASS_NAME
    ): Concrete {
        ClassDefinition::getByName($className) ?? RowMatcherModel::createClassNamed($className);

        $fqcn = self::CLASS_NAMESPACE . $className;

        /** @var Concrete $object */
        $object = new $fqcn();
        $object->setParent(Service::createFolderByPath($folder));
        $object->setKey($key ?? uniqid('o', true));
        $object->setPublished($published);
        $object->setSku($sku);

        return $object;
    }

    private function createVariant(Concrete $parent, ?string $sku): Concrete
    {
        $fqcn = self::CLASS_NAMESPACE . self::INHERITING_CLASS_NAME;

        /** @var Concrete $variant */
        $variant = new $fqcn();
        $variant->setParent($parent);
        $variant->setKey(uniqid('v', true));
        $variant->setPublished(true);
        $variant->setType(DataObject::OBJECT_TYPE_VARIANT);
        $variant->setSku($sku);
        $variant->save();

        return $variant;
    }
}
