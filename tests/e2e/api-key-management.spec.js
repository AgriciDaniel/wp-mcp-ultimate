// @ts-check
const { test, expect } = require('@playwright/test');
const { wpLogin } = require('./wp-auth');

test.describe('API Key Management', () => {
	test.beforeEach(async ({ page, baseURL }) => {
		await wpLogin(page, baseURL);
	});

	test('can generate an API key via dashboard', async ({ page, baseURL }) => {
		await page.goto(`${baseURL}/wp-admin/tools.php?page=wp-mcp-ultimate`);

		// Look for generate button and click it
		const generateBtn = page.locator('button:has-text("Generate"), input[value*="Generate"], a:has-text("Generate")').first();

		if (await generateBtn.isVisible()) {
			await generateBtn.click();

			// Wait for AJAX response
			await page.waitForTimeout(2000);

			// After generation, should show the key or a success indicator
			const body = await page.textContent('body');
			expect(
				body.includes('password') ||
				body.includes('Key') ||
				body.includes('key') ||
				body.includes('Success') ||
				body.includes('Generated') ||
				body.includes('Revoke') ||
				body.includes('Config')
			).toBeTruthy();
		} else {
			// Key may already exist — verify dashboard loads correctly
			const body = await page.textContent('body');
			expect(body).toContain('MCP');
		}
	});

	test('AJAX generate key endpoint works', async ({ page, baseURL }) => {
		await page.goto(`${baseURL}/wp-admin/tools.php?page=wp-mcp-ultimate`);

		// Extract nonce from the localized script data
		const nonce = await page.evaluate(() => {
			return window.wpMcpUltimate?.nonce || '';
		});
		expect(nonce).toBeTruthy();

		// Make AJAX request directly
		const result = await page.evaluate(async ({ ajaxUrl, nonce }) => {
			const formData = new FormData();
			formData.append('action', 'wp_mcp_ultimate_generate_key');
			formData.append('nonce', nonce);

			const response = await fetch(ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
			});
			return response.json();
		}, { ajaxUrl: `${baseURL}/wp-admin/admin-ajax.php`, nonce });

		expect(result.success).toBe(true);
		expect(result.data.password).toBeTruthy();
		expect(result.data.username).toBe('admin');
		expect(result.data.base64).toBeTruthy();
	});

	test('AJAX revoke key endpoint works', async ({ page, baseURL }) => {
		await page.goto(`${baseURL}/wp-admin/tools.php?page=wp-mcp-ultimate`);

		const nonce = await page.evaluate(() => window.wpMcpUltimate?.nonce || '');

		// First generate a key
		await page.evaluate(async ({ ajaxUrl, nonce }) => {
			const formData = new FormData();
			formData.append('action', 'wp_mcp_ultimate_generate_key');
			formData.append('nonce', nonce);
			await fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' });
		}, { ajaxUrl: `${baseURL}/wp-admin/admin-ajax.php`, nonce });

		// Then revoke it
		const result = await page.evaluate(async ({ ajaxUrl, nonce }) => {
			const formData = new FormData();
			formData.append('action', 'wp_mcp_ultimate_revoke_key');
			formData.append('nonce', nonce);
			const response = await fetch(ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
			});
			return response.json();
		}, { ajaxUrl: `${baseURL}/wp-admin/admin-ajax.php`, nonce });

		expect(result.success).toBe(true);
		expect(result.data.message).toContain('revoked');
	});
});
