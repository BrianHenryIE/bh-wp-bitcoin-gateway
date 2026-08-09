/**
 * External dependencies
 */
import { defineConfig, devices } from '@playwright/test';

/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
require( 'dotenv' ).config();

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig( {
	testDir: './tests/e2e-pw',
	testIgnore: '**/helpers-tests/**',
	// globalSetup: require.resolve("./global-setup"),
	globalSetup: require.resolve( './tests/e2e-pw/config/global-setup' ),
	/* Run tests in files in parallel */
	fullyParallel: true,
	/* Fail the build on CI if you accidentally left test.only in the source code. */
	forbidOnly: !! process.env.CI,
	/* Retry on CI only */
	retries: process.env.CI ? 2 : 0,
	/* Retries exist to surface flakiness, not to hide it: a test that only passes on retry still
	 * fails the build. Without this, Playwright exits 0 and CI goes green on a flaky run. */
	failOnFlakyTests: !! process.env.CI,
	/* The specs share one WordPress install and switch its theme and checkout page content at
	 * runtime, so they cannot safely run in parallel. */
	workers: 1,
	/* Reporter to use. See https://playwright.dev/docs/test-reporters */
	// `github` for inline PR annotations. Defined here rather than as a `--reporter` CLI flag
	// because the CLI flag ignores the reporter options in this file and the html report would
	// land in ./playwright-report/ instead of the fixed folder CI uploads as an artifact.
	reporter: process.env.CI
		? [
				[ 'github' ],
				[
					'html',
					{
						outputFolder: 'tests/_output/playwright-report',
						open: 'never',
					},
				],
		  ]
		: [
				[
					'html',
					{
						outputFolder: 'tests/_output/playwright-report',
						open: 'never',
					},
				],
		  ],
	// Folder for test artifacts such as screenshots, videos, traces, etc.
	outputDir: './tests/_output/playwright-results',
	// https://playwright.dev/docs/test-timeouts
	// timeout is 30 seconds by default
	// expect.timeout is 5 seconds by default
	timeout: 60_000,
	/* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
	use: {
		/* Base URL to use in actions like `await page.goto('/')`. */
		baseURL:
			process.env.BASEURL ||
			process.env.WP_BASE_URL ||
			'http://localhost:8888',

		/* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
		trace: 'on-first-retry',
	},

	/* Configure projects: each major browser split into the blocks-checkout spec vs everything
	 * else (the shortcode checkout plus the theme-agnostic admin specs), so CI can run the two
	 * groups as separate jobs, e.g. `npx playwright test --project=blocks-chromium`. */
	projects: Object.entries( {
		chromium: devices[ 'Desktop Chrome' ],
		firefox: devices[ 'Desktop Firefox' ],
		webkit: devices[ 'Desktop Safari' ],
	} ).flatMap( ( [ browserName, device ] ) => [
		{
			name: `shortcode-${ browserName }`,
			use: { ...device },
			// A project-level testIgnore replaces the top-level one, so the helpers-tests
			// (jest, not Playwright) must be repeated here.
			testIgnore: [
				'**/helpers-tests/**',
				'**/place-order-block-checkout.spec.ts',
			],
		},
		{
			name: `blocks-${ browserName }`,
			use: { ...device },
			testMatch: '**/place-order-block-checkout.spec.ts',
		},
	] ),

	/* Run your local dev server before starting the tests */
	// webServer: {
	//   command: 'npm run start',
	//   url: 'http://127.0.0.1:3000',
	//   reuseExistingServer: !process.env.CI,
	// },
} );
