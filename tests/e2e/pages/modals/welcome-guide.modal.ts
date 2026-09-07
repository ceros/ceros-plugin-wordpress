import type { Locator, Page } from '@playwright/test'
import { TIMEOUTS } from '@constants/timeouts'

/**
 * Gutenberg's first-run guide.
 *
 * It intercepts pointer events, so any click on the block underneath hangs
 * until the test times out — while visibility assertions still pass. That
 * asymmetry makes assertion-only specs go green and the first click-based spec
 * burn its whole timeout looking like a slow page.
 */
export class WelcomeGuideModal {
  readonly dialog: Locator
  readonly closeButton: Locator

  constructor(readonly page: Page) {
    this.dialog = page
      .getByRole('dialog', { name: /welcome to the editor/i })
      .describe('welcome guide dialog')
    // The button is named exactly "Close".
    this.closeButton = this.dialog.getByRole('button', { name: 'Close' }).describe('close button')
  }

  async dismissIfPresent(): Promise<void> {
    try {
      await this.dialog.waitFor({ state: 'visible', timeout: TIMEOUTS.MICRO * 10 })
    } catch {
      return
    }
    await this.closeButton.click()
    await this.dialog.waitFor({ state: 'hidden', timeout: TIMEOUTS.SHORT })
  }
}
