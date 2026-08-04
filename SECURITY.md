# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability, please report it responsibly:

1. **Do NOT open a public issue**
2. Open a [GitHub Security Advisory](https://github.com/AgriciDaniel/wp-mcp-ultimate/security/advisories/new) on this repo
3. Or contact the maintainer directly

## Supported Versions

Only the latest version receives security updates.

## Security Practices

- No credentials or API keys are stored in this repository
- WordPress nonces and capability checks are used for all admin actions
- All user input is sanitized and escaped following WordPress coding standards
- Database queries use prepared statements via `$wpdb->prepare()`
- Dependencies are monitored via Dependabot for known vulnerabilities

## Hardening Defaults (v2.1.0+)

The MCP surface ships locked down. Each default can be adjusted with a filter:

| Behavior | Default | Filter to change it |
|----------|---------|---------------------|
| Capability required to reach the MCP endpoint / meta-tools | `edit_posts` | `mcp_adapter_default_transport_permission_user_capability`, `wp_mcp_ultimate_discover_abilities_capability`, `wp_mcp_ultimate_get_ability_info_capability`, `wp_mcp_ultimate_execute_ability_capability` |
| `plugins/upload` + `plugins/upload-base64` activate / overwrite | `false` (install only, no auto-activate/replace) | pass `activate: true` / `overwrite: true` per call |
| `system/toggle-debug` (rewrites `wp-config.php`) | disabled | `wp_mcp_ultimate_allow_toggle_debug` |
| Bundled OAuth 2.1 server (incl. public dynamic client registration) | enabled | `wp_mcp_ultimate_enable_oauth` (set false to disable entirely) |
| `options/update` protected option list | theme, salts/keys, roles, `WPLANG`, etc. | `wp_mcp_ultimate_protected_options` |
| URL fetch (`media/upload`, `plugins/install-from-url`) SSRF allow-list | none (private/loopback/link-local IPs blocked) | `wp_mcp_ultimate_url_allow_hosts` |
| `system/debug-log` read cap | 2 MiB tail | `wp_mcp_ultimate_debug_log_max_bytes` |

### OAuth notes

- Authorization codes are single-use (2 min TTL); access tokens last 1 hour; refresh tokens rotate on use (30 day TTL). PKCE S256 is mandatory.
- Bearer tokens authenticate **only** the MCP REST route, not the wider `wp/v2` API.
- Dynamic client registration is unauthenticated by design (RFC 7591 public clients) but rate-limited per IP and capped; disable it with `wp_mcp_ultimate_enable_oauth` if you only use Application Passwords.
- The `client_name` shown on the consent screen is self-reported by the connecting application.