# MCP Server (Experimental)

> **Warning**: This feature is experimental. APIs may change in future releases.

AI agent integration via [Model Context Protocol](https://modelcontextprotocol.io/) for automated configuration creation
and validation.

## Setup

### 1. Enable the MCP Server

```yaml
# config/packages/pimcore_data_importer.yaml
pimcore_data_importer:
    mcp_server:
        enabled: true
```

When `enabled` is `false` (the default), the MCP endpoint and all related services are not loaded.

### 2. Enable the MCP Firewall

Add the `pimcore_mcp` firewall to your `config/packages/security.yaml`. This firewall is provided by the Studio Backend
Bundle and protects all `/pimcore-mcp/` routes (see the
[Studio Backend MCP documentation](https://docs.pimcore.com/platform/Studio_Backend/MCP_Server/) for full details):

```yaml
security:
    firewalls:
        pimcore_mcp: '%pimcore_studio_backend.mcp_firewall_settings%'
        pimcore_studio: '%pimcore_studio_backend.firewall_settings%'
    access_control:
        - { path: ^/pimcore-studio/api/(docs|docs/json|translations|user/reset-password)$, roles: PUBLIC_ACCESS }
        - { path: ^/pimcore-studio/api, roles: ROLE_PIMCORE_USER }
        - { path: ^/pimcore-mcp/, roles: ROLE_PIMCORE_USER }
```

> **Note:** The `pimcore_mcp` firewall must be listed **before** `pimcore_studio` in the firewalls section. Symfony
> evaluates firewalls in order, so placing it first ensures `/pimcore-mcp/` requests are matched by the correct firewall.

### 3. Configure Authentication

Authentication is handled by the unified `pimcore_mcp` firewall provided by Studio Backend Bundle. The firewall supports
two authenticators tried in order:

| Authenticator                | Trigger                  | Use Case                                                  |
|------------------------------|--------------------------|-----------------------------------------------------------|
| `SessionBridgeAuthenticator` | Session cookie           | Internal: agent-server forwarding Pimcore Studio sessions |
| `PatAuthenticator`           | `Authorization: Bearer`  | External: MCP clients (Claude Desktop, Cursor, etc.)      |

The `SessionBridgeAuthenticator` authenticates MCP requests against an existing Pimcore Studio session. When the Pimcore AI
agent-server receives a request from the Studio UI, it forwards the browser's `PHPSESSID` cookie to the MCP endpoint.

For external MCP clients, configure Personal Access Tokens (PATs) in the studio-backend config:

```yaml
# config/packages/pimcore_studio_backend.yaml
pimcore_studio_backend:
    mcp:
        authentication:
            tokens:
                admin:
                    - '%env(MCP_TOKEN_ADMIN)%'
                editor_user:
                    - '%env(MCP_TOKEN_EDITOR)%'
```

Each key is a Pimcore username, and the value is a list of accepted bearer tokens for that user. All Pimcore user
permissions apply automatically.

> **Security**: Store tokens in environment variables or a secrets vault — never commit plain-text tokens to version
> control.

## Endpoint

The MCP server uses the **Streamable HTTP** transport:

```
POST /pimcore-mcp/data-importer
Authorization: Bearer <your-token>
```

## Client Configuration

Configure your MCP client to connect to the endpoint:

```json
{
  "mcpServers": {
    "pimcore-data-importer": {
      "url": "https://your-pimcore.com/pimcore-mcp/data-importer",
      "headers": {
        "Authorization": "Bearer <your-token>"
      }
    }
  }
}
```

## Available Tools

### Discovery & Context (read-only)

| Tool                                                     | Purpose                                                       |
|----------------------------------------------------------|---------------------------------------------------------------|
| `pimcore_dataimporter_list_available_classes`             | List all Pimcore Data Object classes available as targets      |
| `pimcore_dataimporter_get_configuration_context`         | Get schema, operators, data targets, and classes in one call   |
| `pimcore_dataimporter_get_class_fields_for_loading`      | Get filterable fields for relation loading on a specific class |
| `pimcore_dataimporter_get_configuration_examples`        | Get real-world example configurations with explanations        |

### Validation & Enrichment

| Tool                                                     | Purpose                                                       |
|----------------------------------------------------------|---------------------------------------------------------------|
| `pimcore_dataimporter_validate_configuration`            | Validate a JSON/YAML configuration and return any errors      |
| `pimcore_dataimporter_enrich_configuration`              | Calculate and add `transformationResultType` to mapping items  |

### Configuration Management (write)

| Tool                                                     | Purpose                                                       |
|----------------------------------------------------------|---------------------------------------------------------------|
| `pimcore_dataimporter_create_configuration`              | Create a new Data Importer configuration in DataHub            |
| `pimcore_dataimporter_save_configuration`                | Update an existing Data Importer configuration                 |

## Typical AI Agent Workflow

1. **Get context** — available loaders, interpreters, operators, and schema
2. **Get examples** — common configuration patterns for CSV, XML, JSON imports
3. **Get classes** — available Pimcore classes and their fields
4. **Build configuration** — based on user requirements
5. **Enrich types** — auto-calculate transformation result types
6. **Validate** — check for errors before saving
7. **Create or save** — persist the configuration to DataHub

## Important Notes

- **Permissions**: All tools run as the authenticated Pimcore user — workspace ACLs and role permissions apply
- **YAML Format**: Use proper YAML nesting, not JSON embedded in YAML strings
- **Schema**: Only `SchemaAwareInterface` services provide full schema details
- **New configurations**: Are created inactive by default — set `general.active` to `true` to activate

## Troubleshooting

**401 Unauthorized**

Ensure the `Authorization: Bearer <token>` header is present and the token matches one configured in
`pimcore_studio_backend.mcp.authentication.tokens`. The mapped Pimcore user must be active.

**"Settings must be nested YAML"**
```yaml
# Wrong
settings: '{"key": "value"}'

# Correct
settings:
  key: value
```
