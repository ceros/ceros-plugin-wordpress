import { test as base, type Locator } from '@playwright/test'
import { TIMEOUTS } from '@constants/timeouts'
import { BLOCK_NAME } from '@constants/wordpress-constants'
import { CerosBlockControls } from '@pages/modules/ceros-block-controls'
import type { BlockEditorPage } from '@pages/block-editor.page'
import type { DeliveryMode, EmbedSize } from '@utils/ceros-embed'

/** The slice of the editor's `wp` global `selectSeededBlock` uses. */
type WpGlobal = {
  wp?: {
    data: {
      select: (store: 'core/block-editor') => {
        getBlocks: () => { name: string; clientId: string }[]
      }
      dispatch: (store: 'core/block-editor') => { selectBlock: (clientId: string) => void }
    }
  }
}

/** Multi-step workflows over a placed experience's toolbar and inspector controls. */
export class CerosBlockControlsActions {
  readonly controls: CerosBlockControls

  static for(editor: BlockEditorPage): CerosBlockControlsActions {
    return new CerosBlockControlsActions(editor)
  }

  constructor(readonly editor: BlockEditorPage) {
    this.controls = new CerosBlockControls(editor.page)
  }

  /** Wait for the placed block's controls. The block stays selected after Add, so this only gates on the toolbar. */
  async waitForPlacedControls(): Promise<void> {
    await base.step('Wait for the placed block controls', async () => {
      await this.controls.waitForToolbar()
    })
  }

  /**
   * Select a block the post was seeded with, so its controls show, once the editor has
   * loaded it. Through the editor's data store: a click on a Flex block lands in its
   * preview iframe and selects nothing.
   */
  async selectSeededBlock(): Promise<void> {
    await base.step('Select the seeded block', async () => {
      await this.editor.page.waitForFunction(
        (name) => {
          const data = (window as unknown as WpGlobal).wp?.data
          const block = data
            ?.select('core/block-editor')
            .getBlocks()
            .find((candidate) => candidate.name === name)
          if (!data || !block) return false
          data.dispatch('core/block-editor').selectBlock(block.clientId)
          return true
        },
        BLOCK_NAME,
        { timeout: TIMEOUTS.LONG },
      )
      await this.waitForPlacedControls()
    })
  }

  /** Pick an embed size from the toolbar dropdown. */
  async chooseEmbedSizeFromToolbar(size: EmbedSize): Promise<void> {
    await base.step(`Choose embed size from the toolbar -> ${size}`, async () => {
      await this.controls.embedTypeDropdown.click()
      const items: Record<EmbedSize, Locator> = {
        full: this.controls.fullHeightMenuItem,
        scroll: this.controls.scrollingMenuItem,
      }
      await items[size].click()
    })
  }

  /** Reopen the experience picker from the toolbar Replace button. */
  async reopenPickerFromToolbar(): Promise<void> {
    await base.step('Reopen the picker from the toolbar', async () => {
      await this.controls.replaceButton.click()
    })
  }

  /** Show the block inspector: open the settings sidebar if it is closed, then wait for the panel. */
  async ensureInspectorOpen(): Promise<void> {
    if (await this.controls.inspectorExperienceName.isVisible()) {
      return
    }
    // Toggle only when the sidebar is closed; clicking the toggle on an already-open
    // sidebar would hide it.
    if ((await this.controls.settingsSidebarToggle.getAttribute('aria-pressed')) !== 'true') {
      await this.controls.settingsSidebarToggle.click()
    }
    await this.controls.inspectorExperienceName.waitFor()
  }

  /** Pick an embed size from the inspector Settings radios. */
  async chooseEmbedSizeFromInspector(size: EmbedSize): Promise<void> {
    await base.step(`Choose embed size from the inspector -> ${size}`, async () => {
      await this.ensureInspectorOpen()
      const radios: Record<EmbedSize, Locator> = {
        full: this.controls.inspectorFullHeightRadio,
        scroll: this.controls.inspectorScrollingRadio,
      }
      await radios[size].check()
    })
  }

  /** Pick a delivery mode from the inspector. Offered for Flex experiences only. */
  async chooseDeliveryModeFromInspector(mode: DeliveryMode): Promise<void> {
    await base.step(`Choose delivery mode from the inspector -> ${mode}`, async () => {
      await this.ensureInspectorOpen()
      await this.controls.inspectorDeliveryRadio(mode).check()
    })
  }

  /** Select the seeded block and show its Store controls in the inspector. */
  async openStoreControls(): Promise<void> {
    await this.selectSeededBlock()
    await this.ensureInspectorOpen()
  }

  /** Store the experience on the site from the inspector, waiting until the store succeeds or fails. */
  async storeLocally(): Promise<void> {
    await base.step('Store the experience locally', async () => {
      await this.ensureInspectorOpen()
      await this.controls.storeButton.click()
      // The button relabels once the store has written the stored attributes.
      await this.controls.refreshStoredCopyButton
        .or(this.controls.storeError)
        .waitFor({ timeout: TIMEOUTS.LONG })
    })
  }

  /** Drop the stored copy from the inspector, so the block renders live again. */
  async removeStoredCopy(): Promise<void> {
    await base.step('Remove the stored copy', async () => {
      await this.ensureInspectorOpen()
      await this.controls.removeStoredCopyButton.click()
    })
  }

  /** Reopen the experience picker from the inspector edit button. */
  async reopenPickerFromInspector(): Promise<void> {
    await base.step('Reopen the picker from the inspector', async () => {
      await this.ensureInspectorOpen()
      await this.controls.inspectorEditButton.click()
    })
  }
}
