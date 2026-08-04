// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * OAuth 2.1 authorization server (bundled) — discovery, dynamic client
 * registration, and the security invariant that a bearer token is scoped to
 * the MCP route only.
 *
 * These run unauthenticated against the public OAuth endpoints.
 */
test.describe('OAuth - Discovery metadata', () => {
	test('authorization-server metadata is served and well-formed', async ({ request, baseURL }) => {
		const response = await request.get(`${baseURL}/.well-known/oauth-authorization-server`);
		expect(response.status()).toBe(200);
		expect(response.headers()['content-type']).toContain('application/json');

		const meta = await response.json();
		expect(meta.issuer).toBeTruthy();
		expect(meta.authorization_endpoint).toContain('/wp-mcp-oauth/authorize');
		expect(meta.token_endpoint).toContain('/wp-mcp-oauth/token');
		expect(meta.registration_endpoint).toContain('/wp-mcp-oauth/register');
		expect(meta.code_challenge_methods_supported).toContain('S256');
		expect(meta.grant_types_supported).toContain('authorization_code');
		expect(meta.grant_types_supported).toContain('refresh_token');
	});

	test('protected-resource metadata points at the MCP endpoint', async ({ request, baseURL }) => {
		const response = await request.get(`${baseURL}/.well-known/oauth-protected-resource`);
		expect(response.status()).toBe(200);

		const meta = await response.json();
		expect(meta.resource).toContain('mcp/wp-mcp-ultimate');
		expect(Array.isArray(meta.authorization_servers)).toBeTruthy();
		expect(meta.authorization_servers.length).toBeGreaterThan(0);
	});
});

test.describe('OAuth - Dynamic client registration', () => {
	test('register issues a public client for a valid https redirect_uri', async ({ request, baseURL }) => {
		const response = await request.post(`${baseURL}/wp-mcp-oauth/register`, {
			headers: { 'Content-Type': 'application/json' },
			data: {
				client_name: 'Playwright OAuth Test',
				redirect_uris: ['https://claude.ai/api/mcp/auth_callback'],
			},
		});

		expect(response.status()).toBe(201);
		const client = await response.json();
		expect(client.client_id).toBeTruthy();
		expect(client.token_endpoint_auth_method).toBe('none');
		expect(client.redirect_uris).toContain('https://claude.ai/api/mcp/auth_callback');
	});

	test('register rejects a non-loopback http redirect_uri', async ({ request, baseURL }) => {
		const response = await request.post(`${baseURL}/wp-mcp-oauth/register`, {
			headers: { 'Content-Type': 'application/json' },
			data: { redirect_uris: ['http://evil.example.com/cb'] },
		});

		expect(response.status()).toBe(400);
		const body = await response.json();
		expect(body.error).toBe('invalid_redirect_uri');
	});
});

test.describe('OAuth - Security invariants', () => {
	// The MCP endpoint must advertise where to discover the auth server on a
	// 401 (RFC 9728), so OAuth clients can bootstrap.
	test('unauthenticated MCP request carries WWW-Authenticate discovery header', async ({ request, baseURL }) => {
		const response = await request.post(`${baseURL}/wp-json/mcp/wp-mcp-ultimate`, {
			headers: { 'Content-Type': 'application/json' },
			data: { jsonrpc: '2.0', id: 1, method: 'initialize', params: {} },
		});

		expect([401, 403]).toContain(response.status());
		if (response.status() === 401) {
			const header = response.headers()['www-authenticate'] || '';
			expect(header).toContain('resource_metadata');
		}
	});

	// Regression for the bearer-scope bypass (B2): a bearer presented against
	// the MCP path but with ?rest_route= pointing at a privileged endpoint must
	// not be treated as authenticating that endpoint. /wp/v2/settings requires
	// manage_options and is always 401 for an anonymous caller — so it stays
	// 401 here, confirming the bogus token was not honored on the overridden
	// route (the old substring check would have run our bearer resolver for it).
	test('bearer token does not leak to wp/v2 via ?rest_route override', async ({ request, baseURL }) => {
		const response = await request.get(
			`${baseURL}/wp-json/mcp/wp-mcp-ultimate?rest_route=/wp/v2/settings`,
			{ headers: { Authorization: 'Bearer 00000000000000000000000000000000' } }
		);
		expect(response.status()).toBe(401);
	});
});
