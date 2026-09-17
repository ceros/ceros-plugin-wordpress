import { defineConfig, devices } from '@playwright/test'
import { config } from 'dotenv'
import { AUTH_STORAGE_STATE } from '@constants/paths'
import { CONFIG_TIMEOUTS, TIMEOUTS } from '@constants/timeouts'
import { BASE_URL, wpPassword, wpUser } from '@utils/env-utils'

config()

// The library reads WP_BASE_URL / WP_USERNAME / WP_PASSWORD at load, and its REST-root
// discovery hits WP_BASE_URL directly (not the context baseURL). Seed them before it loads.
process.env.WP_BASE_URL ||= BASE_URL
process.env.WP_USERNAME ||= wpUser()
process.env.WP_PASSWORD ||= wpPassword()
process.env.STORAGE_STATE_PATH ||= AUTH_STORAGE_STATE

export { BASE_URL, TIMEOUTS }

/** The installed Google Chrome, not Playwright's bundled headless shell. */
const CHROME = {
  ...devices['Desktop Chrome'],
  channel: 'chrome',
  viewport: { width: 1920, height: 1080 },
} as const

export default defineConfig({
  testDir: './tests',
  // Global setup logs in once and writes the session; every context reuses it via storageState.
  globalSetup: require.resolve('./global-setup'),
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: process.env.CI ? 'blob' : [['html', { open: 'never' }], ['list']],
  timeout: CONFIG_TIMEOUTS.TEST,
  globalTimeout: CONFIG_TIMEOUTS.GLOBAL,
  use: {
    baseURL: BASE_URL,
    storageState: AUTH_STORAGE_STATE,
    deviceScaleFactor: 1,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    actionTimeout: CONFIG_TIMEOUTS.ACTION,
    navigationTimeout: CONFIG_TIMEOUTS.NAVIGATION,
  },
  expect: { timeout: TIMEOUTS.SHORT_MEDIUM },
  projects: [{ name: 'wordpress', use: CHROME }],
})
