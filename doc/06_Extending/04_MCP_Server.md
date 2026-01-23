# MCP Server (Experimental)

> **⚠️ EXPERIMENTAL**: APIs may change in future releases.

AI agent integration via [Model Context Protocol](https://modelcontextprotocol.io/) for automated configuration creation and validation.

## Endpoint

```
POST /dataimporter-mcp
Authorization: Bearer your-api-token
```

## Available Tools

| Tool | Purpose |
|------|---------|
| `validate_configuration` | Validate JSON/YAML configuration |
| `list_available_classes` | Get Pimcore class definitions |
| `get_configuration_context` | Get available loaders, operators, schema |
| `get_class_fields_for_loading` | Get filterable fields for relation loading |
| `get_configuration_examples` | Get example configurations |
| `enrich_configuration_with_transformation_result_types` | Auto-calculate transformation types |

## Typical AI Agent Workflow

1. Get context → available loaders, interpreters, operators
2. Get examples → common configuration patterns
3. Get classes → available Pimcore classes and fields
4. Build configuration → based on user requirements
5. Enrich types → auto-calculate transformation result types
6. Validate → before returning to user

## Authentication Setup

Configure in your MCP client:

```json
{
  "mcpServers": {
    "pimcore-data-importer": {
      "url": "https://your-pimcore.com/pimcore-datahub-webservices/dataimporter/mcp",
      "headers": {
        "Authorization": "Bearer your-api-token"
      }
    }
  }
}
```

## Important Notes

- **Read-Only**: Tools don't modify configurations or execute imports
- **YAML Format**: Use proper YAML nesting, not JSON strings in YAML
- **Security**: Requires bearer token authentication
- **Schema**: Only services implementing `SchemaAwareInterface` provide full schema

## Common Issues

**"Settings must be nested YAML"**
```yaml
# Wrong
settings: '{"key": "value"}'

# Correct
settings:
  key: value
```

**"Authorization header missing"**  
Add `Authorization: Bearer token` header to requests.
