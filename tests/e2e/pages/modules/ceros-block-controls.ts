import type { Locator, Page } from '@playwright/test'
import { TIMEOUTS } from '@constants/timeouts'
import type { DeliveryMode } from '@utils/ceros-embed'

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
  readonly inspectorDeliveryRadio: (mode: DeliveryMode) => Locator
  readonly storeStatus: Locator
  readonly storeButton: Locator
  readonly refreshStoredCopyButton: Locator
  readonly removeStoredCopyButton: Locator
  readonly storeError: Locator
  readonly storeUpdateHint: Locator

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
    this.inspectorDeliveryRadio = (mode: DeliveryMode) =>
      page
        .locator(`.ceros-sidebar__radio-input[value="${mode}"]`)
        .describe(`inspector ${mode} delivery radio`)

    // Store controls show in the inspector for an SSR-delivered Flex experience only.
    const store = page.locator('.ceros-sidebar__store')
    this.storeStatus = store.locator('.ceros-sidebar__store-status').describe('store status')
    this.storeButton = store
      .getByRole('button', { name: 'Fetch & store locally' })
      .describe('store locally button')
    this.refreshStoredCopyButton = store
      .getByRole('button', { name: 'Refresh stored copy' })
      .describe('refresh stored copy button')
    this.removeStoredCopyButton = store
      .getByRole('button', { name: 'Remove', exact: true })
      .describe('remove stored copy button')
    this.storeError = store.locator('.ceros-sidebar__store-error').describe('store error')
    this.storeUpdateHint = store
      .locator('.ceros-sidebar__store-update')
      .describe('stored copy update hint')
  }

  async waitForToolbar(): Promise<void> {
    await this.replaceButton.waitFor({ timeout: TIMEOUTS.LONG })
  }
}
