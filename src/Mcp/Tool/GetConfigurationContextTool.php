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

use function array_diff;
use function array_map;
use function implode;
use function is_array;
use function is_string;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;
use Pimcore\Bundle\DataImporterBundle\Validation\Schema\ConfigurationSchemaService;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Exception\InvalidMcpToolArgumentException;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use function sprintf;
use Throwable;

/**
 * Registered with the Pimcore Agent Bundle's MCP server when that bundle is installed, and
 * usable as a handler in a custom Mcp\Server. See doc/08_MCP_Tools.md.
 */
final readonly class GetConfigurationContextTool
{
    use DataImporterToolHelper;

    private const string TOOL_NAME = 'get_import_config_context';

    private const string SECTION_CLASSES = 'classes';

    private const string SECTION_LOADERS = 'loaders';

    private const string SECTION_INTERPRETERS = 'interpreters';

    private const string SECTION_RESOLVER = 'resolver';

    private const string SECTION_OPERATORS = 'operators';

    private const string SECTION_TARGETS = 'targets';

    private const string SECTION_FIELD_MATRIX = 'field_type_matrix';

    private const string SECTION_SCHEMA = 'schema';

    /** @var list<string> */
    private const array VALID_SECTIONS = [
        self::SECTION_CLASSES,
        self::SECTION_LOADERS,
        self::SECTION_INTERPRETERS,
        self::SECTION_RESOLVER,
        self::SECTION_OPERATORS,
        self::SECTION_TARGETS,
        self::SECTION_FIELD_MATRIX,
        self::SECTION_SCHEMA,
    ];

    /**
     * Cheap orientation: what to import into and where the data comes from. Everything that
     * costs real context, above all the operator catalogue and the full schema, is opt in.
     *
     * @var list<string>
     */
    private const array DEFAULT_SECTIONS = [
        self::SECTION_CLASSES,
        self::SECTION_LOADERS,
        self::SECTION_INTERPRETERS,
    ];

    public function __construct(
        private ConfigurationSchemaService $configurationSchemaService,
        private SecurityServiceInterface $securityService,
        private McpToolErrorHandlerInterface $errorHandler,
    ) {
    }

    #[McpTool(
        name: self::TOOL_NAME,
        title: 'Get Import Configuration Context',
        description: 'Reference data for building a Data Importer configuration. Start with '
            . 'get_import_config_examples for the overall shape, then call this for detail. '
            . 'Sections: classes (import targets and their ids), loaders (where data comes from), '
            . 'interpreters (file formats), resolver (loading, location and publishing strategies), '
            . 'targets (dataTarget types), operators (transformation operators, with the input and '
            . 'output types you chain them by), field_type_matrix (requires classId: which field '
            . 'accepts which result type), schema (the full JSON schema, large, use only when a '
            . 'validation error is otherwise unexplainable). Defaults to classes, loaders and '
            . 'interpreters.',
        // Pure lookup over the class and service definitions.
        annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true, openWorldHint: false)
    )]
    public function execute(
        #[Schema(
            type: 'array',
            description: 'Sections to return. Omit for classes, loaders and interpreters.',
            items: ['type' => 'string', 'enum' => self::VALID_SECTIONS],
        )]
        ?array $sections = null,
        #[Schema(
            type: 'string',
            description: 'Data object class id or name. Required for the field_type_matrix section.'
        )]
        ?string $classId = null,
    ): CallToolResult {
        $denied = $this->denyIfNotAllowed($this->securityService);
        if ($denied !== null) {
            return $denied;
        }

        try {
            $requested = $this->resolveSections($sections);
            $context = [];

            foreach ($requested as $section) {
                $context[$section] = $this->buildSection($section, $classId);
            }
        } catch (Throwable $e) {
            return $this->handledError($this->errorHandler, $e, self::TOOL_NAME, ['classId' => $classId]);
        }

        return $this->successResult($context);
    }

    /**
     * @param list<mixed>|null $sections
     *
     * @return list<string>
     *
     * @throws InvalidMcpToolArgumentException
     */
    private function resolveSections(?array $sections): array
    {
        if ($sections === null || $sections === []) {
            return self::DEFAULT_SECTIONS;
        }

        $names = array_map(
            static fn (mixed $section): string => is_string($section) ? $section : '',
            $sections
        );

        // Silently dropping an unknown section used to serve the full default payload as if
        // nothing had happened, so a typo cost thousands of tokens and produced no signal.
        $unknown = array_diff($names, self::VALID_SECTIONS);
        if ($unknown !== []) {
            throw new InvalidMcpToolArgumentException(sprintf(
                'Unknown section(s): %s. Valid sections: %s.',
                implode(', ', $unknown),
                implode(', ', self::VALID_SECTIONS)
            ));
        }

        return $names;
    }

    /**
     * @throws InvalidMcpToolArgumentException
     *
     * @return array<array-key, mixed>
     */
    private function buildSection(string $section, ?string $classId): array
    {
        return match ($section) {
            self::SECTION_CLASSES => $this->configurationSchemaService->getAvailableClasses(),
            self::SECTION_LOADERS => $this->availableTypes(
                $this->configurationSchemaService->getLoaderConfigSchema()
            ),
            self::SECTION_INTERPRETERS => $this->availableTypes(
                $this->configurationSchemaService->getInterpreterConfigSchema()
            ),
            self::SECTION_RESOLVER => $this->configurationSchemaService->getResolverConfigSchema(),
            self::SECTION_OPERATORS => $this->fromMappingSchema(
                ['transformationPipeline', 'availableOperators']
            ),
            self::SECTION_TARGETS => $this->fromMappingSchema(['dataTarget', 'availableTargets']),
            self::SECTION_FIELD_MATRIX => $this->fieldTypeMatrix($classId),
            self::SECTION_SCHEMA => $this->slimSchema(),
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<array-key, mixed>
     */
    private function availableTypes(array $schema): array
    {
        $types = $schema['availableTypes'] ?? $schema['properties']['type']['availableTypes'] ?? [];

        return is_array($types) ? $types : [];
    }

    /**
     * @param list<string> $path
     *
     * @return array<array-key, mixed>
     */
    private function fromMappingSchema(array $path): array
    {
        $node = $this->configurationSchemaService->getMappingConfigSchema()['items']['properties'] ?? null;
        foreach ($path as $key) {
            if (!is_array($node) || !isset($node[$key])) {
                return [];
            }

            $node = $node[$key];
        }

        return is_array($node) ? $node : [];
    }

    /**
     * @throws InvalidMcpToolArgumentException
     *
     * @return array<array-key, mixed>
     */
    private function fieldTypeMatrix(?string $classId): array
    {
        if ($classId === null || $classId === '') {
            // Returning [] here was indistinguishable from a class with no fields, and flipped
            // the response from an object to an array.
            throw new InvalidMcpToolArgumentException(
                'The field_type_matrix section requires a classId.'
            );
        }

        return $this->configurationSchemaService->getFieldTypeMatrix($classId);
    }

    /**
     * The complete schema embeds the operator and target catalogues verbatim, which are also
     * their own sections. Requesting both charged for them twice, and the catalogues are the
     * bulk of it.
     *
     * @return array<array-key, mixed>
     */
    private function slimSchema(): array
    {
        $schema = $this->configurationSchemaService->getCompleteSchema();

        if (isset($schema['mappingConfig']['items']['properties']['transformationPipeline']['availableOperators'])) {
            $schema['mappingConfig']['items']['properties']['transformationPipeline']['availableOperators'] =
                'See the "operators" section of this tool.';
        }

        if (isset($schema['mappingConfig']['items']['properties']['dataTarget']['availableTargets'])) {
            $schema['mappingConfig']['items']['properties']['dataTarget']['availableTargets'] =
                'See the "targets" section of this tool.';
        }

        return $schema;
    }
}
