import { TIMEOUTS } from '@constants/timeouts'
import type { CerosBlockRoot } from '@pages/modules/ceros-block'
import type { Locator } from '@playwright/test'

/**
 * The "Browse Experiences" modal: the account's folder tree and the selection
 * controls. Rows lazy-load — clicking a folder loads its experiences — so the
 * actions wait for a row to appear rather than assuming the whole tree is present.
 *
 * Readiness gates only; assertions belong to specs.
 */
export class CerosExperiencePicker {
  readonly modal: Locator
  readonly body: Locator
  readonly loading: Locator
  readonly addButton: Locator

  /**
   * A tree row (folder or experience) by its display name. A folder's row holds
   * only its own name — its children render in a sibling node — so filtering the
   * row by name never matches a nested experience.
   */
  readonly row: (name: string) => Locator

  constructor(readonly root: CerosBlockRoot) {
    this.modal = root.locator('.ceros-block__modal').describe('experience picker modal')
    this.body = this.modal.locator('.ceros-block__modal-body').describe('picker body')
    this.loading = this.modal.locator('.ceros-block__loading').describe('picker loading state')
    this.addButton = this.modal
      .getByRole('button', { name: 'Add Experience' })
      .describe('add experience button')
    this.row = (name) => {
      // Anchored exact-match: a substring `hasText` would let a sibling like
      // "<name> v2" match two rows and turn a click into a strict-mode violation.
      const exact = new RegExp(`^${name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`)
      return this.modal
        .locator('.ceros-block__item')
        .filter({ hasText: exact })
        .describe(`picker row: ${name}`)
    }
  }

  async waitForOpen(): Promise<void> {
    await this.modal.waitFor({ timeout: TIMEOUTS.LONG })
  }
}
