import { defineConfig, devices } from '@playwright/test'
import { config } from 'dotenv'
import { AUTH_STORAGE_STATE } from '@constants/paths'
import { CONFIG_TIMEOUTS, TIMEOUTS } from '@constants/timeouts'
import { BASE_URL } from '@utils/env-utils'

config()

export { BASE_URL, TIMEOUTS }

/** The installed Google Chrome, not Playwright's bundled headless shell. */
const CHROME = {
  ...devices['Desktop Chrome'],
  channel: 'chrome',
  viewport: { width: 1920, height: 1080 },
} as const

export default defineConfig({
  testDir: './tests',
  globalSetup: require.resolve('./global-setup'),
  // Login is no longer per test: the `auth` setup project logs in once and every
  // context reuses the saved session, so parallel workers would no longer time
  // each other out on wp-login. The suite is now parallel-ready, but the actual
  // workers>1 rollout (and the mode-phasing it needs) lands separately — kept
  // serial here so this change is only the login refactor.
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: process.env.CI ? 'blob' : [['html', { open: 'never' }], ['list']],
  timeout: CONFIG_TIMEOUTS.TEST,
  globalTimeout: CONFIG_TIMEOUTS.GLOBAL,
  use: {
    baseURL: BASE_URL,
    deviceScaleFactor: 1,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    actionTimeout: CONFIG_TIMEOUTS.ACTION,
    navigationTimeout: CONFIG_TIMEOUTS.NAVIGATION,
  },
  expect: { timeout: TIMEOUTS.SHORT_MEDIUM },
  projects: [
    // Logs in once and writes AUTH_STORAGE_STATE. Everything else depends on it.
    { name: 'auth', testDir: './setup', testMatch: /auth\.setup\.ts/, use: CHROME },
    {
      name: 'wordpress',
      use: { ...CHROME, storageState: AUTH_STORAGE_STATE },
      dependencies: ['auth'],
    },
  ],
})
