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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun;

use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatch;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatchOutcome;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element\Service;
use Pimcore\Model\User;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the preview and the download of the skipped rows say about a row, in the user's language.
 *
 * @internal
 */
final readonly class DryRunMessages
{
    private const string DOMAIN = 'studio';

    private const string PREFIX = 'data-importer.ad-hoc-import.check.';

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param string $matchField how the field the rows are matched by is called
     * @param string $targetFolder the path of the folder the import goes into
     * @param string $newObjectFolder the path of the folder new objects go to
     */
    public function rowError(
        RowMatch $match,
        string $matchField,
        string $targetFolder,
        string $newObjectFolder,
        User $user
    ): string {
        $value = $match->matchValue;
        $field = ['{{field}}' => $matchField, '{{value}}' => $value];

        return match ($match->outcome) {
            RowMatchOutcome::EmptyValue => $this->say($user, 'empty-value', $field),
            RowMatchOutcome::InvalidValue => $this->say($user, 'invalid-match-value', $field),
            RowMatchOutcome::DuplicateInFile => $this->say(
                $user,
                'duplicate-in-file',
                [...$field, '{{row}}' => (string) $match->firstRow]
            ),
            RowMatchOutcome::SeveralMatches => $this->say(
                $user,
                'several-matches',
                [...$field, '{{count}}' => (string) count($match->objectIds)]
            ),
            RowMatchOutcome::OutsideTargetFolder => $this->outside($match, $targetFolder, $user),
            RowMatchOutcome::UnknownObjectId => $this->say($user, 'unknown-object-id', $field),
            RowMatchOutcome::OtherClass => $this->say($user, 'other-class', $field),
            RowMatchOutcome::KeyTaken => $this->keyTaken($match, $newObjectFolder, $user),
            RowMatchOutcome::Existing, RowMatchOutcome::New => '',
        };
    }

    public function valueError(ValueError $error, string $field, string $value, User $user): string
    {
        return $this->say($user, 'value.' . $error->value, ['{{field}}' => $field, '{{value}}' => $value]);
    }

    public function objectIdLabel(User $user): string
    {
        return $this->say($user, 'object-id', []);
    }

    /**
     * @param 'row'|'error' $name
     */
    public function columnName(string $name, User $user): string
    {
        return $this->say($user, 'column.' . $name, []);
    }

    // where an object the user may not see lies is not told
    private function outside(RowMatch $match, string $targetFolder, User $user): string
    {
        $object = DataObject::getById($match->objectIds[0] ?? 0);
        if ($object === null || !$object->isAllowed('view', $user)) {
            return $this->say($user, 'outside-target-folder', ['{{folder}}' => $targetFolder]);
        }

        return $this->say($user, 'found-outside-target-folder', [
            '{{path}}' => rtrim($object->getRealPath(), '/') ?: '/',
            '{{folder}}' => $targetFolder,
        ]);
    }

    private function keyTaken(RowMatch $match, string $newObjectFolder, User $user): string
    {
        $key = Service::getValidKey($match->matchValue, 'object');

        return $match->firstRow === null
            ? $this->say($user, 'key-taken', ['{{key}}' => $key, '{{folder}}' => $newObjectFolder])
            : $this->say($user, 'key-taken-in-file', ['{{key}}' => $key, '{{row}}' => (string) $match->firstRow]);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function say(User $user, string $key, array $parameters): string
    {
        return $this->translator->trans(self::PREFIX . $key, $parameters, self::DOMAIN, $user->getLanguage() ?: null);
    }
}
