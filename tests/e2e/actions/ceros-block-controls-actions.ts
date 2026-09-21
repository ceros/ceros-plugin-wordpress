import { test as base, type Locator } from '@playwright/test'
import { CerosBlockControls } from '@pages/modules/ceros-block-controls'
import type { BlockEditorPage } from '@pages/block-editor.page'
import type { EmbedSize } from '@utils/ceros-embed'

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
  async selectBlock(): Promise<void> {
    await base.step('Wait for the placed block controls', async () => {
      await this.controls.waitForToolbar()
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

  /** Open the settings sidebar if the inspector panel is not already showing. */
  async ensureInspectorOpen(): Promise<void> {
    if (await this.controls.inspectorExperienceName.isVisible()) {
      return
    }
    await this.controls.settingsSidebarToggle.click()
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

  /** Reopen the experience picker from the inspector edit button. */
  async reopenPickerFromInspector(): Promise<void> {
    await base.step('Reopen the picker from the inspector', async () => {
      await this.ensureInspectorOpen()
      await this.controls.inspectorEditButton.click()
    })
  }
}
