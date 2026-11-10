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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\ClassificationStore;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfiguration;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\ClassificationStoreFixture;
use Pimcore\Element\MarshallerService;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\EncryptedField;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\Classificationstore as ClassificationstoreModel;
use Pimcore\Model\DataObject\Classificationstore\KeyTypeSupport;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Normalizer\NormalizerInterface;
use Pimcore\Tests\Support\Util\TestHelper;
use Pimcore\Tool;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * The importer-side round trip of one classification store key value, over every key type a key can
 * currently hold, both classification store data targets and a localized as well as a
 * non-localized classification store field.
 *
 * The verdict table is written to `tests/_output` and summarised in the audit document committed
 * next to this test, which also states what this is not evidence of.
 */
class ClassificationStoreRoundTripAuditTest extends Unit
{
    private const STORE = 'diAuditStore';

    private const GROUP = 'diAuditGroup';

    private const CLASS_NAME = 'DiCsAuditClass';

    private const KEY_PREFIX = 'diAudit_';

    private const TARGET_SINGLE_KEY = 'classificationstore';

    private const TARGET_BATCH = 'classificationstoreBatch';

    private const VERDICT_SURVIVED = 'survived';

    private const VERDICT_DIFFERED = 'differed';

    private const VERDICT_UNREACHABLE = 'not reachable';

    private const MARSHALLER_FORMAT = 'classificationstore';

    private const FIELD_VARIANTS = [
        ClassificationStoreFixture::FIELD_NON_LOCALIZED,
        ClassificationStoreFixture::FIELD_LOCALIZED,
    ];

    private const TARGETS = [self::TARGET_SINGLE_KEY, self::TARGET_BATCH];

    /**
     * The headings the audit document has to carry. Content quality is a review matter; that these
     * sections exist at all is not.
     */
    private const REQUIRED_SECTIONS = [
        '## What this is evidence of, and what it is not',
        '## Key types under audit',
        '## Verdict table',
        '## What counts as empty',
        '## Divergence from Direct',
        '## Two entries that look alike',
        '## Documentation check',
        '## Items for the follow-up ticket',
    ];

    private const FOLLOW_UP_MARKER = '**For the follow-up ticket:**';

    private const PROBE_NUMBER = '12';

    /**
     * One plausible source value per key type, as a source file would carry it. Anything not named
     * here is probed with a plain string.
     *
     * @var array<string, mixed>
     */
    private const PROBES = [
        'booleanSelect' => 1,
        'checkbox' => true,
        'country' => 'AT',
        'countrymultiselect' => ['AT', 'DE'],
        'date' => '2021-07-22',
        'datetime' => '2021-07-22 10:00:00',
        'email' => 'audit@example.com',
        'gender' => 'male',
        'inputQuantityValue' => self::PROBE_NUMBER,
        'language' => 'en',
        'languagemultiselect' => ['en', 'de'],
        'multiselect' => ['one', 'two'],
        'numeric' => 42,
        'quantityValue' => self::PROBE_NUMBER,
        'quantityValueRange' => self::PROBE_NUMBER,
        'rgbaColor' => '#ff0000',
        'slider' => 7,
        'table' => [['a', 'b']],
        'textarea' => "line1\nline2",
        'time' => '10:00',
        'wysiwyg' => '<p>audit</p>',
    ];

    private const PROBE_DEFAULT = 'audit';

    /**
     * @var \Pimcore\Bundle\DataImporterBundle\Tests\UnitTester
     */
    protected $tester;

    private int $groupId;

    /**
     * @var array<string, int> fieldtype => key id
     */
    private array $keyIds = [];

    /**
     * @var array<string, class-string<Data>>|null
     */
    private ?array $acceptedKeyTypes = null;

    private string $language;

    protected function _before(): void
    {
        $languages = Tool::getValidLanguages();
        $this->language = (string)(reset($languages) ?: 'en');

        $fixture = new ClassificationStoreFixture();
        $store = $fixture->store(self::STORE);
        $group = $fixture->group($store->getId(), self::GROUP);
        $this->groupId = $group->getId();

        $sorter = 1;
        foreach ($this->acceptedKeyTypes() as $type => $className) {
            $key = $fixture->key(
                $store->getId(),
                self::KEY_PREFIX . $type,
                $type,
                $this->fieldDefinition($type, $className)
            );
            $fixture->relate($group->getId(), $key->getId(), $sorter++);
            $this->keyIds[$type] = $key->getId();
        }

        $fixture->classDefinition(self::CLASS_NAME, $store->getId());
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
    }

    public function testTheDerivedKeyTypeSetMatchesTheAuditDocument(): void
    {
        $derived = array_keys($this->acceptedKeyTypes());
        sort($derived);

        static::assertSame(
            $this->documentedKeyTypes(),
            $derived,
            'The key types core accepts have changed. Update the list in the audit document, '
            . 'and re-run the audit so its verdict table covers them.'
        );
    }

    public function testTheAuditDocumentCarriesEverySectionItHasTo(): void
    {
        $document = $this->auditDocument();

        foreach (self::REQUIRED_SECTIONS as $section) {
            static::assertStringContainsString($section, $document, 'missing audit section');
        }
    }

    /**
     * Every numbered item of the follow-up list either names the change that fixed it or states why
     * it is not fixed, so that the document cannot drift away from what shipped.
     */
    public function testEveryFollowUpItemCarriesItsResolution(): void
    {
        $document = $this->auditDocument();
        $list = substr($document, (int)strpos($document, self::FOLLOW_UP_MARKER) + strlen(self::FOLLOW_UP_MARKER));
        $items = preg_split('/^(?=\d+\. )/m', $list, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $items = array_values(array_filter(
            $items,
            static fn (string $item): bool => preg_match('/^\d+\. /', $item) === 1
        ));

        static::assertCount(4, $items, 'the follow-up list is items 3 to 6');
        foreach ($items as $item) {
            static::assertMatchesRegularExpression(
                '/\*\*(Resolved here|Not fixed here):\*\*/',
                $item,
                'unclassified: ' . substr($item, 0, 60)
            );
        }
    }

    /**
     * The key types the importer acts on are the ones core derives, whatever core ships: the audit's
     * own acceptance rule and `KeyTypeSupport` agree over every field type core carries.
     */
    public function testTheTypeSetTheBundleActsOnIsTheOneCoreDerives(): void
    {
        /** @var KeyTypeSupport $support */
        $support = $this->tester->grabService(KeyTypeSupport::class);
        $directory = dirname((string)(new ReflectionClass(Data::class))->getFileName()) . '/Data';

        $held = [];
        foreach ((array)glob($directory . '/*.php') as $file) {
            $type = $this->keyTypeOf(Data::class . '\\' . basename((string)$file, '.php'));
            if ($type !== null && $support->isHeldInValueColumns($type)) {
                $held[] = $type;
            }
        }

        sort($held);
        static::assertSame(array_keys($this->acceptedKeyTypes()), $held);
    }

    public function testNoSourceFileEnumeratesKeyTypeNamesForThisPurpose(): void
    {
        $files = [
            ...(array)glob(dirname(__DIR__, 3) . '/src/Mapping/DataTarget/Classificationstore*.php'),
            dirname(__DIR__, 3) . '/src/Mapping/Type/ClassificationStoreDataTypeService.php',
        ];

        foreach ($files as $file) {
            static::assertDoesNotMatchRegularExpression(
                '/[\'"](geopoint|geobounds|geopolygon|geopolyline|hotspotimage'
                . '|imageGallery|date|datetime|image)[\'"]/i',
                (string)file_get_contents((string)$file),
                basename((string)$file) . ' lists key types'
            );
        }
    }

    /**
     * Asserted against the table the run actually emitted, and over the key types the acceptance
     * rule derives rather than the ones this test managed to create: a key type that never got a
     * key config, or a row the writer dropped, fails here instead of leaving the table short of
     * what it claims to cover.
     */
    public function testEveryKeyTypeHasAVerdictForEveryFieldVariantAndTarget(): void
    {
        $table = $this->writeVerdictTable($this->runRoundTrips());

        $expected = [];
        foreach (array_keys($this->acceptedKeyTypes()) as $type) {
            foreach (self::FIELD_VARIANTS as $field) {
                foreach (self::TARGETS as $target) {
                    $expected[] = $type . ' / ' . $this->variantLabel($field) . ' / ' . $target;
                }
            }
        }

        sort($expected);

        static::assertSame($expected, $this->rowsOf($table));
    }

    /**
     * The (key type, field variant, target) triples the emitted table actually holds a row for.
     *
     * @return string[]
     */
    private function rowsOf(string $table): array
    {
        $rows = [];
        foreach (explode("\n", $table) as $line) {
            if (preg_match('/^\| (\S+) \| (\S+) \| (\S+) \| /u', $line, $matches) !== 1) {
                continue;
            }

            if ($matches[1] === '---') {
                continue;
            }

            $rows[] = $matches[1] . ' / ' . $matches[2] . ' / ' . $matches[3];
        }

        sort($rows);

        return $rows;
    }

    /**
     * The finding the audit records about a plain date string inside a mixed `array` batch entry:
     * the batch target carries no format handling of its own, and `Date::normalize()` keeps only a
     * Carbon, so the value is lost on save rather than written as text.
     */
    public function testAPlainDateStringDoesNotSurviveAMixedBatchEntry(): void
    {
        $result = $this->roundTrip(
            'date',
            ClassificationStoreFixture::FIELD_NON_LOCALIZED,
            self::TARGET_BATCH
        );

        static::assertSame(
            self::VERDICT_DIFFERED,
            $result['verdict'],
            'A plain date string no longer reads back as this finding describes. Restate the finding '
            . 'in the audit document and in the follow-up item that currently carries it. The run '
            . 'noted: ' . $result['note']
        );
    }

    /**
     * Core's own acceptance rule for a classification store key, read off the installed core: the
     * field definition normalises to something a single value column can hold, or a
     * `classificationstore_<type>` marshaller takes it apart into the two value columns.
     *
     * @return array<string, class-string<Data>> fieldtype => field definition class
     */
    private function acceptedKeyTypes(): array
    {
        if ($this->acceptedKeyTypes !== null) {
            return $this->acceptedKeyTypes;
        }

        /** @var MarshallerService $marshallerService */
        $marshallerService = $this->tester->grabService(MarshallerService::class);
        $directory = dirname((string)(new ReflectionClass(Data::class))->getFileName()) . '/Data';

        $types = [];
        foreach ((array)glob($directory . '/*.php') as $file) {
            $className = Data::class . '\\' . basename((string)$file, '.php');
            $type = $this->keyTypeOf($className);
            if ($type === null) {
                continue;
            }

            if ($this->normalisesToASingleValue($className)
                || $marshallerService->supportsFielddefinition(self::MARSHALLER_FORMAT, $type)) {
                $types[$type] = $className;
            }
        }

        ksort($types);
        $this->acceptedKeyTypes = $types;

        return $types;
    }

    /**
     * The field type the given class definition data class carries, or null when it is not a class a
     * key config could hold at all: not loadable, abstract, not constructible without arguments, not
     * a normaliser, or nameless.
     */
    private function keyTypeOf(string $className): ?string
    {
        if (!class_exists($className)) {
            return null;
        }

        $reflection = new ReflectionClass($className);
        if ($reflection->isAbstract()) {
            return null;
        }

        // a field definition the scanner cannot build is not one a key config can carry either
        try {
            $fieldDefinition = $reflection->newInstance();
        } catch (Throwable) {
            return null;
        }

        if (!$fieldDefinition instanceof NormalizerInterface || !$fieldDefinition instanceof Data) {
            return null;
        }

        $type = $fieldDefinition->getFieldType();

        return $type === '' ? null : $type;
    }

    /**
     * @param class-string<Data> $className
     */
    private function normalisesToASingleValue(string $className): bool
    {
        $returnType = (new ReflectionMethod($className, 'normalize'))->getReturnType();

        return !$returnType instanceof ReflectionNamedType || $returnType->getName() !== 'array';
    }

    /**
     * @param class-string<Data> $className
     */
    private function fieldDefinition(string $type, string $className): Data
    {
        /** @var Data $fieldDefinition */
        $fieldDefinition = new $className();
        $fieldDefinition->setName(self::KEY_PREFIX . $type);

        if ($fieldDefinition instanceof EncryptedField) {
            $delegate = new Input();
            $delegate->setName(self::KEY_PREFIX . $type);
            $fieldDefinition->setDelegateDatatype('input');
            $fieldDefinition->setDelegate($delegate);
        }

        return $fieldDefinition;
    }

    /**
     * @return array<string, array<string, array<string, array{verdict: string, note: string}>>>
     */
    private function runRoundTrips(): array
    {
        $verdicts = [];

        foreach (array_keys($this->keyIds) as $type) {
            foreach (self::FIELD_VARIANTS as $field) {
                foreach (self::TARGETS as $target) {
                    $verdicts[$type][$field][$target] = $this->roundTrip($type, $field, $target);
                }
            }
        }

        return $verdicts;
    }

    /**
     * Source value to factory-built pipeline to data target to save to reload to comparison.
     *
     * @return array{verdict: string, note: string}
     */
    private function roundTrip(string $type, string $field, string $target): array
    {
        $probe = self::PROBES[$type] ?? self::PROBE_DEFAULT;

        // an audit records a crash as a verdict rather than failing on it, which is why this
        // catches Throwable: a TypeError out of a target is exactly one of the findings
        try {
            // unsaved, so that the save below is this object's only one: see
            // ClassificationStoreFixture::newObject() for why a second save cannot reach a verdict
            $element = (new ClassificationStoreFixture())->newObject(self::CLASS_NAME, uniqid('cs-audit-', false));
            $mappingConfiguration = $this->mappingConfiguration($type, $field, $target);

            $value = $target === self::TARGET_BATCH
                ? [$this->groupId . '-' . $this->keyIds[$type] => $probe]
                : $probe;

            foreach ($mappingConfiguration->getTransformationPipeline() as $operator) {
                $value = $operator->process($value);
            }

            $mappingConfiguration->getDataTarget()->assignData($element, $value);
            $element->save();

            $reloaded = $this->reloadKeyValue($element, $field, $type);
        } catch (Throwable $throwable) {
            return [
                'verdict' => self::VERDICT_UNREACHABLE,
                'note' => get_class($throwable) . ': ' . $throwable->getMessage(),
            ];
        }

        if ($reloaded == $probe) {
            return ['verdict' => self::VERDICT_SURVIVED, 'note' => ''];
        }

        return ['verdict' => self::VERDICT_DIFFERED, 'note' => 'read back as ' . $this->render($reloaded)];
    }

    private function mappingConfiguration(string $type, string $field, string $target): MappingConfiguration
    {
        /** @var MappingConfigurationFactory $factory */
        $factory = $this->tester->grabService(MappingConfigurationFactory::class);

        $settings = ['fieldName' => $field];
        if ($field === ClassificationStoreFixture::FIELD_LOCALIZED) {
            $settings['language'] = $this->language;
        }
        if ($target === self::TARGET_SINGLE_KEY) {
            $settings['keyId'] = $this->groupId . '-' . $this->keyIds[$type];
        }

        return $factory->loadMappingConfigurationItem('audit', [
            'label' => $type,
            'transformationPipeline' => [],
            'dataTarget' => ['type' => $target, 'settings' => $settings],
        ]);
    }

    /**
     * @return mixed
     */
    private function reloadKeyValue(Concrete $element, string $field, string $type)
    {
        $reloaded = Concrete::getById((int)$element->getId(), ['force' => true]);
        static::assertInstanceOf(Concrete::class, $reloaded);

        $getter = 'get' . ucfirst($field);
        /** @var ClassificationstoreModel $classificationStore */
        $classificationStore = $reloaded->$getter();

        return $classificationStore->getLocalizedKeyValue(
            $this->groupId,
            $this->keyIds[$type],
            $field === ClassificationStoreFixture::FIELD_LOCALIZED ? $this->language : 'default'
        );
    }

    /**
     * @param array<string, array<string, array<string, array{verdict: string, note: string}>>> $verdicts
     *
     * @return string the table as written
     */
    private function writeVerdictTable(array $verdicts): string
    {
        $lines = [
            '# Classification store round trip verdicts',
            '',
            'One row per (key type, field variant, target). Written by '
            . self::class . '; the audit document next to that test states what it is not evidence of.',
            '',
            '| key type | field variant | target | verdict | note |',
            '| --- | --- | --- | --- | --- |',
        ];

        foreach ($verdicts as $type => $fields) {
            foreach ($fields as $field => $targets) {
                foreach ($targets as $target => $result) {
                    $lines[] = sprintf(
                        '| %s | %s | %s | %s | %s |',
                        $type,
                        $this->variantLabel($field),
                        $target,
                        $result['verdict'],
                        str_replace(["\r", "\n", '|'], [' ', ' ', '\\|'], $result['note'])
                    );
                }
            }
        }

        $table = implode("\n", $lines) . "\n";
        file_put_contents(codecept_output_dir('classification-store-round-trip-audit.md'), $table);

        return $table;
    }

    private function variantLabel(string $field): string
    {
        return $field === ClassificationStoreFixture::FIELD_LOCALIZED ? 'localized' : 'non-localized';
    }

    /**
     * @return string[]
     */
    private function documentedKeyTypes(): array
    {
        if (preg_match('/## Key types under audit.*?```\n(.*?)```/su', $this->auditDocument(), $matches) !== 1) {
            static::fail('The audit document states no key type list under "## Key types under audit".');
        }

        $types = (array)preg_split('/\s+/u', trim($matches[1]), -1, PREG_SPLIT_NO_EMPTY);
        sort($types);

        return $types;
    }

    private function auditDocument(): string
    {
        $path = __DIR__ . '/CLASSIFICATION_STORE_ROUND_TRIP_AUDIT.md';
        static::assertFileExists($path, 'The audit document is not committed next to this test.');

        return (string)file_get_contents($path);
    }

    /**
     * @param mixed $value
     */
    private function render($value): string
    {
        if (is_object($value)) {
            return get_class($value);
        }

        if (is_array($value)) {
            return 'array(' . count($value) . ')';
        }

        if ($value === null) {
            return 'null';
        }

        return is_scalar($value) ? var_export($value, true) : gettype($value);
    }
}
