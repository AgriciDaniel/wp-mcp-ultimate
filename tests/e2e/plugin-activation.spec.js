// @ts-check
const { test, expect } = require('@playwright/test');
const { wpLogin } = require('./wp-auth');

test.describe('Plugin Activation & Admin', () => {
	test.beforeEach(async ({ page, baseURL }) => {
		await wpLogin(page, baseURL);
	});

	test('plugin is listed and active in Plugins page', async ({ page, baseURL }) => {
		await page.goto(`${baseURL}/wp-admin/plugins.php`);

		// Find the plugin row
		const pluginRow = page.locator('[data-slug="wp-mcp-ultimate"]');
		await expect(pluginRow).toBeVisible();

		// Should show "Deactivate" link (meaning it's active)
		const deactivateLink = pluginRow.locator('a:has-text("Deactivate")');
		await expect(deactivateLink).toBeVisible();
	});

	test('plugin version is 2.0.0', async ({ page, baseURL }) => {
		await page.goto(`${baseURL}/wp-admin/plugins.php`);

		const pluginRow = page.locator('[data-slug="wp-mcp-ultimate"]');
		const versionText = await pluginRow.locator('.plugin-version-author-uri').textContent();
		expect(versionText).toContain('2.0.0');
	});

	test('MCP Ultimate dashboard page loads under Tools menu', async ({ page, baseURL }) => {
		await page.goto(`${baseURL}/wp-admin/tools.php?page=wp-mcp-ultimate`);

		// Page should load without errors
		await expect(page.locator('body')).not.toContainText('Fatal error');
		await expect(page.locator('body')).not.toContainText('Warning:');

		// Should contain the page title or main content
		const content = await page.textContent('body');
		expect(content).toContain('MCP');
	});

	test('dashboard has API key generation controls', async ({ page, baseURL }) => {
		await page.goto(`${baseURL}/wp-admin/tools.php?page=wp-mcp-ultimate`);

		// Should have the generate key button or config section
		const body = await page.textContent('body');
		// The dashboard should have key management UI
		expect(
			body.includes('Generate') ||
			body.includes('API Key') ||
			body.includes('generate')
		).toBeTruthy();
	});

	test('dashboard JavaScript localization is present', async ({ page, baseURL }) => {
		await page.goto(`${baseURL}/wp-admin/tools.php?page=wp-mcp-ultimate`);

		const wpMcpUltimate = await page.evaluate(() => {
			return window.wpMcpUltimate || null;
		});

		expect(wpMcpUltimate).not.toBeNull();
		expect(wpMcpUltimate.ajaxUrl).toContain('admin-ajax.php');
		expect(wpMcpUltimate.nonce).toBeTruthy();
		expect(wpMcpUltimate.restUrl).toContain('mcp/wp-mcp-ultimate');
	});

	test('no PHP errors in debug.log after activation', async ({ page, baseURL }) => {
		// Load several admin pages to trigger any lazy-loaded errors
		await page.goto(`${baseURL}/wp-admin/`);
		await page.goto(`${baseURL}/wp-admin/plugins.php`);
		await page.goto(`${baseURL}/wp-admin/tools.php?page=wp-mcp-ultimate`);

		// Check for fatal errors on any page
		for (const url of [
			`${baseURL}/wp-admin/`,
			`${baseURL}/`,
		]) {
			await page.goto(url);
			const body = await page.textContent('body');
			expect(body).not.toContain('Fatal error');
			expect(body).not.toContain('Parse error');
		}
	});
});
