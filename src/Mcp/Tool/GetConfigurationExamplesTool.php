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

use function array_keys;
use function basename;
use function count;
use function file_get_contents;
use function glob;
use function is_array;
use function is_dir;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Psr\Log\LoggerInterface;
use function sort;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Registered with the Pimcore Agent Bundle's MCP server when that bundle is installed, and
 * usable as a handler in a custom Mcp\Server. See doc/08_MCP_Tools.md.
 */
final readonly class GetConfigurationExamplesTool
{
    use DataImporterToolHelper;

    private const string TOOL_NAME = 'get_import_config_examples';

    private const string EXAMPLES_PATH = __DIR__ . '/../../../doc/examples';

    public function __construct(
        private SecurityServiceInterface $securityService,
        private McpToolErrorHandlerInterface $errorHandler,
        private LoggerInterface $logger,
    ) {
    }

    #[McpTool(
        name: self::TOOL_NAME,
        title: 'Get Import Configuration Examples',
        description: 'Start here. Complete, working Data Importer configurations (CSV, CSV with '
            . 'relations, JSON), each with a summary of the loader, interpreter, target class and '
            . 'operators it uses. Copy the closest one and adapt it: this is the cheapest way to '
            . 'learn the required top level structure. The class ids and field names in the '
            . 'examples are illustrative, so replace them using the classes and field_type_matrix '
            . 'sections of get_import_config_context.',
        // Reads the example files shipped with the bundle.
        annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true, openWorldHint: false)
    )]
    public function execute(): CallToolResult
    {
        $denied = $this->denyIfNotAllowed($this->securityService);
        if ($denied !== null) {
            return $denied;
        }

        try {
            $examples = $this->loadExamples();
        } catch (Throwable $e) {
            return $this->handledError($this->errorHandler, $e, self::TOOL_NAME);
        }

        return $this->successResult(['examples' => $examples]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadExamples(): array
    {
        if (!is_dir(self::EXAMPLES_PATH)) {
            $this->logger->warning('Data Importer example directory is missing', [
                'path' => self::EXAMPLES_PATH,
            ]);

            return [];
        }

        $files = glob(self::EXAMPLES_PATH . '/*.yaml');
        if ($files === false) {
            return [];
        }

        sort($files);

        $examples = [];
        foreach ($files as $file) {
            $config = $this->parseExample($file);
            if ($config === null) {
                continue;
            }

            $examples[] = [
                'name' => basename($file, '.yaml'),
                'description' => $config['general']['description'] ?? '',
                'summary' => $this->summarise($config),
                'configuration' => $config,
            ];
        }

        return $examples;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseExample(string $file): ?array
    {
        $content = file_get_contents($file);
        if ($content === false) {
            $this->logger->warning('Failed to read Data Importer example', ['file' => $file]);

            return null;
        }

        try {
            $config = Yaml::parse($content);
        } catch (Throwable $e) {
            $this->logger->warning('Failed to parse Data Importer example', [
                'file' => $file,
                'exception' => $e,
            ]);

            return null;
        }

        return is_array($config) ? $config : null;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function summarise(array $config): array
    {
        $operators = [];
        foreach (($config['mappingConfig'] ?? []) as $mapping) {
            foreach (($mapping['transformationPipeline'] ?? []) as $operator) {
                $type = $operator['type'] ?? 'unknown';
                $operators[$type] = ($operators[$type] ?? 0) + 1;
            }
        }

        return [
            'loaderType' => $config['loaderConfig']['type'] ?? 'unknown',
            'interpreterType' => $config['interpreterConfig']['type'] ?? 'unknown',
            'targetClass' => $config['resolverConfig']['dataObjectClassId'] ?? 'unknown',
            'mappingCount' => count($config['mappingConfig'] ?? []),
            'usedOperators' => array_keys($operators),
        ];
    }
}
