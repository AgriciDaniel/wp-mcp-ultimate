/**
 * WordPress authentication helper for Playwright tests.
 *
 * Logs in as wp-env default admin (admin/password) and stores
 * cookies so subsequent requests are authenticated.
 */

const WP_ADMIN_USER = 'admin';
const WP_ADMIN_PASS = 'password';

/**
 * Log in to WordPress admin and return authenticated page context.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} baseURL
 */
async function wpLogin(page, baseURL) {
	await page.goto(`${baseURL}/wp-login.php`);
	await page.fill('#user_login', WP_ADMIN_USER);
	await page.fill('#user_pass', WP_ADMIN_PASS);
	await page.click('#wp-submit');
	await page.waitForURL('**/wp-admin/**');
}

/**
 * Get a nonce for REST API requests via authenticated page.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} baseURL
 * @returns {Promise<string>}
 */
async function getRestNonce(page, baseURL) {
	await page.goto(`${baseURL}/wp-admin/admin-ajax.php?action=rest-nonce`, {
		waitUntil: 'networkidle',
	});
	const text = await page.textContent('body');
	// If rest-nonce action isn't registered, fall back to extracting from page source
	if (!text || text.includes('0') || text.length < 5) {
		await page.goto(`${baseURL}/wp-admin/`);
		const nonce = await page.evaluate(() => {
			return window.wpApiSettings?.nonce || '';
		});
		return nonce;
	}
	return text.trim();
}

module.exports = { wpLogin, getRestNonce, WP_ADMIN_USER, WP_ADMIN_PASS };
