import { defineConfig, devices } from '@playwright/test'
import { config } from 'dotenv'
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
  // One wp-env instance and one WordPress session back the whole suite, so
  // concurrent browsers just make each other time out on login.
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
  projects: [{ name: 'wordpress', use: CHROME }],
})
