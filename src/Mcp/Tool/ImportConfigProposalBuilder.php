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

namespace Pimcore\Bundle\DataImporterBundle\Mcp\Tool;

use function array_key_exists;
use function implode;
use function is_array;
use function json_decode;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataHubBundle\Proposal\ConfigDocument;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigPolicy;
use function sprintf;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * The state propose_import_config records for an agent's document, or a refusal saying what to
 * send instead. Writes nothing: the state only rides a change set.
 *
 * @internal
 */
final readonly class ImportConfigProposalBuilder
{
    public function __construct(private ImportConfigVocabulary $vocabulary)
    {
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ProposalRefusedException
     */
    public function build(string $name, array|string $configuration, ?Configuration $existing): array
    {
        if ($existing === null && !ProposedImportConfiguration::isValidName($name)) {
            throw new ProposalRefusedException(sprintf(
                '"%s" cannot name a configuration: use letters, digits, "-" and "_", '
                . 'starting with a letter or digit.',
                $name,
            ));
        }

        // a name nothing is stored under creates: the document is the whole configuration
        $stored = $existing?->getConfiguration() ?? [];
        $state = ProposedImportConfiguration::fold($stored, $this->readSections($configuration, $stored));

        $this->assertRecordable($state, $stored, $existing);

        return $this->withSubjectIdentity($state, $name, $existing);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(array|string $configuration): ?array
    {
        if (is_array($configuration)) {
            return $configuration;
        }

        $decoded = json_decode($configuration, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        try {
            $parsed = Yaml::parse($configuration);
        } catch (Throwable) {
            return null;
        }

        return is_array($parsed) ? $parsed : null;
    }

    /**
     * The document as it would be recorded, or a refusal saying what to send instead.
     *
     * @throws ProposalRefusedException
     */

    /**
     * @throws ProposalRefusedException
     */
    private function readSections(array|string $configuration, array $stored): array
    {
        $proposed = $this->decode($configuration);
        if ($proposed === null) {
            throw new ProposalRefusedException(
                'The configuration must be an object, or a JSON or YAML object string.'
            );
        }

        $unknown = ProposedImportConfiguration::unknownSections($proposed, $stored);
        if ($unknown !== []) {
            throw new ProposalRefusedException(sprintf(
                '%s is not part of an import configuration. Read the current document with '
                . 'get_import_config and send that back with your changes applied, rather than '
                . 'writing one from memory.',
                implode(', ', $unknown),
            ));
        }

        // a field the editor binds nothing to reviews as a change and applies as nothing
        $invented = ProposedImportConfiguration::unknownPaths($proposed, $stored);
        if ($invented !== []) {
            throw new ProposalRefusedException(sprintf(
                '%s is not a field of an import configuration. Read the current document with '
                . 'get_import_config and change the keys it already has, rather than spelling '
                . 'one from memory.',
                implode(', ', $invented),
            ));
        }

        if (array_key_exists('mappingConfig', $proposed)) {
            $mappings = ProposedImportConfiguration::mappingList($proposed['mappingConfig']);
            if ($mappings === null) {
                throw new ProposalRefusedException(
                    'mappingConfig must be a list of mapping entries, one per column.'
                );
            }
            $proposed['mappingConfig'] = $mappings;
        }

        return $proposed;
    }

    /**
     * @throws ProposalRefusedException
     */
    private function assertRecordable(array $state, array $stored, ?Configuration $existing): void
    {
        // a select can only hold one of its options; a proposal must not hold more
        $unknownValues = ProposedImportConfiguration::unknownValues($state, $this->vocabulary->all());
        if ($unknownValues !== []) {
            throw new ProposalRefusedException(implode("\n", $unknownValues));
        }

        // a change the import would ignore is not worth a reviewer's turn; a create is
        // reviewed whole, and a copy legitimately carries the inert values its source has
        if ($existing === null) {
            return;
        }

        $ineffective = ProposedImportConfiguration::ineffectiveChanges($stored, $state);
        if ($ineffective !== []) {
            throw new ProposalRefusedException(implode("\n", $ineffective));
        }
    }

    /**
     * @throws ProposalRefusedException
     */
    private function withSubjectIdentity(array $state, string $name, ?Configuration $existing): array
    {
        // identity and adapter type belong to the subject, never to a proposal
        $state['general']['name'] = $name;
        $state['general']['type'] = $existing?->getType() ?? ImportConfigPolicy::CONFIG_TYPE;

        if ($existing === null) {
            $missing = ProposedImportConfiguration::missingForCreate($state);
            if ($missing !== []) {
                throw new ProposalRefusedException(sprintf(
                    'A new configuration needs %s. Read a similar one with get_import_config and send '
                    . 'the complete document under the new name.',
                    implode(', ', $missing),
                ));
            }
            // a pipeline nobody switched on must not start running because it was reviewed
            $state['general']['active'] ??= false;
            $state['general']['path'] ??= '';
        }

        // the subject strips these from its own state; proposing them back adds leaves
        // to the review that name a change nobody made
        foreach (ConfigDocument::VOLATILE_GENERAL as $volatile) {
            unset($state['general'][$volatile]);
        }

        return $state;
    }
}
