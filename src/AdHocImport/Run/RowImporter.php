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

use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunMessages;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunPlan;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\FieldWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ImportPlan;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PreSaveEvent;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\ProcessElementExceptionEvent;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplicationScope;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatchOutcome;
use Pimcore\Cache\RuntimeCache;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Service;
use Pimcore\Model\Element\Service as ElementService;
use Pimcore\Model\User;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Imports one row of a file: finds its object, checks the user's rights on it, applies the mapping and hands it to the
 * writer. A row that cannot be saved is reported, and does not stop the run.
 *
 * @internal
 */
final readonly class RowImporter
{
    // the events of an import name its configuration; an ad hoc import has none
    public const string CONFIG_NAME = '';

    public function __construct(
        private RowWriterInterface $writer,
        private MappingApplicationScope $scope,
        private DryRunMessages $dryRunMessages,
        private RunMessages $messages,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param int $index the row's position among the file's rows
     * @param list<int> $leftOut the positions of the writers whose value the row goes without
     * @param RunRecord|null $run the run the row belongs to, which the writer may write into
     */
    public function import(ImportPlan $plan, User $user, int $index, array $leftOut, ?RunRecord $run = null): RowOutcome
    {
        $dryRun = $plan->dryRun;
        $row = $plan->rows[$index] ?? [];
        $rowNumber = $plan->firstRowNumber + $index;
        $match = $dryRun->matcher->match($row, $rowNumber);
        $object = $match->element;
        if (!$object instanceof Concrete) {
            // a taken key stops an object the row would have created
            $action = $match->outcome === RowMatchOutcome::KeyTaken ? 'create' : null;

            return RowOutcome::failed($match->matchValue, $action, $this->dryRunMessages->rowError(
                $match,
                $dryRun->matchField,
                $dryRun->targetFolder,
                $dryRun->newObjectFolder,
                $user
            ));
        }

        $isNew = $match->outcome === RowMatchOutcome::New;
        $action = $isNew ? 'create' : 'update';

        try {
            $refusal = $this->rightsRefusal($object, $isNew, $dryRun, $user, $leftOut, $match->matchValue);
            if ($refusal !== null) {
                return RowOutcome::failed($match->matchValue, $action, $refusal);
            }

            $refusal = $this->applyMapping($object, $dryRun, $user, $index, $row, $leftOut);
            if ($refusal !== null) {
                return RowOutcome::failed($match->matchValue, $action, $refusal);
            }

            $event = new PreSaveEvent(self::CONFIG_NAME, $row, $object);
            $this->eventDispatcher->dispatch($event);
            if ($event->shouldSkipSave()) {
                return RowOutcome::failed($match->matchValue, $action, $this->messages->leftOutByListener($user));
            }

            $this->writer->write(new RowWrite(
                $object,
                $row,
                $isNew,
                $user,
                $rowNumber,
                $match->matchValue,
                $this->folderOf($object, $isNew),
                $plan->cells($index),
                $run
            ));

            return RowOutcome::saved($isNew, $match->matchValue);
        } catch (\Throwable $throwable) { // NOSONAR php:S2221 one row must not end the run, whatever stops it
            $this->logger->error(sprintf(
                'Ad hoc import, row %d of %s: %s',
                $rowNumber,
                $dryRun->targetFolder,
                $throwable->getMessage()
            ));
            $message = $this->messages->writeFailure(
                $throwable,
                (string) $object->getKey(),
                $this->folderOf($object, $isNew),
                $user
            );
            $this->eventDispatcher->dispatch(new ProcessElementExceptionEvent(
                self::CONFIG_NAME,
                $row,
                $object,
                $message,
                $throwable,
                null
            ));

            return RowOutcome::failed($match->matchValue, $action, $message);
        } finally {
            // what a row changed stays in memory: another row or a retry reads the object as it is saved
            if ($object->getId() !== null) {
                RuntimeCache::set(ElementService::getElementCacheTag('object', $object->getId()), null);
            }
        }
    }

    /**
     * @param list<int> $leftOut
     */
    private function rightsRefusal(
        Concrete $object,
        bool $isNew,
        DryRunPlan $plan,
        User $user,
        array $leftOut,
        string $matchValue
    ): ?string {
        $folder = $this->folderOf($object, $isNew);
        $refusal = $isNew
            ? $this->createRefusal($object, $folder, $user)
            : $this->changeRefusal($object, $folder, $plan, $user, $matchValue);
        if ($refusal !== null) {
            return $refusal;
        }

        $language = $this->languageNotEditable($object, $plan->writers, $leftOut, $user);

        return $language === null ? null : $this->messages->mayNotEditLanguage($language, $folder, $user);
    }

    private function createRefusal(Concrete $object, string $folder, User $user): ?string
    {
        return $object->getParent()?->isAllowed('create', $user) ? null : $this->messages->mayNotCreate($folder, $user);
    }

    private function changeRefusal(
        Concrete $object,
        string $folder,
        DryRunPlan $plan,
        User $user,
        string $matchValue
    ): ?string {
        if (!$object->isAllowed('view', $user)) {
            return $this->dryRunMessages->notViewable($plan->matchField, $matchValue, $user);
        }

        // changing what is live needs publish, as saving it in Studio does
        $right = $object->isPublished() ? 'publish' : 'save';

        return $object->isAllowed($right, $user) ? null : $this->messages->mayNotSave($folder, $user);
    }

    /**
     * @param list<FieldWriter> $writers
     * @param list<int> $leftOut
     */
    private function languageNotEditable(Concrete $object, array $writers, array $leftOut, User $user): ?string
    {
        $editable = $user->isAdmin() ? null : Service::getLanguagePermissions($object, $user, 'lEdit');
        if ($editable === null) {
            return null;
        }

        foreach ($writers as $position => $writer) {
            $language = $writer->field()->language;
            if ($language !== null && !isset($editable[$language]) && !in_array($position, $leftOut, true)) {
                return $language;
            }
        }

        return null;
    }

    /**
     * @param array<int|string, mixed> $row
     * @param list<int> $leftOut
     *
     * @return string|null why a value cannot go into its field
     */
    private function applyMapping(
        Concrete $object,
        DryRunPlan $plan,
        User $user,
        int $index,
        array $row,
        array $leftOut
    ): ?string {
        return $this->scope->run(null, function () use ($object, $plan, $user, $index, $row, $leftOut): ?string {
            foreach ($plan->writers as $position => $writer) {
                if (in_array($position, $leftOut, true)) {
                    continue;
                }

                $error = $writer->forUser($user)->write($object, $index, $row);
                if ($error !== null) {
                    return $this->dryRunMessages->valueError(
                        $error,
                        $writer->field()->label,
                        $writer->fileValue($row) ?? '',
                        $user
                    );
                }
            }

            return null;
        });
    }

    private function folderOf(Concrete $object, bool $isNew): string
    {
        // an object not saved yet has no path, only the folder it goes to
        $path = $isNew ? $object->getParent()?->getRealFullPath() : $object->getRealPath();

        return rtrim((string) $path, '/') ?: '/';
    }
}
