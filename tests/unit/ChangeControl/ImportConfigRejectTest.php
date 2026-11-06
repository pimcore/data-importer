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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\ChangeControl;

use Codeception\Test\Unit;
use Pimcore\Bundle\ChangeControlBundle\Merge\MergeSaveMode;
use Pimcore\Bundle\ChangeControlBundle\Subject\Exception\SubjectAccessDeniedException;
use Pimcore\Bundle\ChangeControlBundle\Subject\SubjectRef;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataHubBundle\Proposal\ChangeControl\ConfigSubjectHandler;
use Pimcore\Bundle\DataHubBundle\Proposal\StoredConfigurationsInterface;
use Pimcore\Bundle\DataHubBundle\Service\AdapterAvailabilityServiceInterface;
use Pimcore\Bundle\DataHubBundle\Service\Studio\ConfigurationServiceInterface;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigPolicy;
use Pimcore\Model\User;
use Symfony\Component\Uid\Uuid;

class ImportConfigRejectTest extends Unit
{
    protected function _before(): void
    {
        // Change Control is an optional integration this repository cannot install, and the
        // subject handler comes from a Data Hub proposal lane the data-hub constraint admits no release of yet
        if (!class_exists(SubjectRef::class) || !class_exists(ConfigSubjectHandler::class)) {
            static::markTestSkipped('Change Control bundle or Data Hub proposal lane not installed');
        }
    }

    public function testRejectingAProposalLeavesTheStoredConfigurationUnchanged(): void
    {
        $configuration = [
            'general' => ['name' => 'car-import', 'type' => ImportConfigPolicy::CONFIG_TYPE],
            'interpreterConfig' => ['type' => 'csv'],
        ];
        $stored = $this->createMock(Configuration::class);
        $stored->method('getType')->willReturn(ImportConfigPolicy::CONFIG_TYPE);
        $stored->method('getConfiguration')->willReturn($configuration);
        $stored->method('isAllowed')->willReturn(true);
        // neither the configuration nor its modification date is written
        foreach (['save', 'persist', 'delete', 'setConfiguration', 'setModificationDate'] as $write) {
            $stored->expects($this->never())->method($write);
        }

        $configurations = $this->createMock(ConfigurationServiceInterface::class);
        foreach (['addConfiguration', 'updateConfiguration', 'deleteConfiguration'] as $write) {
            $configurations->expects($this->never())->method($write);
        }

        $handler = $this->handler($stored, $configurations);
        $subject = new SubjectRef(ImportConfigPolicy::SUBJECT_TYPE, Uuid::v4(), 'car-import');
        $user = new User();
        $user->setAdmin(true);

        // what Change Control asks the subject's handler when a reviewer rejects (discards) a change set
        $handler->authorizeRead($subject, $user);
        $refused = false;

        try {
            $handler->authorize($subject, $user, MergeSaveMode::Save);
        } catch (SubjectAccessDeniedException) {
            $refused = true;
        }
        $handler->authorize($subject, $user, MergeSaveMode::Publish);
        $state = $handler->readState($subject);

        static::assertTrue($refused);
        static::assertSame('csv', $state['interpreterConfig']['type']);
    }

    private function handler(Configuration $stored, ConfigurationServiceInterface $configurations): ConfigSubjectHandler
    {
        $storedConfigurations = $this->createStub(StoredConfigurationsInterface::class);
        $storedConfigurations->method('find')->willReturn($stored);
        $adapters = $this->createStub(AdapterAvailabilityServiceInterface::class);
        $adapters->method('isEnabled')->willReturn(true);

        return new ConfigSubjectHandler(
            new ImportConfigPolicy($configurations),
            $configurations,
            $storedConfigurations,
            $adapters,
        );
    }
}
