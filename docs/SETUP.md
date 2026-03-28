# Setup Guide

## Requirements

- PHP 8.0 or higher
- WordPress 6.7 or higher
- HTTPS recommended for production (API credentials are transmitted with requests)

## Installation

### Option A: Upload via WordPress Admin

1. Download the latest release zip from the [Releases page](https://github.com/agricidaniel/wp-mcp-ultimate/releases)
2. In WordPress admin, go to **Plugins > Add New > Upload Plugin**
3. Choose the zip file and click **Install Now**
4. Click **Activate Plugin**

### Option B: Clone Repository

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/agricidaniel/wp-mcp-ultimate.git
```

Then activate the plugin in **Plugins > Installed Plugins**.

## Configuration

1. After activation, go to **Tools > MCP Ultimate** in your WordPress admin
2. Click **Generate** to create an Application Password API key
3. Copy the config snippet for your AI client (Claude Code, Claude Desktop, or Cursor)
4. Paste the snippet into your AI client's configuration file

## Client Configuration

The MCP server endpoint is:

```
https://YOUR-SITE.com/wp-json/mcp/wp-mcp-ultimate
```

The plugin uses **Streamable HTTP** transport (MCP protocol 2025-06-18). Do **not** append `/sse` to the URL.

Authentication uses HTTP Basic Auth with your WordPress username and the generated Application Password.

### Claude Code

Add to `~/.claude/settings.json`:

```json
{
  "mcpServers": {
    "wordpress": {
      "type": "streamable-http",
      "url": "https://YOUR-SITE.com/wp-json/mcp/wp-mcp-ultimate",
      "headers": {
        "Authorization": "Basic BASE64_ENCODED_CREDENTIALS"
      }
    }
  }
}
```

### Claude Desktop

Add to your `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote",
        "https://YOUR-SITE.com/wp-json/mcp/wp-mcp-ultimate",
        "--header",
        "Authorization: Basic BASE64_ENCODED_CREDENTIALS"
      ]
    }
  }
}
```

### Cursor

Add to `.cursor/mcp.json`:

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote",
        "https://YOUR-SITE.com/wp-json/mcp/wp-mcp-ultimate",
        "--header",
        "Authorization: Basic BASE64_ENCODED_CREDENTIALS"
      ]
    }
  }
}
```

### Generating the Base64 Credentials

The `BASE64_ENCODED_CREDENTIALS` value is `base64(username:application_password)`. You can generate it with:

```bash
echo -n "your_username:xxxx xxxx xxxx xxxx xxxx xxxx" | base64
```

The admin dashboard generates this for you automatically.

## Troubleshooting

### "Unauthorized" or 401 errors

- Verify your Application Password is correct (spaces in the password are normal)
- Ensure your WordPress user has administrator privileges
- Check that pretty permalinks are enabled (**Settings > Permalinks** -- choose anything except "Plain")

### Connection refused or timeout

- Ensure your site is accessible from the internet (not just localhost)
- Check that HTTPS is configured if your AI client requires it
- Verify the URL includes the full path: `/wp-json/mcp/wp-mcp-ultimate`
- Do **not** append `/sse` to the endpoint URL

### Conflict warnings

If you see admin notices about conflicting plugins, deactivate the old plugins:
- MCP Adapter
- MCP Expose Abilities
- Abilities API

WP MCP Ultimate includes all functionality from these plugins.

### REST API disabled

The plugin requires the WordPress REST API. If it's disabled by a security plugin, whitelist the `/wp-json/mcp/` endpoint.
