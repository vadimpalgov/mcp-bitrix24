# parfeon/mcp-bitrix24

> **Alpha version** — API and configuration format may change without notice. Not recommended for production use.

[Русская версия](README_RUS.md)

Composer package that runs an MCP (Model Context Protocol) server on top of Bitrix24, allowing AI clients (Claude Desktop, Cursor, etc.) to read and manage CRM data directly via chat.

## How it works

Runs as a long-lived PHP CLI process. Communicates with the MCP client over **stdio** (JSON-RPC via stdin/stdout) — the standard MCP transport. The client sends a tool call, the server executes it through the Bitrix API and returns the result.

## Requirements

- PHP 8.3+
- Bitrix24 installed and accessible from the same runtime environment
- Composer

## Installation

```bash
composer require parfeon/mcp-bitrix24
```

## Configuration

Add an `extra.mcp-bitrix24` section to your root `composer.json`:

```json
{
  "extra": {
    "mcp-bitrix24": {
      "bitrix_root": "/var/www/bitrix",
      "admin_user_id": 1,
      "modules": ["crm", "tasks", "socialnetwork"]
    }
  }
}
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `bitrix_root` | string | auto-detect | Absolute path to the Bitrix document root. If omitted, the package walks up the directory tree looking for `bitrix/modules/main/include/prolog_before.php`. |
| `admin_user_id` | int | `1` | Bitrix user ID used to authorize internal API calls. |
| `modules` | array | `["crm", "tasks", "socialnetwork"]` | Bitrix modules to load on startup. Add `"iblock"` if you use iblock-linked fields. |

If `bitrix_root` is not set and auto-detection fails, the server exits with:

```
[MCP] Bitrix root not found. Set extra.mcp-bitrix24.bitrix_root in composer.json
```

## MCP Client Setup

### Claude Desktop

**Windows (Docker + WSL):**

```json
{
  "mcpServers": {
    "bitrix24": {
      "command": "wsl",
      "args": ["docker", "exec", "-i", "bitrix_php",
               "php", "/var/www/bitrix/local/modules/bit.mcp/extension/cli/server.php"]
    }
  }
}
```

Config file location: `%APPDATA%\Claude\claude_desktop_config.json`

**Linux / macOS (native server):**

```json
{
  "mcpServers": {
    "bitrix24": {
      "command": "php",
      "args": ["/var/www/bitrix/local/modules/bit.mcp/extension/cli/server.php"]
    }
  }
}
```

**Linux / macOS (Docker):**

```json
{
  "mcpServers": {
    "bitrix24": {
      "command": "docker",
      "args": ["exec", "-i", "bitrix_php",
               "php", "/var/www/bitrix/local/modules/bit.mcp/extension/cli/server.php"]
    }
  }
}
```

**Remote server via SSH:**

```json
{
  "mcpServers": {
    "bitrix24": {
      "command": "ssh",
      "args": ["user@your-server",
               "php", "/var/www/bitrix/local/modules/bit.mcp/extension/cli/server.php"]
    }
  }
}
```

Any MCP client that supports stdio transport will work with the same pattern.

## Available Tools

| Tool | Description |
|---|---|
| `crm_get_deals` | List deals with optional filters (stage, responsible) |
| `crm_get_deal` | Get a single deal by ID including UF_* fields |
| `crm_get_contacts` | List contacts with optional name search |
| `crm_get_contact` | Get a single contact by ID |
| `crm_get_item` | Get any CRM entity item by `entityTypeId` and `id` |
| `crm_create_item` | Create a CRM entity item |
| `crm_update_item` | Update fields of a CRM entity item |
| `crm_delete_item` | Delete a CRM entity item (irreversible) |
| `crm_get_entity_fields` | List all fields (ORM + UF_* + parent relations) for an entity type |
| `crm_get_smart_processes` | List all smart processes (custom CRM types) |
| `crm_add_user_field` | Add a user field (UF_*) to a CRM entity |
| `crm_update_user_field` | Update label / mandatory / sort of a user field |
| `crm_delete_user_field` | Delete a user field (irreversible) |
| `iblock_get_elements` | List iblock elements (useful for decoding iblock_element field values) |
| `tasks_get_list` | List tasks with optional filters |
| `tasks_create` | Create a task |
| `tasks_update` | Update a task |
| `tasks_delete` | Delete a task (irreversible) |
| `groups_get_list` | List workgroups/projects with optional filters |
| `groups_create` | Create a workgroup or project |
| `groups_update` | Update a workgroup or project |
| `groups_delete` | Delete a workgroup or project (irreversible) |

## Running Environments

The server is not tied to Docker. It runs anywhere PHP 8.3+ has access to the Bitrix installation files:

| Environment | Launch command |
|---|---|
| Native Linux / macOS | `php /path/to/cli/server.php` |
| Docker (Linux / macOS) | `docker exec -i bitrix_php php /path/to/cli/server.php` |
| Docker + WSL (Windows) | `wsl docker exec -i bitrix_php php /path/to/cli/server.php` |
| Remote server via SSH | `ssh user@host php /path/to/cli/server.php` |

> PHP CGI / FPM is **not supported** — the server requires a CLI process with stdio.

## Manual Start

```bash
php /var/www/bitrix/local/modules/bit.mcp/extension/cli/server.php
```

Server logs are written to stderr and visible in the MCP client's log file
(`%APPDATA%\Claude\logs\mcp-server-bitrix24.log` for Claude Desktop on Windows).

## License

Copyright (c) 2025 [Vadim Palgov](https://parfeon.dev)

Licensed under the [Elastic License 2.0](LICENSE). You may not redistribute, resell, or rebrand this software as your own product. See [NOTICE](NOTICE) and [LICENSE](LICENSE) for full terms.
