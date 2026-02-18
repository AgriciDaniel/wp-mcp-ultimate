# WP MCP Ultimate — Design Document

**Date:** 2026-02-18
**Status:** Approved
**Author:** agricidaniel + Claude

---

## Overview

WP MCP Ultimate is a single WordPress plugin that gives AI tools (Claude, Cursor, VS Code, etc.) full access to manage a WordPress site via the Model Context Protocol. It replaces the current 3-plugin setup (Abilities API + MCP Adapter + MCP Expose Abilities) with one install.

**Tagline:** "Connect WordPress to AI in one click. No other plugins needed."

## Requirements

| Requirement | Decision |
|-------------|----------|
| Audience | Non-technical users (Skool community) + developers |
| Architecture | Fork & Merge — consolidate GPL code from MCP Adapter + Expose Abilities |
| Scope | Core WordPress only (57 abilities). Extensible for add-ons. |
| Dependencies | Zero WordPress plugin dependencies. Self-contained. |
| Admin UI | Single page: health check, API key generation, config copy |
| PHP | >= 8.0 |
| WordPress | >= 6.7 |
| MCP Protocol | 2025-06-18 (Streamable HTTP) |
| License | GPL-2.0-or-later |

## Architecture

### Fork & Merge Strategy

Take proven GPL-2.0 code from:
1. **WordPress/mcp-adapter** (v0.4.1) — MCP protocol, transport, session management
2. **bjornfix/mcp-expose-abilities** (v3.0.17) — 57 WordPress abilities

Merge into a single plugin with unified namespace, add admin UI, ship.

### Component Diagram

```
[AI Client] <--STDIO--> [@automattic/mcp-wordpress-remote] <--HTTPS-->
    [WP MCP Ultimate Plugin]
        ├── Server (MCP Protocol)
        │   ├── HttpTransport (REST endpoint)
        │   ├── SessionManager (user-meta based)
        │   ├── RequestRouter (JSON-RPC dispatch)
        │   └── Handlers (initialize, tools/*, resources/*, prompts/*)
        ├── Abilities (57 WordPress abilities)
        │   ├── Content (posts, pages, categories, tags, search, revisions)
        │   ├── Media (upload, get, update, delete)
        │   ├── Users (list, get, create, update, delete)
        │   ├── Plugins (list, activate, deactivate, delete, upload)
        │   ├── Menus (CRUD + assign locations)
        │   ├── Widgets (list sidebars, get sidebar, list available)
        │   ├── Comments (CRUD + moderation)
        │   ├── Options (get, set, list — with blocklist)
        │   └── System (debug log, transients, toggle debug)
        ├── Admin (dashboard + setup)
        │   ├── Health check (auto-detect requirements)
        │   ├── API key generation (Application Passwords)
        │   └── Config export (Claude, Cursor, VS Code JSON)
        └── Helpers (schemas, validation, caching)
```

## Plugin Structure

```
wp-mcp-ultimate/
├── wp-mcp-ultimate.php              # Entry point
├── readme.txt                        # WP.org listing
├── uninstall.php                     # Cleanup
├── LICENSE                           # GPL-2.0
├── composer.json                     # Dev deps
│
├── includes/
│   ├── Plugin.php                    # Bootstrap
│   ├── Autoloader.php                # PSR-4
│   ├── Server/                       # MCP protocol (from MCP Adapter)
│   │   ├── McpServer.php
│   │   ├── Transport/HttpTransport.php
│   │   ├── Transport/StdioTransport.php
│   │   ├── Handlers/
│   │   ├── Session/SessionManager.php
│   │   └── Domain/ (Tools, Resources, Prompts)
│   ├── Abilities/                    # 57 abilities (from Expose Abilities)
│   │   ├── Registry.php
│   │   ├── Content/
│   │   ├── Media/
│   │   ├── Users/
│   │   ├── Plugins/
│   │   ├── Menus/
│   │   ├── Widgets/
│   │   ├── Comments/
│   │   ├── Options/
│   │   └── System/
│   ├── Admin/
│   │   ├── Dashboard.php
│   │   ├── Ajax.php
│   │   └── views/
│   └── Helpers/
│       ├── McpHelper.php
│       └── SchemaDefinitions.php
│
├── assets/css/admin.css
├── assets/js/admin.js
├── tests/
├── docs/
│   ├── SETUP.md
│   ├── ABILITIES.md
│   └── EXTENDING.md
└── .github/workflows/
    ├── ci.yml
    └── release.yml
```

## MCP Endpoint

- **Route:** `/wp-json/mcp/wp-mcp-ultimate`
- **Auth:** Application Passwords (Basic Auth over HTTPS)
- **Sessions:** User-meta storage, 24h timeout, max 32/user
- **Methods:** initialize, ping, tools/list, tools/call, resources/list, resources/read, prompts/list, prompts/get

## Admin UI (Single Page)

### On First Activation
1. Health check banner: WordPress version, PHP version, REST API status (auto-detected, green checkmarks)
2. "Generate API Key" button — creates Application Password, shows it once with copy button
3. Auto-generated Claude config JSON block with copy button
4. Instructions for pasting into `~/.claude.json`

### After Setup (Dashboard)
- Status dot: Connected / Not Connected
- MCP Endpoint URL with copy button
- "57 abilities active" (clickable to expand list)
- "Regenerate Config" button
- "Revoke Access" button

## Conflict Detection

On activation, detect and show admin notices for:
- MCP Adapter plugin (suggest deactivation)
- Abilities API plugin (suggest deactivation)
- MCP Expose Abilities plugin (suggest deactivation)

No auto-deactivation. Own route avoids conflicts.

## Abilities API Compatibility

- WordPress 6.9+: Use core `wp_register_ability()` functions
- WordPress 6.7-6.8: Bundle minimal Abilities API polyfill
- Runtime detection: `function_exists('wp_register_ability')`

## Security

- All abilities check WordPress capabilities via `permission_callback`
- Critical options blocklisted from modification
- Sessions tied to authenticated users only
- HTTPS required for production use
- Application Passwords scoped to the authenticated user's capabilities

## Distribution

1. GitHub Releases (zip upload to wp-admin)
2. Composer (`composer require`)
3. Future: WordPress.org plugin directory

## Credits

Based on GPL-2.0 code from:
- [WordPress/mcp-adapter](https://github.com/WordPress/mcp-adapter) by WordPress contributors
- [bjornfix/mcp-expose-abilities](https://github.com/bjornfix/mcp-expose-abilities) by Bjorn Solstad / Devenia
