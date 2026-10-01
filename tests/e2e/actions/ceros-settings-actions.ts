import { test as base, type Page } from '@playwright/test'
import { TIMEOUTS } from '@constants/timeouts'
import { CerosSettingsPage } from '@pages/ceros-settings.page'

export type ApiEnvironment = 'Production' | 'Staging'

/** Multi-step workflows over the Ceros settings page. */
export class CerosSettingsActions {
  static for(page: Page): CerosSettingsActions {
    return new CerosSettingsActions(new CerosSettingsPage(page))
  }

  constructor(readonly settings: CerosSettingsPage) {}

  async open(): Promise<CerosSettingsPage> {
    await base.step('Open the Ceros settings page', async () => {
      await this.settings.open()
      await this.settings.waitForLoaded()
    })

    return this.settings
  }

  /**
   * Type into the API key field. The value is set in the page rather than with
   * `fill`, so a real key never reaches a step title or the report.
   */
  async enterApiKey(key: string): Promise<void> {
    await base.step('Enter an API key', async () => {
      await this.settings.apiKeyInput.evaluate((input: HTMLInputElement, value) => {
        input.value = value
        input.dispatchEvent(new Event('input', { bubbles: true }))
      }, key)
    })
  }

  async chooseEnvironment(environment: ApiEnvironment): Promise<void> {
    await base.step(`Choose the API environment -> ${environment}`, async () => {
      await this.settings.environmentSelect.selectOption({ label: environment })
    })
  }

  /** Submit the form and wait for options.php to redirect back with its notices. */
  async save(): Promise<void> {
    await base.step('Save the settings', async () => {
      await this.settings.saveButton.click()
      await this.settings.page.waitForURL(/settings-updated=true/, { timeout: TIMEOUTS.MEDIUM })
    })
  }

  /** Confirm the removal prompt and wait for the page to reload. */
  async removeApiKey(): Promise<void> {
    await base.step('Remove the API key', async () => {
      const { page } = this.settings
      page.once('dialog', (dialog) => void dialog.accept())
      const reloaded = page.waitForEvent('load', { timeout: TIMEOUTS.MEDIUM })
      await this.settings.removeKeyButton.click()
      await reloaded
      await this.settings.waitForLoaded()
    })
  }

  /** Run the connection test and wait for its outcome to replace the spinner. */
  async testConnection(): Promise<void> {
    await base.step('Test the connection', async () => {
      await this.settings.testConnectionButton.click()
      await this.settings.testConnectionResult
        .locator('.dashicons')
        .waitFor({ timeout: TIMEOUTS.MEDIUM })
    })
  }
}
