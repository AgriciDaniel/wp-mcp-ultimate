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