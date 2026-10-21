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

use function count;
use Pimcore\Bundle\ChangeControlBundle\ChangeSet\MappedParameter\ListChangeSetsParameters;
use Pimcore\Bundle\ChangeControlBundle\ChangeSet\Service\ChangeSetServiceInterface;
use Pimcore\Bundle\ChangeControlBundle\Contract\ChangeControlAuthoringInterface;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScope;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScopeResolverInterface;
use Symfony\Component\Uid\Uuid;

/**
 * A scope counts only as a change set the review lists for this configuration: the listing
 * already asks the change set's visibility in the context and the subject's own read check.
 *
 * @internal
 */
final readonly class ChangeSetPreviewScopeResolver implements PreviewScopeResolverInterface
{
    public function __construct(
        private ChangeSetServiceInterface $changeSets,
        private ChangeControlAuthoringInterface $authoring,
    ) {
    }

    public function proposal(string $configName, PreviewScope $scope): ?array
    {
        if (!Uuid::isValid($scope->changeSetId)) {
            return null;
        }

        $changeSet = Uuid::fromString($scope->changeSetId);
        if (!$this->isListed($configName, $changeSet, $scope->contextRef)) {
            return null;
        }

        foreach ($this->authoring->subjectsOf($changeSet, $scope->contextRef) as $subject) {
            if ($subject->subjectType !== ImportConfigPolicy::SUBJECT_TYPE || $subject->ref !== $configName) {
                continue;
            }
            $states = $this->authoring->subjectStatesOf($changeSet, [$subject], $scope->contextRef);

            return $states[$subject->key()]['proposed'] ?? null;
        }

        return null;
    }

    private function isListed(string $configName, Uuid $changeSet, ?string $contextRef): bool
    {
        $page = 1;
        do {
            $items = $this->changeSets->list(new ListChangeSetsParameters(
                ImportConfigPolicy::SUBJECT_TYPE,
                $configName,
                $contextRef,
                $page++,
                ListChangeSetsParameters::MAX_PAGE_SIZE,
            ));
            foreach ($items as $item) {
                if (Uuid::isValid($item->getId()) && Uuid::fromString($item->getId())->equals($changeSet)) {
                    return true;
                }
            }
        } while (count($items) === ListChangeSetsParameters::MAX_PAGE_SIZE);

        return false;
    }
}
