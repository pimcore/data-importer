---
title: MCP Tools
description: The import configuration MCP tools of the bundle, and which of them a Studio-managed MCP server can offer.
---

# MCP Tools

The Data Importer contributes MCP tools to the Pimcore Agent that read import configurations and propose changes to
them.

The tools are registered only when the Change Control bundle and the Pimcore Agent bundle are installed and enabled.
They reach a caller in one of two ways:

- **Agent chat:** every tool below, in the Pimcore Agent's tool groups `pimcore-data-importer-read` and
  `pimcore-data-importer-propose`.
- **Studio-managed MCP server:** only the tools tagged `pimcore.studio_backend.mcp_tool`. They are listed in the Studio
  tool catalogue (`GET /pimcore-studio/api/mcp/tools`), and an administrator can assign them to a configured MCP server
  (`/pimcore-mcp/studio/<slug>`) that external MCP clients connect to. Such a client carries no agent chat session; a
  proposal it makes is stored in the calling user's session on that server.

| Tool | Purpose | Read / write | Propose / direct-write | Studio-managed server |
|---|---|---|---|---|
| `list_import_configs` | List the import configurations the user may read, with group, description and whether each is active. | read | – | yes |
| `get_import_config` | Read one import configuration in full, as the document a proposal sends back. | read | – | yes |
| `list_config_principals` | List the role and user names for the permissions section of a configuration. | read | – | yes |
| `propose_import_config` | Propose changes to an import configuration, or a new one, for review in a change set. | write | propose | yes |

- **Read / write:** a read tool is annotated `readOnlyHint: true`, so a Studio-managed server offers it under the OAuth
  scope `mcp:read`; the propose tool is offered under `mcp:write`.
- **Propose / direct-write:** no tool of this bundle writes a configuration directly. `propose_import_config` records
  the configuration as a proposal in a change set; it is applied only once a user approves it. From the agent chat
  the proposal is stored in the chat session and reviewed on its card; from a Studio-managed server it is stored in the
  calling user's session on that server and reviewed in Studio's change requests.
- **Permissions:** every tool runs as the calling user. It needs the `plugin_datahub_config` permission and, for a
  single configuration, read access to it (`list_import_configs` lists only those).
