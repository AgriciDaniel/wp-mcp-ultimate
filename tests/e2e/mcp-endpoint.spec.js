// @ts-check
const { test, expect } = require('@playwright/test');
const { WP_ADMIN_USER } = require('./wp-auth');

// Application password created via: wp user application-password create 1 "playwright-test"
const APP_PASSWORD = 'RuSuYH1rubGKlhkzq9yXaWlU';

/**
 * Helper: Initialize an MCP session and return the session ID.
 */
async function mcpInitialize(request, baseURL) {
	const auth = Buffer.from(`${WP_ADMIN_USER}:${APP_PASSWORD}`).toString('base64');

	const response = await request.post(`${baseURL}/wp-json/mcp/wp-mcp-ultimate`, {
		headers: {
			'Content-Type': 'application/json',
			'Authorization': `Basic ${auth}`,
		},
		data: {
			jsonrpc: '2.0',
			id: 1,
			method: 'initialize',
			params: {
				protocolVersion: '2025-06-18',
				capabilities: {},
				clientInfo: { name: 'playwright-test', version: '1.0.0' },
			},
		},
	});

	const sessionId = response.headers()['mcp-session-id'];
	const body = await response.json();
	return { sessionId, body, status: response.status() };
}

/**
 * Helper: Make authenticated JSON-RPC request to the MCP endpoint with session.
 */
async function mcpRequest(request, baseURL, sessionId, method, params = {}, id = 2) {
	const auth = Buffer.from(`${WP_ADMIN_USER}:${APP_PASSWORD}`).toString('base64');

	const headers = {
		'Content-Type': 'application/json',
		'Authorization': `Basic ${auth}`,
	};

	if (sessionId) {
		headers['Mcp-Session-Id'] = sessionId;
	}

	const response = await request.post(`${baseURL}/wp-json/mcp/wp-mcp-ultimate`, {
		headers,
		data: {
			jsonrpc: '2.0',
			id,
			method,
			params,
		},
	});

	return response;
}

test.describe('MCP Endpoint - Protocol Compliance', () => {
	test('MCP endpoint exists and rejects unauthenticated requests', async ({ request, baseURL }) => {
		const response = await request.post(`${baseURL}/wp-json/mcp/wp-mcp-ultimate`, {
			headers: { 'Content-Type': 'application/json' },
			data: {
				jsonrpc: '2.0',
				id: 1,
				method: 'initialize',
				params: {},
			},
		});

		const status = response.status();
		expect([401, 403]).toContain(status);
	});

	test('MCP initialize handshake works', async ({ request, baseURL }) => {
		const { status, body, sessionId } = await mcpInitialize(request, baseURL);

		expect(status).toBe(200);
		expect(body.jsonrpc).toBe('2.0');
		expect(body.id).toBe(1);

		const result = body.result;
		expect(result).toBeDefined();
		expect(result.protocolVersion).toBe('2025-06-18');
		expect(result.serverInfo).toBeDefined();
		expect(result.serverInfo.name).toContain('MCP');
		expect(result.serverInfo.version).toBe('2.0.0');
		expect(result.capabilities).toBeDefined();

		// Should return a session ID header
		expect(sessionId).toBeTruthy();
	});

	test('MCP tools/list returns available tools', async ({ request, baseURL }) => {
		const { sessionId } = await mcpInitialize(request, baseURL);

		const response = await mcpRequest(request, baseURL, sessionId, 'tools/list', {}, 2);
		expect(response.status()).toBe(200);

		const body = await response.json();
		expect(body.jsonrpc).toBe('2.0');

		const tools = body.result?.tools;
		expect(Array.isArray(tools)).toBeTruthy();

		const toolNames = tools.map((t) => t.name);
		expect(toolNames).toContain('wp-mcp-ultimate-discover-abilities');
		expect(toolNames).toContain('wp-mcp-ultimate-get-ability-info');
		expect(toolNames).toContain('wp-mcp-ultimate-execute-ability');
	});

	test('MCP tools/list tools have proper schemas', async ({ request, baseURL }) => {
		const { sessionId } = await mcpInitialize(request, baseURL);

		const response = await mcpRequest(request, baseURL, sessionId, 'tools/list', {}, 2);
		const body = await response.json();
		const tools = body.result?.tools || [];

		expect(tools.length).toBe(3);

		for (const tool of tools) {
			expect(tool.name).toBeTruthy();
			expect(tool.description).toBeTruthy();
			expect(tool.inputSchema).toBeDefined();
			expect(tool.inputSchema.type).toBe('object');
		}
	});
});

test.describe('MCP Endpoint - Tool Execution', () => {
	test('discover-abilities returns list of abilities', async ({ request, baseURL }) => {
		const { sessionId } = await mcpInitialize(request, baseURL);

		const response = await mcpRequest(request, baseURL, sessionId, 'tools/call', {
			name: 'wp-mcp-ultimate-discover-abilities',
			arguments: {},
		}, 3);

		expect(response.status()).toBe(200);

		const body = await response.json();
		expect(body.jsonrpc).toBe('2.0');
		expect(body.result).toBeDefined();

		const content = body.result?.content;
		expect(Array.isArray(content)).toBeTruthy();
		expect(content.length).toBeGreaterThan(0);

		const textContent = content.find((c) => c.type === 'text');
		expect(textContent).toBeDefined();

		const parsed = JSON.parse(textContent.text);
		// The result could be an object with abilities array, or directly an array
		const abilities = Array.isArray(parsed) ? parsed : parsed.abilities;
		expect(Array.isArray(abilities)).toBeTruthy();
		// Should have at least 57 abilities (our 57 + the 3 MCP core)
		expect(abilities.length).toBeGreaterThanOrEqual(57);
	});

	test('get-ability-info returns details for a specific ability', async ({ request, baseURL }) => {
		const { sessionId } = await mcpInitialize(request, baseURL);

		const response = await mcpRequest(request, baseURL, sessionId, 'tools/call', {
			name: 'wp-mcp-ultimate-get-ability-info',
			arguments: { ability_name: 'content/list-posts' },
		}, 3);

		const body = await response.json();
		expect(body.result).toBeDefined();

		const content = body.result?.content;
		expect(Array.isArray(content)).toBeTruthy();

		const textContent = content.find((c) => c.type === 'text');
		expect(textContent).toBeDefined();

		const info = JSON.parse(textContent.text);
		expect(info.name).toContain('list-posts');
	});

	test('execute-ability runs content/search with fixed pagination', async ({ request, baseURL }) => {
		const { sessionId } = await mcpInitialize(request, baseURL);

		const response = await mcpRequest(request, baseURL, sessionId, 'tools/call', {
			name: 'wp-mcp-ultimate-execute-ability',
			arguments: {
				ability_name: 'content/search',
				parameters: { query: 'hello', per_page: 5 },
			},
		}, 4);

		const body = await response.json();
		expect(body.result).toBeDefined();

		const content = body.result?.content;
		const textContent = content?.find((c) => c.type === 'text');
		expect(textContent).toBeDefined();

		const result = JSON.parse(textContent.text);
		// ExecuteAbilityAbility wraps result in {success, data}
		expect(result.success).toBe(true);
		// Verify our v2.0.0 bug fix: pagination fields present under data
		expect(result.data).toHaveProperty('results');
		expect(result.data).toHaveProperty('returned');
		expect(result.data).toHaveProperty('total');
		expect(result.data).toHaveProperty('has_more');
		expect(typeof result.data.returned).toBe('number');
		expect(typeof result.data.total).toBe('number');
	});

	test('execute-ability runs options/get with fixed case preservation', async ({ request, baseURL }) => {
		const { sessionId } = await mcpInitialize(request, baseURL);

		const response = await mcpRequest(request, baseURL, sessionId, 'tools/call', {
			name: 'wp-mcp-ultimate-execute-ability',
			arguments: {
				ability_name: 'options/get',
				parameters: { name: 'blogname' },
			},
		}, 5);

		const body = await response.json();
		expect(body.result).toBeDefined();

		const content = body.result?.content;
		const textContent = content?.find((c) => c.type === 'text');
		const result = JSON.parse(textContent.text);

		// ExecuteAbilityAbility wraps result in {success, data}
		expect(result.success).toBe(true);
		expect(result.data.name).toBe('blogname');
		expect(result.data.value).toBeTruthy();
	});

	test('execute-ability runs plugins/list', async ({ request, baseURL }) => {
		const { sessionId } = await mcpInitialize(request, baseURL);

		const response = await mcpRequest(request, baseURL, sessionId, 'tools/call', {
			name: 'wp-mcp-ultimate-execute-ability',
			arguments: {
				ability_name: 'plugins/list',
				parameters: {},
			},
		}, 6);

		const body = await response.json();
		const content = body.result?.content;
		const textContent = content?.find((c) => c.type === 'text');
		expect(textContent).toBeDefined();

		const result = JSON.parse(textContent.text);
		expect(result.success).toBe(true);
		// plugins/list returns {plugins: [...], total: N} wrapped in data
		const plugins = result.data.plugins;
		expect(Array.isArray(plugins)).toBeTruthy();
		expect(plugins.length).toBeGreaterThan(0);
		// Our plugin should be in the list
		const ourPlugin = plugins.find((p) => p.name === 'WP MCP Ultimate');
		expect(ourPlugin).toBeDefined();
		expect(ourPlugin.active).toBe(true);
	});
});

test.describe('MCP Endpoint - Error Handling', () => {
	test('invalid JSON-RPC method returns error', async ({ request, baseURL }) => {
		const { sessionId } = await mcpInitialize(request, baseURL);

		const response = await mcpRequest(request, baseURL, sessionId, 'nonexistent/method', {});
		const body = await response.json();

		expect(body.error).toBeDefined();
		expect(body.error.code).toBeDefined();
	});

	test('execute-ability with non-existent ability returns error', async ({ request, baseURL }) => {
		const { sessionId } = await mcpInitialize(request, baseURL);

		const response = await mcpRequest(request, baseURL, sessionId, 'tools/call', {
			name: 'wp-mcp-ultimate-execute-ability',
			arguments: {
				ability_name: 'nonexistent/ability',
				parameters: {},
			},
		}, 7);

		const body = await response.json();

		// Permission denied errors return as isError tool results (MCP spec)
		if (body.result?.isError) {
			const content = body.result.content;
			const textContent = content?.find((c) => c.type === 'text');
			expect(textContent).toBeDefined();
			expect(textContent.text).toContain('not found');
		} else if (body.error) {
			// Or as a JSON-RPC error
			expect(body.error.code).toBeDefined();
		} else {
			// Or as a success=false result
			const content = body.result?.content;
			const textContent = content?.find((c) => c.type === 'text');
			const result = JSON.parse(textContent.text);
			expect(result.success).toBe(false);
		}
	});

	test('malformed JSON-RPC request is handled gracefully', async ({ request, baseURL }) => {
		const auth = Buffer.from(`${WP_ADMIN_USER}:${APP_PASSWORD}`).toString('base64');

		const response = await request.post(`${baseURL}/wp-json/mcp/wp-mcp-ultimate`, {
			headers: {
				'Content-Type': 'application/json',
				'Authorization': `Basic ${auth}`,
			},
			data: { invalid: 'not json-rpc' },
		});

		// Should not crash
		const status = response.status();
		expect(status).toBeLessThan(500);
	});
});
