import type { Locator, Page } from '@playwright/test'
import { WP_ROUTES } from '@constants/wordpress-constants'

/** Settings → Ceros: the API key, the API environment, and the connection test. */
export class CerosSettingsPage {
  readonly heading: Locator
  readonly apiKeyInput: Locator
  readonly constantKeyField: Locator
  readonly keySavedStatus: Locator
  readonly removeKeyButton: Locator
  readonly environmentSelect: Locator
  readonly stagingUrlInput: Locator
  readonly testConnectionButton: Locator
  readonly testConnectionResult: Locator
  readonly saveButton: Locator

  /** A settings notice, which core renders as `#setting-error-<code>`. */
  readonly notice: (code: string) => Locator

  constructor(readonly page: Page) {
    this.heading = page
      .getByRole('heading', { name: 'Ceros Settings', level: 1 })
      .describe('settings heading')
    this.apiKeyInput = page.getByLabel('API Key', { exact: true }).describe('API key input')
    // The read-only field has no id, so its label does not reach it.
    this.constantKeyField = page
      .locator('input[disabled][value="Defined in wp-config.php"]')
      .describe('API key defined in wp-config')
    this.keySavedStatus = page
      .getByText('API key is verified, configured, and encrypted.')
      .describe('key saved status')
    this.removeKeyButton = page
      .getByRole('button', { name: 'Remove API Key' })
      .describe('remove API key button')
    this.environmentSelect = page
      .getByLabel('API Environment', { exact: true })
      .describe('API environment select')
    this.stagingUrlInput = page
      .getByLabel('Staging API URL', { exact: true })
      .describe('staging API URL input')
    this.testConnectionButton = page
      .getByRole('button', { name: 'Test Connection' })
      .describe('test connection button')
    // A bare status span with no role or label.
    this.testConnectionResult = page
      .locator('#ceros-test-result')
      .describe('test connection result')
    this.saveButton = page
      .getByRole('button', { name: 'Save Changes' })
      .describe('save changes button')
    this.notice = (code) =>
      page.locator(`#setting-error-${code}`).describe(`settings notice: ${code}`)
  }

  async open(): Promise<void> {
    await this.page.goto(WP_ROUTES.cerosSettings)
  }

  async waitForLoaded(): Promise<void> {
    await this.heading.waitFor()
  }
}
