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

namespace Pimcore\Bundle\DataImporterBundle\Service\Studio;

use Pimcore\Bundle\DataImporterBundle\AdHocImport\CsvText;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunMessages;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FieldAccess;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowOutcome;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\SpreadsheetCell;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\StoreKey;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ValueText;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportRunObjectsResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CurrentUserResolverTrait;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ConcreteObjectResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
final class AdHocImportRunObjectsService implements AdHocImportRunObjectsServiceInterface
{
    use CurrentUserResolverTrait;

    private const int MAX_PAGE_SIZE = 200;

    public function __construct(
        private readonly SecurityServiceInterface $securityService, // NOSONAR php:S1068 read by CurrentUserResolverTrait
        private readonly RunStoreInterface $runs,
        private readonly ConcreteObjectResolverInterface $objects,
        private readonly DryRunMessages $messages,
    ) {
    }

    public function objects(string $fileId, string $kind, int $page, int $pageSize): AdHocImportRunObjectsResponse
    {
        $user = $this->resolveCurrentUser();
        $run = $this->endedRun($user, $fileId);
        $ids = $this->idsOf($run, $kind);
        $pageSize = max(1, min($pageSize, self::MAX_PAGE_SIZE));
        $onPage = array_slice($ids, (max(1, $page) - 1) * $pageSize, $pageSize);

        $items = [];
        foreach ($onPage as $id) {
            $object = $this->viewable($id, $user);
            if ($object !== null) {
                $items[] = [
                    'id' => $id,
                    'fullPath' => $object->getRealFullPath(),
                    'modificationDate' => $object->getModificationDate(),
                    'values' => $this->valuesOf($object, $run, $user),
                ];
            }
        }

        return new AdHocImportRunObjectsResponse(
            count($ids),
            array_map(static fn (array $field): array => [
                'key' => $field['key'],
                'label' => $field['label'],
            ], $run->fields),
            $items
        );
    }

    public function objectsCsv(string $fileId, string $kind): string
    {
        $user = $this->resolveCurrentUser();
        $run = $this->endedRun($user, $fileId);
        $ids = $this->idsOf($run, $kind);

        $lines = [[
            $this->messages->columnName('id', $user),
            $this->messages->columnName('path', $user),
            ...array_map(static fn (array $field): string => SpreadsheetCell::safe($field['label']), $run->fields),
        ]];
        foreach ($ids as $id) {
            $object = $this->viewable($id, $user);
            if ($object !== null) {
                $lines[] = [
                    $id,
                    SpreadsheetCell::safe($object->getRealFullPath()),
                    ...array_map(
                        static fn (?string $value): string => SpreadsheetCell::safe($value ?? ''),
                        $this->valuesOf($object, $run, $user)
                    ),
                ];
            }
        }

        return CsvText::of($lines);
    }

    private function endedRun(User $user, string $fileId): RunRecord
    {
        $run = $this->runs->find($user->getId(), $fileId);
        if ($run === null || $run->state !== RunRecord::DONE) {
            throw new NotFoundHttpException(sprintf('No ended run of file %s found', $fileId));
        }

        return $run;
    }

    /**
     * @return list<int>
     */
    private function idsOf(RunRecord $run, string $kind): array
    {
        if ($kind !== RowOutcome::UPDATED && $kind !== RowOutcome::CREATED) {
            throw new InvalidArgumentException(sprintf('No list of %s objects: updated or created.', $kind));
        }

        return $run->objectIds($kind);
    }

    // an object deleted since, or one the user may no longer see, is left out
    private function viewable(int $id, User $user): ?Concrete
    {
        $object = $this->objects->getById($id);

        return $object instanceof Concrete && $object->isAllowed('view', $user) ? $object : null;
    }

    /**
     * @return list<string|null> per field the file wrote, its value as the object holds it now
     */
    private function valuesOf(Concrete $object, RunRecord $run, User $user): array
    {
        return array_map(static fn (array $field): ?string => ValueText::of(
            FieldAccess::read($object, $field['key'], $field['language'], StoreKey::fromId($field['storeKey'])),
            $user
        ), $run->fields);
    }
}
