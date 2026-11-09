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

        return implode("\n", $lines);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function say(User $user, string $key, array $parameters): string
    {
        return $this->translator->trans($key, $parameters, self::DOMAIN, $user->getLanguage() ?: null);
    }
}
