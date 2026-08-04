const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
	testDir: './tests/e2e',
	timeout: 60000,
	// These specs run against a live WordPress (wp-env). In CI, run serially
	// and allow retries so a transient hiccup on a cold container (e.g. the
	// first request that lazily fires ability registration on WP 6.9) doesn't
	// fail an otherwise-correct run. Locally, keep it fast and strict.
	retries: process.env.CI ? 2 : 0,
	workers: process.env.CI ? 1 : undefined,
	use: {
		baseURL: 'http://localhost:8889',
		headless: true,
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { browserName: 'chromium' },
		},
	],
});
