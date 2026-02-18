# WP MCP Ultimate

![PHP >= 8.0](https://img.shields.io/badge/PHP-%3E%3D%208.0-777BB4?logo=php&logoColor=white)
![WordPress >= 6.7](https://img.shields.io/badge/WordPress-%3E%3D%206.7-21759B?logo=wordpress&logoColor=white)
![License GPL-2.0-or-later](https://img.shields.io/badge/License-GPL--2.0--or--later-blue)

Connect WordPress to AI in one click. A self-contained MCP (Model Context Protocol) server plugin with 57 WordPress abilities -- manage posts, pages, media, users, plugins, menus, comments, and more through any MCP-compatible AI client. No other plugins needed.

## Quick Start

1. **Install the plugin**: Upload the zip file via Plugins > Add New > Upload Plugin, or clone this repository into `wp-content/plugins/`.
2. **Generate an API key**: Go to Tools > MCP Ultimate in your WordPress admin and click Generate to create an Application Password.
3. **Configure your AI client**: Copy the MCP server config snippet shown on the dashboard into your AI client's configuration file.

## Features

- 57 WordPress abilities covering content, media, users, plugins, menus, widgets, comments, options, and system management
- MCP protocol v2025-06-18 compliance
- SSE (Server-Sent Events) transport
- Admin dashboard with API key management and one-click generation
- Config export snippets for Claude Code, Claude Desktop, and Cursor
- Conflict detection for legacy plugins (MCP Adapter, MCP Expose Abilities, Abilities API)
- Abilities API polyfill for WordPress < 6.9

## Requirements

- PHP 8.0 or higher
- WordPress 6.7 or higher

## Documentation

- [Setup Guide](docs/SETUP.md) -- Detailed installation and configuration instructions
- [Abilities Reference](docs/ABILITIES.md) -- Complete list of all 57 abilities with descriptions and required capabilities

## Credit

Built on work from the WordPress AI team, MCP Adapter, and MCP Expose Abilities plugins.

## Contributing

PRs welcome. Please open an issue first to discuss.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) for the full text.
