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

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Pimcore\Bundle\DataImporterBundle\Validation\ConfigurationValidationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * MCP tool to validate Data Importer configurations.
 *
 * @internal
 */
final readonly class ValidateConfigurationTool
{
    public function __construct(
        private ConfigurationValidationService $validationService,
        private LoggerInterface $logger
    ) {
    }

    #[McpTool(
        name: 'validate_configuration',
        description: 'Validate a Data Importer configuration in JSON or '
            . 'YAML format. Returns success status and any validation '
            . 'errors. Always validate configurations before saving or '
            . 'using them. IMPORTANT: When using YAML format, settings '
            . 'must be nested YAML structures, NOT JSON strings. '
            . 'Example: "settings:\n  assetPath: /path" NOT '
            . '"settings: \'{\"assetPath\":\"/path\"}\'". '
            . 'Use proper YAML nesting for all configuration nodes '
            . 'including loaderConfig.settings, interpreterConfig.settings, '
            . 'transformationPipeline settings, and dataTarget.settings.'
    )]
    public function execute(
        #[Schema(
            type: 'string',
            description: 'The configuration as JSON or YAML string. '
                . 'For YAML: use nested structures for all settings fields, '
                . 'not JSON strings embedded in YAML.'
        )]
        string $configuration,
        #[Schema(
            type: 'string',
            description: 'Format of the configuration: "json" or "yaml". '
                . 'Auto-detects if not specified.'
        )]
        ?string $format = null
    ): CallToolResult {
        try {
            // Parse configuration based on format
            $configArray = $this->parseConfiguration($configuration, $format);

            $result = $this->validationService
                ->validateConfiguration($configArray);

            if ($result->isValid()) {
                return new CallToolResult(
                    [
                        new TextContent(
                            json_encode(
                                [
                                    'valid' => true,
                                    'message' => 'Configuration is valid'
                                ],
                                JSON_PRETTY_PRINT
                            )
                        )
                    ],
                    isError: false
                );
            }

            $errors = [];
            foreach ($result->getErrors() as $error) {
                $errors[] = [
                    'path' => $error->getPath(),
                    'message' => $error->getMessage()
                ];
            }

            return new CallToolResult(
                [
                    new TextContent(
                        json_encode(
                            ['valid' => false, 'errors' => $errors],
                            JSON_PRETTY_PRINT
                        )
                    )
                ],
                isError: false
            );
        } catch (\Throwable $e) {
            // Log full details server-side for debugging
            $this->logger->error(
                'Unhandled error during tool execution',
                [
                    'name' => 'validate_configuration',
                    'exception' => $e->getMessage(),
                    'type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            );

            // Return sanitized error to client (no paths/traces)
            $errorMessage = $this->sanitizeErrorMessage($e);

            return new CallToolResult(
                [
                    new TextContent(
                        json_encode(
                            [
                                'valid' => false,
                                'error' => $errorMessage
                            ],
                            JSON_PRETTY_PRINT
                        )
                    )
                ],
                isError: true
            );
        }
    }

    /**
     * Parse configuration string to array based on format
     *
     * @throws \Exception if parsing fails
     */
    private function parseConfiguration(string $config, ?string $format): array
    {
        // Auto-detect format if not specified
        if ($format === null) {
            $trimmed = trim($config);
            if ($trimmed[0] === '{' || $trimmed[0] === '[') {
                $format = 'json';
            } else {
                $format = 'yaml';
            }
        }

        $format = strtolower($format);

        if ($format === 'json') {
            $result = json_decode($config, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException(
                    'Invalid JSON: ' . json_last_error_msg()
                );
            }

            return $result;
        }

        if ($format === 'yaml' || $format === 'yml') {
            try {
                $result = Yaml::parse($config);
                if (!is_array($result)) {
                    throw new \RuntimeException(
                        'YAML must parse to an array/object'
                    );
                }

                return $result;
            } catch (\Exception $e) {
                throw new \RuntimeException(
                    'Invalid YAML: ' . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        throw new \InvalidArgumentException(
            "Unsupported format: {$format}. Use 'json' or 'yaml'."
        );
    }

    /**
     * Sanitize error message for client response
     *
     * Removes sensitive information like file paths and internal
     * implementation details while providing actionable feedback.
     */
    private function sanitizeErrorMessage(\Throwable $e): string
    {
        $message = $e->getMessage();

        // Handle common validation errors with helpful messages
        if (str_contains($message, 'must be of type array, string given')) {
            return 'Invalid configuration format: settings fields must ' .
                'be nested YAML structures, not JSON strings. ' .
                'Example: "settings:\\n  assetPath: /path" instead of ' .
                '"settings: \\"{\\\"assetPath\\\":\\\"/path\\\"}\\""';
        }

        if (str_contains($message, 'Settings must be a nested YAML')) {
            return $message;
        }

        if (str_contains($message, 'Invalid JSON in settings field')) {
            return $message;
        }

        // Remove file paths from error messages
        $message = preg_replace(
            '#(/[^\s:]+\.(php|yaml|yml))#',
            '[file]',
            $message
        );

        // Remove line numbers that reference code
        $message = preg_replace(
            '#(on line|at line|line)\s+\d+#i',
            '',
            $message
        );

        // Provide generic fallback for unexpected errors
        if (empty(trim($message))) {
            return 'Configuration validation failed. Please check your ' .
                'configuration structure and ensure all required fields ' .
                'are present with valid values.';
        }

        return $message;
    }
}
