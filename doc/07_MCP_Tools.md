---
title: MCP Tools
description: Tools that let AI agents build and maintain Data Importer configurations.
---

# MCP Tools

The Data Importer ships nine MCP (Model Context Protocol) tool handlers. An agent that can call
them can discover the import schema, read and write import configurations, and validate one before
it is stored.

The handlers are ordinary services carrying `#[McpTool]` and `#[Schema]` attributes. They are not
bound to any particular MCP server: this bundle provides the tools, and
[integrating them](#integrating-the-tools-into-an-mcp-server) into a server is a separate step.

## Requirements

The handlers are registered only when [`mcp/sdk`](https://github.com/modelcontextprotocol/php-sdk)
`^0.7` is installed, so an installation without an MCP server is unaffected. The shared MCP tool
boundary they build on lives in `pimcore/studio-backend-bundle` `^2026.3`.

## The tools

All handlers are in the `Pimcore\Bundle\DataImporterBundle\Mcp\Tool` namespace and require the
`plugin_datahub_config` permission. Each returns a single JSON text content block.

MCP tools bypass the Symfony `#[IsGranted]` pipeline the Studio controllers rely on, so every tool
checks that permission itself before doing anything. On top of it, the per configuration Data Hub
rights still apply: reads resolve through a repository that filters by adapter type and the `read`
right, writes go through the Data Hub configuration service, which enforces `create` and
`read`+`update`, and `run_import_config` goes through the same Studio import service the Studio
button uses, which requires `update`. A name that is not a Data Importer configuration is reported
as not found rather than acted on.

| Tool | Handler | Purpose |
|---|---|---|
| `get_import_config_examples` | `GetConfigurationExamplesTool` | Complete working configurations to copy |
| `get_import_config_context` | `GetConfigurationContextTool` | Classes, loaders, interpreters, strategies, operators, targets, schema |
| `list_import_configs` | `ListImportConfigsTool` | Existing configurations, with their target class and active flag |
| `get_import_config` | `GetImportConfigTool` | Read one configuration to modify it |
| `get_class_fields_for_loading` | `GetClassFieldsForLoadingTool` | Filter fields for the Load Data Object operator |
| `validate_import_config` | `ValidateConfigurationTool` | Validate before saving |
| `enrich_import_config` | `EnrichConfigurationTool` | Report the result type each mapping pipeline produces |
| `create_import_config` | `CreateDataImporterConfigTool` | Create a new, empty configuration |
| `save_import_config` | `SaveDataImporterConfigTool` | Replace a configuration's content |
| `run_import_config` | `RunImportConfigTool` | Start the import a configuration describes |
| `get_import_status` | `GetImportStatusTool` | Progress of a running or finished import |

### The order they are called in

```
stage_asset (agent bundle)          read the source file itself, see the actual values
(ask the user)                      which loader the import should use, unless it is an asset
get_import_config_context           field_type_matrix: what the class can actually store,
                                    before any mapping is proposed
get_import_config_examples          copy the closest working configuration
get_import_config_context           classes, loaders, interpreters (default), then
                                    resolver / targets / operators
list_import_configs                 is the name free?
get_class_fields_for_loading        what the class can be looked up by
validate_import_config              fix every error, validate again
create_import_config                make the entry
save_import_config                  write the document
run_import_config                   start the import (writes data objects)
get_import_status                   poll until isRunning is false
```

To change an existing configuration, read it with `get_import_config`, modify, validate and
save.

The first two steps are not tools, and both matter.

Reading the source data is the first. A mapping cannot be derived from field names alone: the
values are what say that `"130.0 kw"` needs a quantity value with a unit, that a year arrives as a
string and needs the numeric operator, or that a column holds a URL to import as an asset. The
agent reads the file directly, not through the loader it is about to configure, because none of
these tools fetches a file over HTTP, SFTP or SQL for inspection. When the source is a Pimcore
asset, the Agent Bundle's `stage_asset` puts the whole file on the local filesystem to read or
grep, which is why an agent that builds import configurations should also be granted
`pimcore-assets-read`. For anything else the agent has to be given the file or a sample of it.

Which data source to configure is the second, and it is a separate question. The file the agent
analysed and the loader the configuration uses are often not the same: a sample sent by the user
may correspond to a nightly SFTP pull, and a staged asset may be a one off while the real import
uses `upload`. The agent asks which loader to configure and for whatever that loader needs rather
than assuming the import reads from wherever the sample came from.

### `get_import_config_context`

| Parameter | Type | Description |
|---|---|---|
| `sections` | array of enum, optional | `classes`, `loaders`, `interpreters`, `resolver`, `targets`, `operators`, `field_type_matrix`, `schema`. Defaults to classes, loaders and interpreters. |
| `classId` | string, optional | Data object class id or name. Required for `field_type_matrix`. |

An unknown section is an error naming the valid ones, rather than a silent fallback to the
defaults. The `schema` section is large; its operator and target catalogues are pointers to the
`operators` and `targets` sections rather than copies of them.

### `enrich_import_config`

Takes a full configuration or a single mapping item and returns only the computed types:

```json
{"types": [{"index": 0, "label": "Article Number", "transformationResultType": "default"}]}
```

This is a diagnostic rather than a step in the sequence. The result type is derived from the
transformation pipeline, and `validate_import_config` derives it the same way, so a configuration
that omits `transformationResultType` validates exactly like one that carries it. Reach for this
tool to find out why a target field is reported as incompatible, or to choose a target that
accepts what a pipeline produces.

`save_import_config` computes the types as well and writes them into the stored configuration, so
a caller never has to supply them. That matters because the Studio mapping editor reads the stored
value to decide which class attributes it offers for a mapping item's target field, while the
importer ignores it and derives the type from the pipeline. A value written by hand is therefore
never checked against anything, which is why the tool replaces it.

### `validate_import_config` and `save_import_config`

Both return `{"valid": false, "errors": [{"path", "message"}]}` for a configuration that does not
validate, as a **successful** tool result rather than an error: a rejected configuration is a
result the agent acts on, not a transport failure. The `path` points at the exact node, for example
`mappingConfig[2].dataTarget`.

`save_import_config` returns `{"saved": true, "name", "modificationDate"}` on success.

### `create_import_config`

Creates an empty entry and fails if the name already exists, so check with `list_import_configs`
first. New configurations are **inactive**: set `general.active` to `true` in the configuration you
save, or the import never runs.

## Permissions

MCP tools bypass the Symfony `#[IsGranted]` and `kernel.exception` pipeline the Studio controllers
rely on, so each handler checks `plugin_datahub_config` itself, against the user the MCP request
authenticated as. That is the same permission the Data Importer Studio controllers require.
`list_import_configs` and `get_import_config` additionally honour the per-configuration read
permission, so an agent only sees configurations its user may read.

## Error results

Every failure is returned as a tool result with `isError: true` and a JSON body of
`{"error": ..., "code": ...}`. The `code` is what an agent branches on:

| `code` | Meaning | Examples |
|---|---|---|
| `permission_denied` | The caller may not do this, and no retry will change that. | Missing `plugin_datahub_config`; no read permission on a configuration. |
| `not_found` | The name or id does not resolve. Re-read it and try again. | Unknown configuration name; class with no loadable fields. |
| `invalid_request` | The call was malformed. | Unknown context section; `field_type_matrix` without `classId`; a configuration that is not parseable. |
| `internal_error` | Something failed inside Pimcore. | Anything else. |

An `internal_error` reads `{"error": "Internal error while executing <tool> (ref: <id>). The cause
was written to the Pimcore application log.", "code": "internal_error"}`. The exception class,
message and stack trace go to the Pimcore log under that same `ref` rather than to the client.

## Tool annotations

Each handler declares a display title and MCP tool annotations. The seven read tools are marked
read-only; `create_import_config` and `save_import_config` are marked as mutating, so an MCP client
that auto-approves read-only tools still asks before a configuration is written.

## Integrating the tools into an MCP server

### Option 1: expose them from your own MCP server

The handlers are autowired services, so any bundle building its own `Mcp\Server` can register them.
Give the SDK a PSR-11 container that resolves the handler classes, then add each handler:

```yaml
# config/services.yaml of your own bundle. A service locator keys each entry by its service id,
# which is the class name the SDK looks up when it resolves the handler.
services:
    my_bundle.mcp.tool_locator: !service_locator
        - '@Pimcore\Bundle\DataImporterBundle\Mcp\Tool\GetConfigurationContextTool'
        - '@Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ValidateConfigurationTool'
```

```php
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Discovery\DocBlockParser;
use Mcp\Capability\Discovery\SchemaGenerator;
use Mcp\Server;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ValidateConfigurationTool;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\ToolInputSchemaNormalizer;

$method = new ReflectionMethod(ValidateConfigurationTool::class, 'execute');
$attribute = $method->getAttributes(McpTool::class)[0]->newInstance();
$schemaGenerator = new SchemaGenerator(new DocBlockParser());

$server = Server::builder()
    ->setServerInfo('My MCP Server', '1.0.0')
    ->setContainer($toolLocator)
    ->addTool(
        handler: [ValidateConfigurationTool::class, 'execute'],
        name: $attribute->name,
        title: $attribute->title,
        description: $attribute->description,
        annotations: $attribute->annotations,
        inputSchema: ToolInputSchemaNormalizer::normalize($schemaGenerator->generate($method)),
    )
    ->build();
```

Two details are easy to miss:

- **`addTool()` does not read the `#[McpTool]` attribute.** Without an explicit `name` the tool is
  registered under the method name, so every handler would be called `execute`. Read the attribute
  by reflection, as above, and it stays the single source of truth.
- **Normalize the generated input schema.** `ToolInputSchemaNormalizer` widens the parameter types
  the SDK infers so an optional parameter actually accepts null, and an object parameter accepts
  `{}`.

The handlers check permissions against the user the request authenticated as, so the server must
run behind a firewall that establishes a Pimcore backend user.

### Option 2: use the Pimcore Agent Bundle

If the [Pimcore Agent Bundle](https://github.com/pimcore/pimcore-agent-bundle) is installed, no
integration work is needed: this bundle registers the handlers with that bundle's
`pimcore.mcp_tool` extension point, which builds the server, applies the schema normalization, and
serves each group under `/pimcore-mcp/agent/<group>`.

| Group | Tools |
|---|---|
| `pimcore-data-importer-read` | `get_import_config_examples`, `get_import_config_context`, `list_import_configs`, `get_import_config`, `get_class_fields_for_loading`, `validate_import_config`, `enrich_import_config`, `get_import_status` |
| `pimcore-data-importer-direct-write` | `create_import_config`, `save_import_config` |
| `pimcore-data-importer-execute` | `run_import_config` |

Attach them to an agent through its `pimcoreMcpServers` list (tool schemas sent with every turn) or
`pimcoreMetaGroups` list (discovered on demand through the meta-tool):

```yaml
pimcoreMcpServers:
  - pimcore-data-importer-read
  - pimcore-data-importer-direct-write
  # so the agent can stage and read the source file it is writing a mapping for
  - pimcore-assets-read
```

Granting only `pimcore-data-importer-read` gives an agent that can explain and validate
configurations without being able to write one.

`pimcore-data-importer-execute` is separate from the write group because running an import and
authoring a configuration are different powers, and either is useful without the other. Grant it
to an agent that should trigger imports it may not edit; withhold it from an agent that maintains
configurations but must never move production data. Running an import creates and updates data
objects, so `run_import_config` is annotated as destructive and open world.

This bundle also contributes a **`data-importer-configuration` skill**, which carries the calling
order, the configuration structure and the rules that decide whether a configuration works. It is
registered automatically when the agent bundle is present, and is added to an agent through its
`skills` list. Nothing needs to be installed for this: the bundle does not depend on the agent
bundle, it only contributes to it when it is there.

To confirm the live registration:

```bash
bin/console pimcore-agent:mcp:list-tools
```

See
[Custom MCP Tools](https://github.com/pimcore/pimcore-agent-bundle/blob/main/docs/06_Extending/01_Custom_MCP_Tools.md)
for the extension contract these groups follow.
