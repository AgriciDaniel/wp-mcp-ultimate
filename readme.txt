=== WP MCP Ultimate ===
Contributors: agricidaniel
Tags: mcp, ai, claude, model-context-protocol, automation
Requires at least: 6.7
Tested up to: 6.8
Stable tag: 2.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Connect WordPress to AI in one click. Full MCP server with 57 abilities.

== Description ==

WP MCP Ultimate is a self-contained MCP (Model Context Protocol) server plugin for WordPress. It provides 57 abilities that let any MCP-compatible AI client manage your WordPress site -- posts, pages, media, users, plugins, menus, widgets, comments, options, and system settings.

This plugin combines the functionality of MCP Adapter, MCP Expose Abilities, and Abilities API into a single install. No other plugins are needed.

Key features:

* 57 WordPress abilities organized across 9 domains
* MCP protocol v2025-06-18 with SSE transport
* Admin dashboard with API key generation
* Config export for Claude Code, Claude Desktop, and Cursor
* Conflict detection for legacy MCP plugins
* Abilities API polyfill for WordPress < 6.9

== Installation ==

1. Upload the plugin zip file via Plugins > Add New > Upload Plugin, or clone the repository into `wp-content/plugins/wp-mcp-ultimate/`.
2. Activate the plugin through the Plugins menu.
3. Go to Tools > MCP Ultimate, generate an API key, and copy the config snippet into your AI client.

== Frequently Asked Questions ==

= What is MCP? =

MCP (Model Context Protocol) is an open standard that allows AI clients like Claude to interact with external systems through a structured protocol. It defines how AI tools discover and use capabilities (called "abilities" in WordPress) provided by a server.

= Does this replace MCP Adapter, MCP Expose Abilities, and Abilities API? =

Yes. WP MCP Ultimate is a single plugin that includes all functionality from those three plugins. If any of them are active, the plugin will display a notice recommending you deactivate them to avoid conflicts.

= What AI clients are supported? =

Any MCP-compatible client works. The admin dashboard provides ready-made config snippets for Claude Code, Claude Desktop, and Cursor.

= Do I need HTTPS? =

HTTPS is strongly recommended for production use since API credentials are transmitted with each request. The plugin will work over HTTP for local development.

= What WordPress capabilities are required? =

Different abilities require different WordPress capabilities. For example, content abilities require `edit_posts`, user abilities require `list_users` or `edit_users`, and system abilities require `manage_options`. See the full abilities reference in docs/ABILITIES.md.

== Changelog ==

= 2.0.0 =
* Merged best implementations from MCP Expose Abilities v3.0.17 (Bjorn Solstad) and WP MCP Ultimate v1.0.0
* Fixed content/search total count always returning 0 (no_found_rows bug)
* Fixed options/get and options/update mangling option names with uppercase letters
* Improved plugin zip install error diagnostics (shows found items on failure)
* Added returned count and has_more pagination to search results

= 1.0.0 =
* Initial release with 57 WordPress abilities
* Admin dashboard with API key generation
* Config export for Claude Code, Claude Desktop, Cursor
* Conflict detection for MCP Adapter, MCP Expose Abilities, Abilities API
* Abilities API polyfill for WordPress < 6.9
* SSE transport with MCP protocol v2025-06-18
