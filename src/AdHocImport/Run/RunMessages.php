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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\Run;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Pimcore\Model\Element\DuplicateFullPathException;
use Pimcore\Model\Element\ValidationException;
use Pimcore\Model\User;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What a run says about its rows and about its end, in the user's language.
 *
 * @internal
 */
final readonly class RunMessages
{
    private const string DOMAIN = 'studio';

    private const string RUN = 'data-importer.ad-hoc-import.run.';

    private const string CHECK = 'data-importer.ad-hoc-import.check.';

    private const string MERGE = 'data-importer.ad-hoc-import.merge.';

    // what a notification lists before it only counts, so a failed run stays readable
    private const int NOTIFIED_ROWS = 20;

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function mayNotSave(string $folder, User $user): string
    {
        return $this->say($user, self::RUN . 'may-not-save', ['{{folder}}' => $folder]);
    }

    public function mayNotCreate(string $folder, User $user): string
    {
        return $this->say($user, self::RUN . 'may-not-create', ['{{folder}}' => $folder]);
    }

    public function mayNotEditLanguage(string $language, string $folder, User $user): string
    {
        return $this->say($user, self::RUN . 'may-not-edit-language', [
            '{{language}}' => $language,
            '{{folder}}' => $folder,
        ]);
    }

    public function leftOutByListener(User $user): string
    {
        return $this->say($user, self::RUN . 'left-out-by-listener', []);
    }

    public function cannotGoOn(User $user): string
    {
        return $this->say($user, self::RUN . 'cannot-go-on', []);
    }

    /**
     * Only what is written for people: other exceptions can hold SQL and values of objects the user may not see.
     */
    public function writeFailure(\Throwable $throwable, string $key, string $folder, User $user): string
    {
        if ($throwable instanceof DuplicateFullPathException) {
            return $this->say($user, self::CHECK . 'key-taken', ['{{key}}' => $key, '{{folder}}' => $folder]);
        }

        if ($throwable instanceof ValidationException) {
            return $throwable->getPrevious() instanceof UniqueConstraintViolationException
                ? $this->say($user, self::RUN . 'must-be-unique', [])
                : $this->say($user, self::RUN . 'save-failed', ['{{reason}}' => $throwable->getMessage()]);
        }

        return $this->say($user, self::RUN . 'save-failed-unknown', []);
    }

    public function branchName(RunRecord $run, User $user): string
    {
        return $this->say($user, self::MERGE . 'branch-name', [
            '{{file}}' => $run->fileName,
            '{{folder}}' => $run->targetFolder,
        ]);
    }

    /**
     * Why the merge did not write a row, by the kind of problem: what Change Control says itself can hold an
     * exception's text, so it goes to the log only.
     */
    public function mergeProblem(string $code, ?RecordedRow $row, User $user): string
    {
        return match ($code) {
            'not_allowed' => $row?->isNew()
                ? $this->mayNotCreate((string) $row->folder, $user)
                : $this->mayNotSave((string) $row?->folder, $user),
            'object_gone' => $this->say($user, self::MERGE . 'object-gone', []),
            'invalid_value' => $this->say($user, self::MERGE . 'invalid-value', []),
            'missing_new_object' => $this->say($user, self::MERGE . 'missing-new-object', []),
            'invalid_place' => $row?->isNew()
                ? $this->say($user, self::CHECK . 'key-taken', ['{{key}}' => $row->key, '{{folder}}' => $row->folder])
                : $this->say($user, self::RUN . 'save-failed-unknown', []),
            'interrupted' => $this->mergeInterrupted($user),
            default => $this->say($user, self::RUN . 'save-failed-unknown', []),
        };
    }

    public function mergeInterrupted(User $user): string
    {
        return $this->say($user, self::MERGE . 'interrupted', []);
    }

    /**
     * A problem of the merge as a whole, which no row of the file shows.
     */
    public function mergeNotice(string $code, User $user): string
    {
        return $this->say($user, self::MERGE . 'notice', ['{{reason}}' => $this->mergeProblem($code, null, $user)]);
    }

    public function mergeTimedOut(User $user): string
    {
        return $this->say($user, self::MERGE . 'timed-out', []);
    }

    public function decisionTitle(RunRecord $run, User $user): string
    {
        return $this->say($user, self::MERGE . 'decision.title', ['{{file}}' => $run->fileName]);
    }

    /**
     * @param list<RowFailure> $failures
     */
    public function decisionText(RunRecord $run, array $failures, User $user): string
    {
        return implode("\n", [
            $this->say($user, self::MERGE . 'decision.summary', [
                '{{ready}}' => (string) ($run->updated + $run->created),
                '{{failed}}' => (string) $run->failed,
                '{{folder}}' => $run->targetFolder,
            ]),
            ...$this->rowLines($failures, $user),
        ]);
    }

    public function notificationTitle(RunRecord $run, User $user): string
    {
        return $this->say($user, self::RUN . 'notification.title', ['{{file}}' => $run->fileName]);
    }

    /**
     * @param list<RowFailure> $failures
     */
    public function notificationText(RunRecord $run, array $failures, User $user): string
    {
        $lines = [$this->say($user, self::RUN . 'notification.summary', [
            '{{updated}}' => (string) $run->updated,
            '{{created}}' => (string) $run->created,
            '{{folder}}' => $run->newObjectsFolder,
            '{{failed}}' => (string) $run->failed,
            '{{skipped}}' => (string) $run->skipped,
        ])];
        if ($run->notice !== null) {
            $lines[] = $run->notice;
        }

        return implode("\n", [...$lines, ...$this->rowLines($failures, $user)]);
    }

    /**
     * @param list<RowFailure> $failures
     *
     * @return list<string>
     */
    private function rowLines(array $failures, User $user): array
    {
        $lines = [];
        foreach (array_slice($failures, 0, self::NOTIFIED_ROWS) as $failure) {
            $lines[] = $this->say($user, self::RUN . 'notification.row', [
                '{{row}}' => (string) $failure->row,
                '{{value}}' => $failure->matchValue,
                '{{reason}}' => $failure->message,
            ]);
        }

        if (count($failures) > self::NOTIFIED_ROWS) {
            $lines[] = $this->say($user, self::RUN . 'notification.more', [
                '{{count}}' => (string) (count($failures) - self::NOTIFIED_ROWS),
            ]);
        }

        return $lines;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function say(User $user, string $key, array $parameters): string
    {
        return $this->translator->trans($key, $parameters, self::DOMAIN, $user->getLanguage() ?: null);
    }
}
