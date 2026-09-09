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

use Pimcore\Bundle\ChangeControlBundle\Merge\Exception\UnsupportedMergeSaveModeException;
use Pimcore\Bundle\ChangeControlBundle\Merge\MergeSaveMode;
use Pimcore\Bundle\ChangeControlBundle\Merge\StateShapeInterface;
use Pimcore\Bundle\ChangeControlBundle\Merge\StructuralShape;
use Pimcore\Bundle\ChangeControlBundle\Subject\Exception\SubjectAccessDeniedException;
use Pimcore\Bundle\ChangeControlBundle\Subject\SubjectHandlerInterface;
use Pimcore\Bundle\ChangeControlBundle\Subject\SubjectRef;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataHubBundle\Service\Studio\ConfigurationServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Model\User;
use Pimcore\Model\UserInterface;
use function sprintf;
use Throwable;
use function time;

/**
 * An import configuration as a change-control subject: the stored configuration document is
 * the state, {@see ConfigurationServiceInterface::updateConfiguration()} lands it. The ref is
 * the configuration name, which is also its identity in storage.
 *
 * Registered only when the Change Control bundle is installed.
 *
 * @internal
 */
final readonly class ImportConfigSubjectHandler implements SubjectHandlerInterface
{
    public const string TYPE = 'data-importer-config';

    /** the adapter type this bundle registers with the Data Hub */
    private const string CONFIG_TYPE = 'dataImporterDataObject';

    /**
     * Bookkeeping the editor never binds and a draft must never carry: it changes on every
     * save, so a draft holding it would report a change nobody made.
     */
    private const array VOLATILE_GENERAL = ['modificationDate', 'createDate', 'creationDate', 'writeable'];

    public function __construct(
        private ConfigurationServiceInterface $configurations,
    ) {
    }

    public function outOfDraftScope(): array
    {
        // the volatile keys are nested under `general`, so they are stripped in readState
        // rather than declared here, which filters top-level keys only
        return [];
    }

    public function shape(SubjectRef $subject): StateShapeInterface
    {
        return new StructuralShape();
    }

    public function readState(SubjectRef $subject): array
    {
        $configuration = $this->load($subject->ref);
        if ($configuration === null) {
            // no configuration yet: the create lane has no base and merges last-write-wins
            return [];
        }

        $state = $configuration->getConfiguration();

        foreach (self::VOLATILE_GENERAL as $key) {
            unset($state['general'][$key]);
        }

        return $state;
    }

    /**
     * Reviewing shows the configuration's current values, so it is gated exactly like opening
     * it in the Data Hub.
     *
     * @throws SubjectAccessDeniedException
     */
    public function authorizeRead(SubjectRef $subject, UserInterface $user): void
    {
        $this->assertConfigPermission($subject, $user);
    }

    public function authorize(SubjectRef $subject, UserInterface $user, MergeSaveMode $mode): void
    {
        // a configuration is live the moment it is written, so a mode this cannot land is
        // refused here rather than at apply — the review meta probes with each mode
        if ($mode !== MergeSaveMode::Publish) {
            throw SubjectAccessDeniedException::forRef(self::TYPE, $subject->ref, MergeSaveMode::Publish->requiredPermission());
        }

        $this->assertConfigPermission($subject, $user);
    }

    public function applyMerged(SubjectRef $subject, array $finalData, UserInterface $mergedBy, MergeSaveMode $mode): array
    {
        if ($mode !== MergeSaveMode::Publish) {
            throw UnsupportedMergeSaveModeException::by(self::class, $mode);
        }

        $name = $subject->ref;

        if ($finalData === []) {
            // an empty merged state would be a removal; a configuration is never deleted through a merge
            throw new \LogicException(sprintf('Refusing to apply an empty state to import configuration "%s".', $name));
        }

        $existing = $this->load($name);
        if ($existing === null) {
            $this->configurations->addConfiguration(
                $name,
                self::CONFIG_TYPE,
                (string) ($finalData['general']['path'] ?? '')
            );
        }

        $configuration = $finalData;
        // identity and adapter type are the subject's, not the draft's — an existing
        // configuration keeps whatever type it was created with
        $configuration['general']['name'] = $name;
        $configuration['general']['type'] = $existing?->getType() ?? self::CONFIG_TYPE;

        // the stored modification date is the one the merge just read; passing now defeats
        // the editor's stale-write guard, which a reviewed merge has already answered
        $this->configurations->updateConfiguration($name, $configuration, time());

        return [];
    }

    private function load(string $name): ?Configuration
    {
        try {
            return Configuration::getByName($name);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @throws SubjectAccessDeniedException
     */
    private function assertConfigPermission(SubjectRef $subject, UserInterface $user): void
    {
        // isAllowed() wants the concrete user model; an actor this cannot verify is denied
        if (!$user instanceof User || !$user->isAllowed(PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG)) {
            throw SubjectAccessDeniedException::forRef(
                self::TYPE,
                $subject->ref,
                PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG
            );
        }
    }
}
