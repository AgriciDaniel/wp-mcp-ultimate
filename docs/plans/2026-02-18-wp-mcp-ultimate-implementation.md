# WP MCP Ultimate — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a single WordPress plugin that provides full MCP server + 57 WordPress abilities + admin dashboard, replacing the 3-plugin setup.

**Architecture:** Fork & Merge — consolidate GPL code from WordPress/mcp-adapter (v0.4.1) and bjornfix/mcp-expose-abilities (v3.0.17) into a unified plugin under the `WpMcpUltimate` namespace. Add a simple admin dashboard with API key generation and config export.

**Tech Stack:** PHP 8.0+, WordPress 6.7+, MCP Protocol 2025-06-18, PSR-4 autoloading, WordPress REST API, Application Passwords API.

**Upstream Sources:** Already downloaded to `.upstream/mcp-adapter/` and `.upstream/mcp-expose-abilities/`

---

## Task 1: Scaffold Plugin Entry Point & Autoloader

**Files:**
- Create: `wp-mcp-ultimate.php`
- Create: `includes/Autoloader.php`
- Create: `includes/Plugin.php`

**Step 1: Create the main plugin file**

```php
<?php
/**
 * WP MCP Ultimate
 *
 * @package     WpMcpUltimate
 * @author      agricidaniel
 * @copyright   2026
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       WP MCP Ultimate
 * Plugin URI:        https://github.com/agricidaniel/wp-mcp-ultimate
 * Description:       Connect WordPress to AI in one click. Full MCP server with 57 WordPress abilities — no other plugins needed.
 * Requires at least: 6.7
 * Version:           1.0.0
 * Requires PHP:      8.0
 * Author:            agricidaniel
 * Author URI:        https://github.com/agricidaniel
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain:       wp-mcp-ultimate
 */

declare(strict_types=1);

namespace WpMcpUltimate;

defined('ABSPATH') || exit();

define('WP_MCP_ULTIMATE_DIR', plugin_dir_path(__FILE__));
define('WP_MCP_ULTIMATE_URL', plugin_dir_url(__FILE__));
define('WP_MCP_ULTIMATE_VERSION', '1.0.0');

require_once __DIR__ . '/includes/Autoloader.php';

if (Autoloader::register()) {
    Plugin::instance();
}
```

Write to: `wp-mcp-ultimate.php`

**Step 2: Create the PSR-4 autoloader**

Build a simple PSR-4 autoloader that maps `WpMcpUltimate\` to `includes/`. No Composer dependency required for production. This replaces the upstream MCP Adapter's Composer-based autoloader.

The autoloader should:
- Map `WpMcpUltimate\` namespace prefix to `includes/` directory
- Convert namespace separators to directory separators
- Handle the full class hierarchy (Server, Abilities, Admin, Helpers)

Reference: `.upstream/mcp-adapter/includes/Autoloader.php` for pattern, but simplify — no Composer fallback needed.

Write to: `includes/Autoloader.php`

**Step 3: Create the Plugin bootstrap**

The Plugin class should:
- Be a singleton (same pattern as upstream `Plugin.php`)
- Check for Abilities API availability: `function_exists('wp_register_ability')`
- If Abilities API is NOT available (WP < 6.9), load our bundled polyfill (Task 3)
- If Abilities API IS available, proceed directly
- Initialize the MCP server via `McpAdapter::instance()` (our namespaced copy)
- Check for conflict plugins (MCP Adapter, Abilities API, MCP Expose Abilities) and show admin notices

Reference: `.upstream/mcp-adapter/includes/Plugin.php` — adapt the `has_dependencies()` check to load polyfill instead of failing.

Write to: `includes/Plugin.php`

**Step 4: Commit**

```bash
git add wp-mcp-ultimate.php includes/Autoloader.php includes/Plugin.php
git commit -m "feat: scaffold plugin entry point, autoloader, and bootstrap"
```

---

## Task 2: Port MCP Server Core (from mcp-adapter)

**Files:**
- Create: `includes/Server/McpAdapter.php`
- Create: `includes/Server/McpServer.php`
- Create: `includes/Server/McpComponentRegistry.php`
- Create: `includes/Server/McpTransportFactory.php`
- Create: `includes/Server/DefaultServerFactory.php`

**Step 1: Copy and re-namespace the Core classes**

Copy these files from `.upstream/mcp-adapter/includes/`:
- `Core/McpAdapter.php` → `includes/Server/McpAdapter.php`
- `Core/McpServer.php` → `includes/Server/McpServer.php`
- `Core/McpComponentRegistry.php` → `includes/Server/McpComponentRegistry.php`
- `Core/McpTransportFactory.php` → `includes/Server/McpTransportFactory.php`
- `Servers/DefaultServerFactory.php` → `includes/Server/DefaultServerFactory.php`

For each file:
1. Change namespace from `WP\MCP\Core` (or `WP\MCP\Servers`) to `WpMcpUltimate\Server`
2. Update all `use WP\MCP\...` imports to `use WpMcpUltimate\...`
3. Update the `WP_MCP_DIR` constant references to `WP_MCP_ULTIMATE_DIR`
4. Update text domain from `mcp-adapter` to `wp-mcp-ultimate`
5. In `DefaultServerFactory::create()`, change:
   - `server_id` to `'wp-mcp-ultimate-server'`
   - `server_route` to `'wp-mcp-ultimate'`
   - `server_route_namespace` to `'mcp'`
   - `server_name` to `'WP MCP Ultimate'`
   - `server_description` to `'All-in-one MCP server for WordPress'`

**Step 2: Verify namespace mapping is consistent**

Ensure all cross-references between files use the new `WpMcpUltimate\Server` namespace. The McpAdapter class references:
- `WpMcpUltimate\Server\Abilities\DiscoverAbilitiesAbility` (will be created in Task 4)
- `WpMcpUltimate\Server\Abilities\ExecuteAbilityAbility`
- `WpMcpUltimate\Server\Abilities\GetAbilityInfoAbility`
- `WpMcpUltimate\Server\Infrastructure\...` (will be created in this task)
- `WpMcpUltimate\Server\DefaultServerFactory`

**Step 3: Commit**

```bash
git add includes/Server/
git commit -m "feat: port MCP server core from mcp-adapter with WpMcpUltimate namespace"
```

---

## Task 3: Port Transport, Handlers, Domain, and Infrastructure

**Files:**
- Create: `includes/Server/Transport/HttpTransport.php`
- Create: `includes/Server/Transport/Contracts/McpRestTransportInterface.php`
- Create: `includes/Server/Transport/Contracts/McpTransportInterface.php`
- Create: `includes/Server/Transport/Infrastructure/HttpRequestContext.php`
- Create: `includes/Server/Transport/Infrastructure/HttpRequestHandler.php`
- Create: `includes/Server/Transport/Infrastructure/HttpSessionValidator.php`
- Create: `includes/Server/Transport/Infrastructure/JsonRpcResponseBuilder.php`
- Create: `includes/Server/Transport/Infrastructure/McpTransportContext.php`
- Create: `includes/Server/Transport/Infrastructure/McpTransportHelperTrait.php`
- Create: `includes/Server/Transport/Infrastructure/RequestRouter.php`
- Create: `includes/Server/Transport/Infrastructure/SessionManager.php`
- Create: `includes/Server/Handlers/HandlerHelperTrait.php`
- Create: `includes/Server/Handlers/Initialize/InitializeHandler.php`
- Create: `includes/Server/Handlers/Tools/ToolsHandler.php`
- Create: `includes/Server/Handlers/Resources/ResourcesHandler.php`
- Create: `includes/Server/Handlers/Prompts/PromptsHandler.php`
- Create: `includes/Server/Handlers/System/SystemHandler.php`
- Create: `includes/Server/Domain/Tools/McpTool.php`
- Create: `includes/Server/Domain/Tools/McpToolValidator.php`
- Create: `includes/Server/Domain/Tools/RegisterAbilityAsMcpTool.php`
- Create: `includes/Server/Domain/Resources/McpResource.php`
- Create: `includes/Server/Domain/Resources/McpResourceValidator.php`
- Create: `includes/Server/Domain/Resources/RegisterAbilityAsMcpResource.php`
- Create: `includes/Server/Domain/Prompts/McpPrompt.php`
- Create: `includes/Server/Domain/Prompts/McpPromptBuilder.php`
- Create: `includes/Server/Domain/Prompts/McpPromptValidator.php`
- Create: `includes/Server/Domain/Prompts/RegisterAbilityAsMcpPrompt.php`
- Create: `includes/Server/Domain/Prompts/Contracts/McpPromptBuilderInterface.php`
- Create: `includes/Server/Domain/Utils/McpAnnotationMapper.php`
- Create: `includes/Server/Domain/Utils/McpValidator.php`
- Create: `includes/Server/Domain/Utils/SchemaTransformer.php`
- Create: `includes/Server/Infrastructure/ErrorHandling/Contracts/McpErrorHandlerInterface.php`
- Create: `includes/Server/Infrastructure/ErrorHandling/ErrorLogMcpErrorHandler.php`
- Create: `includes/Server/Infrastructure/ErrorHandling/McpErrorFactory.php`
- Create: `includes/Server/Infrastructure/ErrorHandling/NullMcpErrorHandler.php`
- Create: `includes/Server/Infrastructure/Observability/Contracts/McpObservabilityHandlerInterface.php`
- Create: `includes/Server/Infrastructure/Observability/ConsoleObservabilityHandler.php`
- Create: `includes/Server/Infrastructure/Observability/ErrorLogMcpObservabilityHandler.php`
- Create: `includes/Server/Infrastructure/Observability/McpObservabilityHelperTrait.php`
- Create: `includes/Server/Infrastructure/Observability/NullMcpObservabilityHandler.php`

**Step 1: Copy all files from upstream, preserving directory structure**

For each file in `.upstream/mcp-adapter/includes/`:
- `Transport/` → `includes/Server/Transport/`
- `Handlers/` → `includes/Server/Handlers/`
- `Domain/` → `includes/Server/Domain/`
- `Infrastructure/` → `includes/Server/Infrastructure/`

**Step 2: Re-namespace all files**

Global find-and-replace in every copied file:
- `namespace WP\MCP\Transport` → `namespace WpMcpUltimate\Server\Transport`
- `namespace WP\MCP\Handlers` → `namespace WpMcpUltimate\Server\Handlers`
- `namespace WP\MCP\Domain` → `namespace WpMcpUltimate\Server\Domain`
- `namespace WP\MCP\Infrastructure` → `namespace WpMcpUltimate\Server\Infrastructure`
- `namespace WP\MCP\Core` → `namespace WpMcpUltimate\Server`
- `use WP\MCP\` → `use WpMcpUltimate\Server\` (but be careful — Abilities references need `WpMcpUltimate\Server\Abilities\`)
- Text domain: `mcp-adapter` → `wp-mcp-ultimate`

**Step 3: Commit**

```bash
git add includes/Server/Transport/ includes/Server/Handlers/ includes/Server/Domain/ includes/Server/Infrastructure/
git commit -m "feat: port transport, handlers, domain, and infrastructure layers"
```

---

## Task 4: Port MCP Meta-Abilities (discover, info, execute)

**Files:**
- Create: `includes/Server/Abilities/DiscoverAbilitiesAbility.php`
- Create: `includes/Server/Abilities/ExecuteAbilityAbility.php`
- Create: `includes/Server/Abilities/GetAbilityInfoAbility.php`
- Create: `includes/Server/Abilities/McpAbilityHelperTrait.php`

**Step 1: Copy from upstream**

Copy `.upstream/mcp-adapter/includes/Abilities/` → `includes/Server/Abilities/`

**Step 2: Re-namespace**

- `namespace WP\MCP\Abilities` → `namespace WpMcpUltimate\Server\Abilities`
- Update all `use WP\MCP\...` imports
- Update text domain

These are the 3 meta-tools that wrap all 57 abilities:
- `discover-abilities` — lists all public abilities
- `get-ability-info` — returns schema for a specific ability
- `execute-ability` — executes any public ability by name

**Step 3: Commit**

```bash
git add includes/Server/Abilities/
git commit -m "feat: port MCP meta-abilities (discover, info, execute)"
```

---

## Task 5: Abilities API Polyfill (for WordPress < 6.9)

**Files:**
- Create: `includes/Compat/AbilitiesApi.php`

**Step 1: Build the polyfill**

Create a minimal Abilities API polyfill that provides:
- `wp_register_ability($name, $args)` — registers an ability in our internal registry
- `wp_get_ability($name)` — retrieves a registered ability
- `wp_get_abilities()` — returns all registered abilities
- `wp_ability_is_registered($name)` — checks if ability exists
- `wp_register_ability_category($slug, $args)` — registers a category
- `WP_Ability` class — with `get_name()`, `get_meta()`, `get_input_schema()`, `get_output_schema()`, `execute($input)`, `check_permissions($input)`
- `WP_Ability_Category` class — with `get_slug()`, `get_label()`, `get_description()`
- `WP_Abilities_Registry` class — singleton holding all abilities

Reference the WordPress 6.9 Abilities API documentation and the GitHub repo at https://github.com/WordPress/abilities-api for the exact function signatures and class interfaces.

The polyfill must:
- Only define functions/classes if they don't already exist (`if (!function_exists(...)`)
- Fire the `wp_abilities_api_categories_init` and `wp_abilities_api_init` actions at the right time
- Be loaded from Plugin.php when `function_exists('wp_register_ability')` returns false

**Step 2: Update Plugin.php to load polyfill conditionally**

In `Plugin::setup()`, before calling `McpAdapter::instance()`:
```php
if (!function_exists('wp_register_ability')) {
    require_once WP_MCP_ULTIMATE_DIR . 'includes/Compat/AbilitiesApi.php';
}
```

**Step 3: Commit**

```bash
git add includes/Compat/AbilitiesApi.php includes/Plugin.php
git commit -m "feat: add Abilities API polyfill for WordPress < 6.9"
```

---

## Task 6: Port WordPress Abilities — Helpers & Schema Definitions

**Files:**
- Create: `includes/Abilities/Helpers.php`
- Create: `includes/Abilities/SchemaDefinitions.php`

**Step 1: Extract helpers from expose-abilities**

From `.upstream/mcp-expose-abilities/mcp-expose-abilities.php`, extract:
- The `MCP_Helper` class (around line 482-670) → `includes/Abilities/Helpers.php`
- All `MCP_SCHEMA_*` constants (lines 138-271) → `includes/Abilities/SchemaDefinitions.php`
- The `mcp_expose_install_plugin_zip()` function (line 286)
- The `mcp_expose_all_abilities()` filter function (line 433)
- The `mcp_expose_parse_pagination()` function (line 469)
- The `mcp_get_optimized_query_args()` function (line 667)
- The WordPress admin include guards (lines 108-126)

Wrap the MCP_Helper class in `WpMcpUltimate\Abilities` namespace.
Keep the global functions as-is (they use WordPress naming convention) but prefix them with `wp_mcp_ultimate_` to avoid conflicts if MCP Expose Abilities is also active.

**Step 2: Commit**

```bash
git add includes/Abilities/Helpers.php includes/Abilities/SchemaDefinitions.php
git commit -m "feat: port ability helpers and schema definitions"
```

---

## Task 7: Port Content Abilities (Posts, Pages, Revisions, Search)

**Files:**
- Create: `includes/Abilities/Content/Posts.php`
- Create: `includes/Abilities/Content/Pages.php`
- Create: `includes/Abilities/Content/Taxonomy.php`
- Create: `includes/Abilities/Content/Search.php`
- Create: `includes/Abilities/Content/Revisions.php`

**Step 1: Extract content abilities from the monolith**

From `.upstream/mcp-expose-abilities/mcp-expose-abilities.php`, extract:

**Posts.php** — abilities at these lines:
- `content/list-posts` (Line 720)
- `content/get-post` (Line 878)
- `content/create-post` (Line 987)
- `content/update-post` (Line 1117)
- `content/delete-post` (Line 1255)
- `content/patch-post` (Line 2655)
- `content/list-media` (Line 2466) — media listing is under content/ in upstream
- `content/list-users` (Line 2566) — user listing is under content/ in upstream

**Pages.php** — abilities:
- `content/list-pages` (Line 1328)
- `content/get-page` (Line 1442)
- `content/create-page` (Line 1545)
- `content/update-page` (Line 1661)
- `content/delete-page` (Line 1797)
- `content/patch-page` (Line 2014)

**Taxonomy.php** — abilities:
- `content/list-categories` (Line 2140)
- `content/create-category` (Line 2209)
- `content/list-tags` (Line 2308)
- `content/create-tag` (Line 2374)

**Search.php** — abilities:
- `content/search` (Line 2794)

**Revisions.php** — abilities:
- `content/list-revisions` (Line 1864)
- `content/get-revision` (Line 1944)

Each file should contain a static `register()` method that calls `wp_register_ability()` for each ability in its group. The abilities should use the same input/output schemas and callbacks as the upstream.

**Step 2: Commit**

```bash
git add includes/Abilities/Content/
git commit -m "feat: port content abilities (posts, pages, taxonomy, search, revisions)"
```

---

## Task 8: Port Remaining Abilities (Media, Users, Plugins, Menus, Widgets, Comments, Options, System)

**Files:**
- Create: `includes/Abilities/Media/Media.php`
- Create: `includes/Abilities/Users/Users.php`
- Create: `includes/Abilities/Plugins/Plugins.php`
- Create: `includes/Abilities/Menus/Menus.php`
- Create: `includes/Abilities/Widgets/Widgets.php`
- Create: `includes/Abilities/Comments/Comments.php`
- Create: `includes/Abilities/Options/Options.php`
- Create: `includes/Abilities/System/System.php`

**Step 1: Extract each category from the monolith**

From `.upstream/mcp-expose-abilities/mcp-expose-abilities.php`:

**Media.php** (4 abilities) — starting around line 3488+:
- `media/upload`, `media/get`, `media/update`, `media/delete`

**Users.php** (5 abilities):
- `users/list`, `users/get`, `users/create`, `users/update`, `users/delete`

**Plugins.php** (6 abilities) — Lines 2880-3320:
- `plugins/upload`, `plugins/upload-base64`, `plugins/list`, `plugins/delete`, `plugins/activate`, `plugins/deactivate`

**Menus.php** (7 abilities) — Lines 3326-3488:
- `menus/list`, `menus/get-items`, `menus/create`, `menus/add-item`, `menus/update-item`, `menus/delete-item`, `menus/assign-location`

**Widgets.php** (3 abilities) — Lines 3488+:
- `widgets/list-sidebars`, `widgets/get-sidebar`, `widgets/list-available`

**Comments.php** (6 abilities):
- `comments/list`, `comments/get`, `comments/create`, `comments/reply`, `comments/delete`, `comments/update-status`

**Options.php** (3 abilities):
- `options/list`, `options/get`, `options/update`

**System.php** (3 abilities):
- `system/debug-log`, `system/toggle-debug`, `system/get-transient`

Each file should follow the same pattern as Task 7: static `register()` method calling `wp_register_ability()` for each.

**Step 2: Commit**

```bash
git add includes/Abilities/Media/ includes/Abilities/Users/ includes/Abilities/Plugins/ includes/Abilities/Menus/ includes/Abilities/Widgets/ includes/Abilities/Comments/ includes/Abilities/Options/ includes/Abilities/System/
git commit -m "feat: port remaining abilities (media, users, plugins, menus, widgets, comments, options, system)"
```

---

## Task 9: Create Abilities Registry (orchestrator)

**Files:**
- Create: `includes/Abilities/Registry.php`

**Step 1: Build the central registry**

Create a class that:
1. Hooks into `wp_abilities_api_categories_init` to register all ability categories (content, media, users, plugins, menus, widgets, comments, options, system)
2. Hooks into `wp_abilities_api_init` to call each ability file's `register()` method
3. Applies the `wp_register_ability_args` filter to mark all abilities as MCP-public (same as `mcp_expose_all_abilities()` from upstream)

```php
namespace WpMcpUltimate\Abilities;

class Registry {
    public static function init(): void {
        add_action('wp_abilities_api_categories_init', [self::class, 'register_categories']);
        add_action('wp_abilities_api_init', [self::class, 'register_abilities']);
        add_filter('wp_register_ability_args', [self::class, 'expose_all_abilities'], 10, 2);
    }

    public static function register_categories(): void {
        // Register: content, media, users, plugins, menus, widgets, comments, options, system
    }

    public static function register_abilities(): void {
        Content\Posts::register();
        Content\Pages::register();
        Content\Taxonomy::register();
        Content\Search::register();
        Content\Revisions::register();
        Media\Media::register();
        Users\Users::register();
        Plugins\Plugins::register();
        Menus\Menus::register();
        Widgets\Widgets::register();
        Comments\Comments::register();
        Options\Options::register();
        System\System::register();
    }

    public static function expose_all_abilities(array $args, string $ability_name): array {
        // Set meta.mcp.public = true and meta.mcp.type = 'tool' on every ability
    }
}
```

**Step 2: Wire it into Plugin.php**

Call `Registry::init()` in `Plugin::setup()` before `McpAdapter::instance()`.

**Step 3: Commit**

```bash
git add includes/Abilities/Registry.php includes/Plugin.php
git commit -m "feat: create abilities registry to orchestrate all 57 abilities"
```

---

## Task 10: Admin Dashboard — Single Page

**Files:**
- Create: `includes/Admin/Dashboard.php`
- Create: `includes/Admin/Ajax.php`
- Create: `includes/Admin/views/dashboard.php`
- Create: `assets/css/admin.css`
- Create: `assets/js/admin.js`

**Step 1: Create the Dashboard class**

```php
namespace WpMcpUltimate\Admin;

class Dashboard {
    public static function init(): void {
        add_action('admin_menu', [self::class, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function add_menu_page(): void {
        add_management_page(
            'WP MCP Ultimate',
            'MCP Ultimate',
            'manage_options',
            'wp-mcp-ultimate',
            [self::class, 'render_page']
        );
    }

    public static function render_page(): void {
        include WP_MCP_ULTIMATE_DIR . 'includes/Admin/views/dashboard.php';
    }

    public static function enqueue_assets(string $hook): void {
        if ($hook !== 'tools_page_wp-mcp-ultimate') return;
        wp_enqueue_style('wp-mcp-ultimate-admin', WP_MCP_ULTIMATE_URL . 'assets/css/admin.css', [], WP_MCP_ULTIMATE_VERSION);
        wp_enqueue_script('wp-mcp-ultimate-admin', WP_MCP_ULTIMATE_URL . 'assets/js/admin.js', ['jquery'], WP_MCP_ULTIMATE_VERSION, true);
        wp_localize_script('wp-mcp-ultimate-admin', 'wpMcpUltimate', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp-mcp-ultimate'),
            'restUrl' => rest_url('mcp/wp-mcp-ultimate'),
            'siteUrl' => site_url(),
        ]);
    }
}
```

**Step 2: Create the dashboard view**

The single-page view should include:
1. **Health Check Section** — Auto-detect PHP version (>= 8.0), WordPress version (>= 6.7), REST API enabled, HTTPS status. Show green checkmarks for passing, red X for failing.
2. **API Key Section** — "Generate API Key" button that creates an Application Password via AJAX. Shows the password once with a copy button. If key already exists, show "API Key Active" with a "Revoke" button.
3. **Config Section** — Auto-generated JSON config block for Claude Code (`~/.claude.json`), Claude Desktop, Cursor. Copy-to-clipboard button. Uses current site URL and authenticated username.
4. **Status Section** — Green/red dot showing if endpoint is reachable. Ability count ("57 abilities active").

**Step 3: Create AJAX handler**

The Ajax class handles:
- `wp_ajax_wp_mcp_ultimate_generate_key` — Creates Application Password via `WP_Application_Passwords::create_new_application_password()`
- `wp_ajax_wp_mcp_ultimate_revoke_key` — Deletes the Application Password
- `wp_ajax_wp_mcp_ultimate_test_connection` — Makes a self-request to the MCP endpoint to verify it's working

**Step 4: Create admin CSS and JS**

Keep it minimal:
- CSS: Clean card-based layout, status indicators, copy button styling
- JS: AJAX calls for key generation/revocation, clipboard copy, health check refresh

**Step 5: Wire into Plugin.php**

Call `Dashboard::init()` in `Plugin::setup()` inside an `is_admin()` check.

**Step 6: Commit**

```bash
git add includes/Admin/ assets/
git commit -m "feat: add admin dashboard with API key generation and config export"
```

---

## Task 11: Conflict Detection & Activation Hooks

**Files:**
- Create: `uninstall.php`
- Modify: `wp-mcp-ultimate.php` (add activation/deactivation hooks)
- Modify: `includes/Plugin.php` (add conflict detection)

**Step 1: Add activation hook**

On activation:
- Set a transient to trigger the setup wizard redirect
- Flush rewrite rules (for the REST API route)

**Step 2: Add deactivation hook**

On deactivation:
- Clean up transients
- Flush rewrite rules

**Step 3: Create uninstall.php**

On uninstall:
- Delete plugin options from `wp_options`
- Optionally delete Application Passwords created by this plugin (tracked by option)
- Clean up any session data from user meta

**Step 4: Add conflict detection**

In Plugin.php, check on `admin_init`:
- `is_plugin_active('mcp-adapter/mcp-adapter.php')` → show notice suggesting deactivation
- `is_plugin_active('mcp-expose-abilities/mcp-expose-abilities.php')` → show notice
- `is_plugin_active('abilities-api/abilities-api.php')` → show notice

**Step 5: Commit**

```bash
git add uninstall.php wp-mcp-ultimate.php includes/Plugin.php
git commit -m "feat: add activation hooks, uninstall cleanup, and conflict detection"
```

---

## Task 12: README, License, and Documentation

**Files:**
- Create: `README.md`
- Create: `readme.txt`
- Create: `LICENSE`
- Create: `docs/SETUP.md`
- Create: `docs/ABILITIES.md`

**Step 1: Create README.md**

GitHub-facing README with:
- Plugin name, tagline, badges (PHP version, WP version, License)
- One-paragraph description
- Quick install steps (3 steps: install plugin, generate API key, paste config)
- Screenshot placeholder
- Credit section (MCP Adapter, MCP Expose Abilities, WordPress AI Team)
- Contributing section
- License (GPL-2.0-or-later)

**Step 2: Create readme.txt**

WordPress.org format:
- Contributors, tags, requires at least, tested up to, stable tag, license
- Description, Installation, FAQ, Changelog sections

**Step 3: Create LICENSE**

Full GPL-2.0-or-later text.

**Step 4: Create docs/SETUP.md**

Detailed setup guide covering:
- WordPress requirements
- Installation methods (zip upload, Composer)
- Generating API key
- Configuring Claude Code, Claude Desktop, Cursor, VS Code
- Troubleshooting common issues

**Step 5: Create docs/ABILITIES.md**

Full reference of all 57 abilities organized by category with:
- Ability name, description, required capability, readonly/destructive flags

**Step 6: Commit**

```bash
git add README.md readme.txt LICENSE docs/
git commit -m "docs: add README, WordPress readme, license, setup guide, and abilities reference"
```

---

## Task 13: GitHub Actions CI

**Files:**
- Create: `.github/workflows/ci.yml`
- Create: `.github/workflows/release.yml`
- Create: `.gitignore`

**Step 1: Create CI workflow**

On push/PR:
- PHP syntax check (lint) for PHP 8.0, 8.1, 8.2, 8.3
- WordPress Coding Standards check (phpcs) — optional, can add later

**Step 2: Create release workflow**

On tag push (`v*`):
- Build production zip (exclude: `.upstream/`, `tests/`, `.github/`, `docs/plans/`, `.gitignore`)
- Create GitHub Release with the zip attached
- Name the zip `wp-mcp-ultimate-{version}.zip`

**Step 3: Create .gitignore**

```
.upstream/
vendor/
node_modules/
*.log
.DS_Store
```

**Step 4: Commit**

```bash
git add .github/ .gitignore
git commit -m "ci: add GitHub Actions for linting and release automation"
```

---

## Task 14: Final Integration Testing & Polish

**Step 1: Verify the full file structure**

Run `find . -type f -not -path './.git/*' -not -path './.upstream/*' | sort` and verify it matches the design document.

**Step 2: Verify all namespace references**

Search for any remaining `WP\MCP\` references that should be `WpMcpUltimate\`:
```bash
grep -r "WP\\\\MCP\\\\" includes/ --include="*.php"
```
Should return zero results.

**Step 3: Verify all text domain references**

Search for old text domains:
```bash
grep -r "mcp-adapter" includes/ --include="*.php"
grep -r "mcp-expose-abilities" includes/ --include="*.php"
```
Should return zero results.

**Step 4: Create a final summary commit**

```bash
git add -A
git commit -m "chore: final integration polish and verification"
```

---

## Execution Notes

- **Total tasks:** 14
- **Estimated commits:** 14
- **Key risk:** The Abilities API polyfill (Task 5) is the most complex novel code. Everything else is mostly re-namespacing proven GPL code.
- **Testing approach:** After Task 10, the plugin can be installed on a WordPress 6.9 site to verify the MCP endpoint responds and the admin dashboard works. Testing on WP 6.7-6.8 will verify the polyfill.
- **Order matters:** Tasks 1-4 must be sequential (each builds on the previous). Tasks 7-8 can be parallelized. Tasks 10-13 can be parallelized after Task 9.
