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

use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunMessages;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunPlan;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\FieldWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\ImportField;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\ValueError;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileType;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ImportPlan;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowFailure;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunMessages;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatch;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatcherInterface;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\User;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the tests of a run share, none of it reaching the database.
 */
final class AdHocRunFixtures
{
    public const string FILE_ID = 'file-1';

    public const int USER_ID = 7;

    public static function user(bool $admin = true): User
    {
        $user = new User();
        $user->setId(self::USER_ID);
        $user->setName('run-user');
        $user->setAdmin($admin);
        $user->setLanguage('en');

        return $user;
    }

    /**
     * @param array<string, mixed> $given
     */
    public static function run(array $given = []): RunRecord
    {
        return RunRecord::fromArray([
            'userId' => self::USER_ID,
            'fileId' => self::FILE_ID,
            'type' => FileType::Csv->value,
            'fileName' => 'autumn.csv',
            'parameters' => ['folderId' => 3, 'classId' => 'PROD', 'matchBy' => 'field', 'matchColumn' => 'sku'],
            'configuration' => null,
            'errorMode' => 'skip',
            'total' => 2,
            'skipped' => 1,
            'matchColumn' => 'SKU',
            'newObjectsFolder' => '/Products',
            'newObjectsFolderId' => 4,
            'userName' => 'Jo Doe',
            ...$given,
        ]);
    }

    /**
     * @param list<RowMatch|\Exception> $matches what the rows find, in order; an exception is thrown
     * @param list<FieldWriter> $writers
     * @param list<array<int|string, mixed>> $rows
     */
    public static function plan(array $matches, array $writers, ?User $user = null, array $rows = [[]]): ImportPlan
    {
        $matcher = new class($matches) implements RowMatcherInterface {
            /** @param list<RowMatch|\Exception> $matches */
            public function __construct(private array $matches)
            {
            }

            public function match(array $row, int $rowNumber): RowMatch
            {
                $match = array_shift($this->matches);
                if ($match instanceof \Exception) {
                    throw $match;
                }

                return $match;
            }
        };

        return new ImportPlan(
            new DryRunPlan($rows, 2, $matcher, $writers, [], 'SKU', '/Products', '/Products/New', $user ?? self::user()),
            $rows,
            2,
            ['sku' => 'SKU', 'name' => 'Name'],
            'SKU',
            4,
            null
        );
    }

    public static function writer(?ValueError $error = null, ?string $language = null): FieldWriter
    {
        return new class($error, $language) implements FieldWriter {
            /** @var list<int> */
            public array $writes = [];

            public function __construct(private ?ValueError $error, private ?string $language)
            {
            }

            public function field(): ImportField
            {
                return new ImportField('name', $this->language, 'Name');
            }

            public function write(Concrete $object, int $index, array $row): ?ValueError
            {
                $this->writes[] = $index;

                return $this->error;
            }

            public function fileValue(array $row): ?string
            {
                return 'bad';
            }
        };
    }

    public static function translator(): TranslatorInterface
    {
        return new class() implements TranslatorInterface {
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return $id . ($parameters === [] ? '' : ' ' . json_encode($parameters, JSON_UNESCAPED_SLASHES));
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };
    }

    public static function dryRunMessages(): DryRunMessages
    {
        return new DryRunMessages(self::translator());
    }

    public static function runMessages(): RunMessages
    {
        return new RunMessages(self::translator());
    }

    public static function memoryStore(): RunStoreInterface
    {
        return new class() implements RunStoreInterface {
            /** @var array<string, RunRecord> */
            private array $runs = [];

            /** @var array<string, list<RowFailure>> */
            private array $failures = [];

            public function find(int $userId, string $fileId): ?RunRecord
            {
                return $this->runs[$userId . '/' . $fileId] ?? null;
            }

            public function save(RunRecord $run): void
            {
                $this->runs[$run->userId . '/' . $run->fileId] = $run;
            }

            public function delete(int $userId, string $fileId): void
            {
                unset($this->runs[$userId . '/' . $fileId]);
            }

            public function addFailure(RunRecord $run, RowFailure $failure): void
            {
                $this->failures[$run->fileId][] = $failure;
            }

            public function takeFailures(RunRecord $run): array
            {
                $failures = $this->failures[$run->fileId] ?? [];
                unset($this->failures[$run->fileId]);
                usort($failures, static fn (RowFailure $a, RowFailure $b): int => $a->row <=> $b->row);

                return $failures;
            }
        };
    }
}
