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

use Pimcore\Bundle\DataHubBundle\Proposal\ConfigProposalPolicyInterface;
use Pimcore\Bundle\DataHubBundle\Service\Studio\ConfigurationServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ProposedImportConfiguration;
use function time;

/**
 * Import configurations as a Data Hub proposal kind. propose_import_config keeps its own
 * refusals, so only the subject and the list and read tools ask this policy.
 *
 * @internal
 */
final readonly class ImportConfigPolicy implements ConfigProposalPolicyInterface
{
    public const string CONFIG_TYPE = 'dataImporterDataObject';

    public const string SUBJECT_TYPE = 'data-importer-config';

    /** slot key => the stored sections it carries, in the order the editor shows them */
    public const array SLOTS = [
        'general' => ['general'],
        'dataSource' => ['loaderConfig', 'interpreterConfig'],
        'resolver' => ['resolverConfig'],
        'processing' => ['processingConfig'],
        'execution' => ['executionConfig'],
        'permissions' => ['permissions'],
    ];

    public function __construct(private ConfigurationServiceInterface $configurations)
    {
    }

    public function configType(): string
    {
        return self::CONFIG_TYPE;
    }

    public function subjectType(): string
    {
        return self::SUBJECT_TYPE;
    }

    public function noun(): string
    {
        return 'import configuration';
    }

    public function readToolName(): string
    {
        return 'get_import_config';
    }

    public function proposable(): array
    {
        return ProposedImportConfiguration::SECTIONS;
    }

    public function withheld(): array
    {
        return [];
    }

    public function withheldReason(): string
    {
        return '';
    }

    public function slots(): array
    {
        return self::SLOTS;
    }

    public function problems(array $state, bool $isNew): array
    {
        return [];
    }

    public function summarize(array $configuration): array
    {
        return [];
    }

    public function save(string $name, array $configuration): void
    {
        // the stored modification date is the one the merge just read; passing now defeats
        // the editor's stale-write guard, which a reviewed merge has already answered
        $this->configurations->updateConfiguration($name, $configuration, time());
    }
}
