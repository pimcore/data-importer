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

namespace Pimcore\Bundle\DataImporterBundle\ChangeControl;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function class_exists;
use function count;
use Cron\CronExpression;
use DateTime;
use function explode;
use function in_array;
use function is_array;
use function is_scalar;
use function is_string;
use function method_exists;
use Pimcore\Bundle\DataImporterBundle\Cleanup\CleanupStrategyFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Processing\Scheduler\JobScheduler;
use Pimcore\Bundle\DataImporterBundle\Resolver\ResolverFactory;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Objectbrick;
use function sprintf;
use function ucfirst;

/**
 * Whether the importer could load a configuration. Each component is built by the importer's own
 * factory, so the check follows the engine's rules instead of copying them; anything that can run
 * is the review's to judge. An update is judged only where it differs from the stored configuration:
 * a stored section nobody touches is not the proposal's to refuse.
 *
 * @internal
 */
final readonly class ImportConfigCheck
{
    private const string MAPPING = 'mappingConfig';

    private const string INTERPRETER = 'interpreterConfig';

    private const string RESOLVER = 'resolverConfig';

    private const string PROCESSING = 'processingConfig';

    private const string EXECUTION = 'executionConfig';

    /** sections the importer reads as objects */
    private const array OBJECT_SECTIONS = [
        'general', 'loaderConfig', self::INTERPRETER, self::RESOLVER, self::PROCESSING, self::EXECUTION,
    ];

    /** data targets that write the field fieldName names (Direct::assignData) */
    private const array FIELD_TARGETS = ['direct', 'manyToManyRelation'];

    private const array CLASSIFICATION_STORE_TARGETS = ['classificationstore', 'classificationstoreBatch'];

    /** what Direct writes without a field of the class */
    private const array SYSTEM_TARGETS = ['key', 'path'];

    /** container.brick.field */
    private const int BRICK_PARTS = 3;

    /** what JobScheduler reads */
    private const string SCHEDULED_AT_FORMAT = 'd-m-Y H:i';

    /** what a problem prints for a value that cannot be printed */
    private const string NOT_A_STRING = 'non-string';

    /** keeps enrichment, which may ask options providers, out of a field lookup */
    private const array NO_ENRICHMENT = ['suppressEnrichment' => true];

    public function __construct(
        private LoaderSettingsCheck $loaderSettings,
        private InterpreterFactory $interpreters,
        private ResolverFactory $resolvers,
        private MappingConfigurationFactory $mappings,
        private CleanupStrategyFactory $cleanupStrategies,
    ) {
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed>|null $stored null for a create
     *
     * @return list<string>
     */
    public function problems(array $state, ?array $stored): array
    {
        $changed = static fn (string $section): bool => $stored === null
            || KeySorted::of($state[$section] ?? null) !== KeySorted::of($stored[$section] ?? null);

        $malformed = $this->malformed($state, $changed);
        if ($malformed !== []) {
            return $malformed;
        }

        // a section the document does not carry is the create check's to name
        $judged = static fn (string $section): bool => array_key_exists($section, $state) && $changed($section);

        $problems = $changed('loaderConfig') ? $this->loaderSettings->problems($state) : [];
        // the interpreter is built with the processing settings too
        $interpreterJudged = array_key_exists(self::INTERPRETER, $state)
            && ($changed(self::INTERPRETER) || $changed(self::PROCESSING));
        if ($interpreterJudged) {
            $problems[] = $this->interpreterProblem($state);
        }
        if ($judged(self::RESOLVER)) {
            $problems[] = $this->resolverProblem($state);
        }
        if ($judged(self::PROCESSING)) {
            $problems[] = $this->cleanupProblem($state[self::PROCESSING]);
        }
        if ($judged(self::EXECUTION)) {
            $problems[] = $this->scheduleProblem($state[self::EXECUTION]);
        }

        // rows written for another class are judged again when the class changes
        $classChanged = $stored === null || $this->classId($state) !== $this->classId($stored);
        $problems = [...$problems, ...$this->mappingProblems($state, $classChanged ? null : $stored)];

        return array_values(array_filter($problems, static fn (?string $problem): bool => $problem !== null));
    }

    /**
     * @param array<string, mixed> $state
     * @param callable(string): bool $changed
     *
     * @return list<string>
     */
    private function malformed(array $state, callable $changed): array
    {
        $problems = [];
        foreach (self::OBJECT_SECTIONS as $section) {
            if (array_key_exists($section, $state) && !is_array($state[$section]) && $changed($section)) {
                $problems[] = sprintf('%s must be an object.', $section);
            }
        }

        if (array_key_exists(self::MAPPING, $state) && !is_array($state[self::MAPPING]) && $changed(self::MAPPING)) {
            $problems[] = 'mappingConfig must be a list of mapping entries, one per column.';
        }

        return $problems;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function interpreterProblem(array $state): ?string
    {
        $config = $state[self::INTERPRETER];
        $type = $config['type'] ?? null;
        if (!is_string($type) || $type === '') {
            return 'interpreterConfig.type is missing.';
        }

        $processing = $state[self::PROCESSING] ?? [];

        return SettingsRead::problem(
            self::INTERPRETER,
            sprintf('the "%s" interpreter', $type),
            function () use ($state, $config, $processing): void {
                $this->interpreters->loadInterpreter($this->configName($state), $config, $processing);
            },
        );
    }

    /**
     * @param array<string, mixed> $state
     */
    private function resolverProblem(array $state): ?string
    {
        $config = $state[self::RESOLVER];
        $problem = SettingsRead::problem(self::RESOLVER, 'the resolver', function () use ($config): void {
            $this->resolvers->loadResolver($config);
        });
        if ($problem !== null) {
            return $problem;
        }

        $classId = $this->classId($state);

        return $classId !== null && $this->targetClass($state) === null
            ? sprintf('resolverConfig.dataObjectClassId "%s" is not a data object class here.', $classId)
            : null;
    }

    /**
     * @param array<string, mixed> $processing
     */
    private function cleanupProblem(array $processing): ?string
    {
        $strategy = $processing['cleanup']['strategy'] ?? null;
        // a strategy nobody switched on is never loaded
        if (empty($processing['cleanup']['doCleanup'])) {
            return null;
        }

        try {
            // the import hands the strategy to loadCleanupStrategy(string) as it is stored
            $this->cleanupStrategies->loadCleanupStrategy(is_string($strategy) ? $strategy : '');
        } catch (InvalidConfigurationException) {
            return sprintf(
                'processingConfig.cleanup.strategy "%s" is not a cleanup strategy here.',
                self::printable($strategy),
            );
        }

        return null;
    }

    /**
     * An empty schedule never runs, which is the review's to judge; one the scheduler cannot read
     * fails every run.
     *
     * @param array<string, mixed> $execution
     */
    private function scheduleProblem(array $execution): ?string
    {
        if (($execution['scheduleType'] ?? null) === JobScheduler::NAME) {
            $at = $execution['scheduledAt'] ?? null;
            if (self::isUnset($at) || (is_string($at) && $this->isScheduledAt($at))) {
                return null;
            }

            return sprintf(
                'executionConfig.scheduledAt "%s" is not a date and time like "31-12-2026 23:30" (d-m-Y H:i).',
                self::printable($at),
            );
        }

        $cron = $execution['cronDefinition'] ?? null;
        if (self::isUnset($cron) || (is_string($cron) && CronExpression::isValidExpression($cron))) {
            return null;
        }

        return sprintf(
            'executionConfig.cronDefinition "%s" is not a cron expression like "0 3 * * *".',
            self::printable($cron),
        );
    }

    private function isScheduledAt(string $at): bool
    {
        $parsed = DateTime::createFromFormat(self::SCHEDULED_AT_FORMAT, $at);
        $errors = DateTime::getLastErrors();

        // an overflowing date ("32-13-2026") parses with a warning into another day
        return $parsed !== false && ($errors === false || $errors['warning_count'] === 0);
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed>|null $stored rows equal to a stored one are not judged
     *
     * @return list<string|null>
     */
    private function mappingProblems(array $state, ?array $stored): array
    {
        $rows = MappingRowIds::dropped($state)[self::MAPPING] ?? [];
        $storedRows = $stored === null ? [] : (MappingRowIds::dropped($stored)[self::MAPPING] ?? []);
        $untouched = is_array($storedRows) ? array_map(KeySorted::of(...), $storedRows) : [];
        $class = $this->targetClass($state);

        $problems = [];
        foreach (is_array($rows) ? $rows : [] as $index => $row) {
            if (!in_array(KeySorted::of($row), $untouched, true)) {
                $problems[] = $this->rowProblem(sprintf('mappingConfig[%s]', $index), $row, $state, $class);
            }
        }

        return $problems;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function rowProblem(string $where, mixed $row, array $state, ?ClassDefinition $class): ?string
    {
        if (!is_array($row)) {
            return sprintf('%s must be a mapping entry: an object with a dataSourceIndex and a dataTarget.', $where);
        }

        $type = $row['dataTarget']['type'] ?? null;
        if (!is_string($type) || $type === '') {
            return sprintf('%s.dataTarget.type is missing: every mapping entry needs a data target.', $where);
        }

        $problem = SettingsRead::problem($where, 'the mapping entry', function () use ($state, $row): void {
            $this->mappings->loadMappingConfigurationItem($this->configName($state), $row);
        });

        return $problem ?? ($class === null ? null : $this->fieldProblem($where, $type, $row['dataTarget'], $class));
    }

    /**
     * @param array<string, mixed> $dataTarget
     */
    private function fieldProblem(string $where, string $type, array $dataTarget, ClassDefinition $class): ?string
    {
        $fieldName = $dataTarget['settings']['fieldName'] ?? null;
        if (in_array($type, self::FIELD_TARGETS, true)) {
            $missing = !is_string($fieldName) || !$this->writesField($class, $fieldName);
            $kind = 'a field';
        } elseif (in_array($type, self::CLASSIFICATION_STORE_TARGETS, true)) {
            $missing = !is_string($fieldName)
                || !$class->getFieldDefinition($fieldName, self::NO_ENRICHMENT) instanceof Data\Classificationstore;
            $kind = 'a classification store field';
        } else {
            // a data target this check does not know the fields of is the review's to judge
            return null;
        }

        if (!$missing) {
            return null;
        }

        return sprintf(
            '%s.dataTarget.settings.fieldName "%s" is not %s of class %s.',
            $where,
            self::printable($fieldName),
            $kind,
            $class->getName(),
        );
    }

    /** "0" is a value the scheduler has to read, unlike empty() suggests */
    private static function isUnset(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    private static function printable(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : self::NOT_A_STRING;
    }

    /**
     * The forms Direct::assignData writes: key, path, a field of the class (localized ones included)
     * or another setter of the object, and container.brick.field.
     */
    private function writesField(ClassDefinition $class, string $fieldName): bool
    {
        if (in_array($fieldName, self::SYSTEM_TARGETS, true)) {
            return true;
        }

        $parts = explode('.', $fieldName);
        if (count($parts) === 1) {
            $objectClass = 'Pimcore\\Model\\DataObject\\' . ucfirst((string) $class->getName());
            $setterOwner = class_exists($objectClass) ? $objectClass : Concrete::class;

            return $class->getFieldDefinition($fieldName, self::NO_ENRICHMENT) !== null
                || method_exists($setterOwner, 'set' . ucfirst($fieldName));
        }

        if (count($parts) !== self::BRICK_PARTS) {
            return false;
        }

        [$container, $brick, $field] = $parts;
        $containerDefinition = $class->getFieldDefinition($container, self::NO_ENRICHMENT);

        return $containerDefinition instanceof Data\Objectbricks
            && in_array($brick, $containerDefinition->getAllowedTypes(), true)
            && Objectbrick\Definition::getByKey($brick)?->getFieldDefinition($field, self::NO_ENRICHMENT) !== null;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function targetClass(array $state): ?ClassDefinition
    {
        $classId = $this->classId($state);

        return $classId === null || $classId === '' ? null : ClassDefinition::getById($classId);
    }

    /**
     * @param array<string, mixed> $state
     */
    private function classId(array $state): ?string
    {
        $classId = $state[self::RESOLVER]['dataObjectClassId'] ?? null;

        return is_scalar($classId) ? (string) $classId : null;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function configName(array $state): string
    {
        $name = $state['general']['name'] ?? '';

        return is_string($name) ? $name : '';
    }
}
