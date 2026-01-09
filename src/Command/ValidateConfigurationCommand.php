<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Command;

use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\Validation\ConfigurationValidationService;
use Pimcore\Bundle\DataImporterBundle\Validation\Schema\ConfigurationSchemaService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command for validating and inspecting data importer configurations
 *
 * Usage:
 * - bin/console datahub:data-importer:validate-config <config-name>
 * - bin/console datahub:data-importer:validate-config --schema
 * - bin/console datahub:data-importer:validate-config --schema-section=loaderConfig
 */
class ValidateConfigurationCommand extends Command
{
    private const AVAILABLE_SECTIONS = [
        'general',
        'loaderConfig',
        'interpreterConfig',
        'resolverConfig',
        'processingConfig',
        'mappingConfig',
        'executionConfig',
    ];

    protected ConfigurationValidationService $validationService;

    protected ConfigurationSchemaService $schemaService;

    public function __construct(
        ConfigurationValidationService $validationService,
        ConfigurationSchemaService $schemaService
    ) {
        parent::__construct('datahub:data-importer:validate-config');
        $this->validationService = $validationService;
        $this->schemaService = $schemaService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Validate data importer configuration or show schema information')
            ->addArgument('config-name', InputArgument::OPTIONAL, 'Name of the configuration to validate')
            ->addOption('schema', null, InputOption::VALUE_NONE, 'Show complete configuration schema')
            ->addOption(
                'schema-section',
                null,
                InputOption::VALUE_REQUIRED,
                'Show schema for specific section (' .
                'loaderConfig, interpreterConfig, etc.)'
            )
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $configName = $input->getArgument('config-name');
        $showSchema = $input->getOption('schema');
        $schemaSection = $input->getOption('schema-section');
        $jsonOutput = $input->getOption('json');

        // Show schema information
        if ($showSchema || $schemaSection) {
            return $this->showSchema($io, $schemaSection, $jsonOutput);
        }

        // Validate configuration
        if (!$configName) {
            $io->error('Please provide a configuration name or use --schema option');

            return Command::FAILURE;
        }

        return $this->validateConfiguration($io, $configName, $jsonOutput);
    }

    protected function showSchema(SymfonyStyle $io, ?string $section, bool $jsonOutput): int
    {
        if ($section) {
            $method = 'get' . ucfirst($section) . 'Schema';
            if (!method_exists($this->schemaService, $method)) {
                $io->error("Unknown schema section: $section");
                $this->renderAvailableSections($io);

                return Command::FAILURE;
            }

            $schema = $this->schemaService->$method();
        } else {
            $schema = $this->schemaService->getCompleteSchema();
        }

        if ($jsonOutput) {
            $output = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $io->writeln($output);
        } else {
            $this->displaySchema($io, $schema, $section ?: 'Complete Configuration');
        }

        return Command::SUCCESS;
    }

    protected function validateConfiguration(SymfonyStyle $io, string $configName, bool $jsonOutput): int
    {
        $io->title("Validating configuration: $configName");

        $configuration = Configuration::getByName($configName);
        if (!$configuration) {
            $io->error("Configuration '$configName' not found");

            return Command::FAILURE;
        }

        $config = $configuration->getConfiguration();
        $result = $this->validationService->validateConfiguration($config);

        if ($jsonOutput) {
            $output = json_encode($result->toArray(), JSON_PRETTY_PRINT);
            $io->writeln($output);
        } else {
            $this->renderValidationResult($io, $result);
        }

        return $result->isValid() ? Command::SUCCESS : Command::FAILURE;
    }

    protected function displaySchema(SymfonyStyle $io, array $schema, string $title): void
    {
        $io->title($title);

        if (isset($schema['description'])) {
            $io->text($schema['description']);
            $io->newLine();
        }

        if (isset($schema['availableTypes'])) {
            $this->renderAvailableTypes($io, $schema['availableTypes']);
        }

        if (isset($schema['properties']) && !isset($schema['availableTypes'])) {
            $this->renderProperties($io, $schema['properties']);
        }
    }

    private function renderAvailableSections(SymfonyStyle $io): void
    {
        $io->note('Available sections: ' . implode(', ', self::AVAILABLE_SECTIONS));
    }

    private function renderAvailableTypes(SymfonyStyle $io, array $availableTypes): void
    {
        $io->section('Available Types:');
        foreach ($availableTypes as $type => $info) {
            $io->writeln("  <info>$type</info>");
            if (isset($info['description'])) {
                $io->writeln('    ' . $info['description']);
            }
            $this->renderTypeSettings($io, $info['settings'] ?? []);
            $io->newLine();
        }
    }

    private function renderProperties(SymfonyStyle $io, array $properties): void
    {
        $io->section('Properties:');
        foreach ($properties as $propName => $propInfo) {
            $required = $propInfo['required'] ?? false;
            $requiredLabel = $required ? '<fg=red>*</>' : ' ';
            $type = $propInfo['type'] ?? 'unknown';
            $io->writeln("  $requiredLabel <info>$propName</info> ($type)");
            $this->renderPropertyDetails($io, $propInfo);
            $io->newLine();
        }
    }

    private function renderPropertyDetails(SymfonyStyle $io, array $propInfo): void
    {
        if (isset($propInfo['description'])) {
            $io->writeln('    ' . $propInfo['description']);
        }

        if (isset($propInfo['enum'])) {
            $io->writeln('    Allowed values: ' . implode(', ', $propInfo['enum']));
        }

        if (!isset($propInfo['default'])) {
            return;
        }

        $defaultValue = $propInfo['default'];
        if (is_bool($defaultValue)) {
            $defaultValue = $defaultValue ? 'true' : 'false';
        }

        $io->writeln("    Default: $defaultValue");
    }

    private function renderValidationResult(SymfonyStyle $io, object $result): void
    {
        if ($result->isValid()) {
            $io->success('Configuration is valid');

            return;
        }

        $io->error('Configuration has errors');
        $this->renderErrors($io, $result);
        $this->renderWarnings($io, $result);
    }

    private function renderErrors(SymfonyStyle $io, object $result): void
    {
        if (!$result->hasErrors()) {
            return;
        }

        $io->section('Errors:');
        foreach ($result->getErrors() as $error) {
            $io->writeln("  • {$error->getPath()}: {$error->getMessage()}");
        }
    }

    private function renderWarnings(SymfonyStyle $io, object $result): void
    {
        if (!$result->hasWarnings()) {
            return;
        }

        $io->section('Warnings:');
        foreach ($result->getWarnings() as $warning) {
            $io->writeln("  • {$warning->getPath()}: {$warning->getMessage()}");
        }
    }

    private function renderTypeSettings(SymfonyStyle $io, array $settings): void
    {
        if (empty($settings)) {
            return;
        }

        $io->writeln('    Settings:');
        foreach ($settings as $settingName => $settingInfo) {
            $required = $settingInfo['required'] ?? false;
            $requiredLabel = $required ? '<fg=red>*</>' : ' ';
            $typeLabel = $settingInfo['type'] ?? 'unknown';
            $io->writeln("      $requiredLabel $settingName ($typeLabel)");
            if (isset($settingInfo['description'])) {
                $io->writeln('        ' . $settingInfo['description']);
            }
        }
    }
}
