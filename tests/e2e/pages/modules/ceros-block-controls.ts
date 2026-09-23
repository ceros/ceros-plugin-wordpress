import type { Locator, Page } from '@playwright/test'
import { TIMEOUTS } from '@constants/timeouts'

/**
 * The Ceros block's toolbar and inspector controls for a placed experience.
 *
 * Both render in the top document — the block toolbar as a popover, the
 * inspector in the editor settings sidebar — not in the canvas iframe the block
 * previews in, so the root is the page.
 *
 * Readiness gates only; assertions belong to specs.
 */
export class CerosBlockControls {
  readonly embedTypeDropdown: Locator
  readonly fullHeightMenuItem: Locator
  readonly scrollingMenuItem: Locator
  readonly replaceButton: Locator

  readonly settingsSidebarToggle: Locator
  readonly inspectorExperienceName: Locator
  readonly inspectorEditButton: Locator
  readonly inspectorFullHeightRadio: Locator
  readonly inspectorScrollingRadio: Locator

  constructor(readonly page: Page) {
    this.embedTypeDropdown = page
      .getByRole('button', { name: 'Change embed type' })
      .describe('toolbar embed type dropdown')
    this.fullHeightMenuItem = page
      .getByRole('menuitem', { name: 'Full height' })
      .describe('full height menu item')
    this.scrollingMenuItem = page
      .getByRole('menuitem', { name: 'Scrolling' })
      .describe('scrolling menu item')
    // Exact match: the inspector edit button is "Change experiences" (plural),
    // and a substring match would catch both.
    this.replaceButton = page
      .getByRole('button', { name: 'Change experience', exact: true })
      .describe('toolbar replace button')

    this.settingsSidebarToggle = page
      .getByRole('button', { name: 'Settings', exact: true })
      .describe('settings sidebar toggle')
    this.inspectorExperienceName = page
      .locator('.ceros-sidebar__file-name')
      .describe('inspector experience name')
    this.inspectorEditButton = page
      .getByRole('button', { name: 'Change experiences' })
      .describe('inspector edit button')
    // The three sidebar radio groups share role and label text; the full/scroll
    // values are unique to the iframe Settings group, so key on them.
    this.inspectorFullHeightRadio = page
      .locator('.ceros-sidebar__radio-input[value="full"]')
      .describe('inspector full height radio')
    this.inspectorScrollingRadio = page
      .locator('.ceros-sidebar__radio-input[value="scroll"]')
      .describe('inspector scrolling radio')
  }

  async waitForToolbar(): Promise<void> {
    await this.replaceButton.waitFor({ timeout: TIMEOUTS.LONG })
  }
}
