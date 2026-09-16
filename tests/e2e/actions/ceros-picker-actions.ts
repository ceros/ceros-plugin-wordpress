import { test as base } from '@playwright/test'
import { TIMEOUTS } from '@constants/timeouts'
import type { BlockEditorPage } from '@pages/block-editor.page'

/** Multi-step workflows over the Browse Experiences modal. */
export class CerosPickerActions {
  static for(editor: BlockEditorPage): CerosPickerActions {
    return new CerosPickerActions(editor)
  }

  constructor(readonly editor: BlockEditorPage) {}

  /**
   * Open the picker, drill into a folder, select an experience by name, and add
   * it. Leaves the block showing its preview.
   */
  async browseAndAdd(folderName: string, experienceName: string): Promise<void> {
    const { page, cerosBlock: block, picker } = this.editor

    await base.step('Open the experience picker', async () => {
      await block.browseExperiencesButton.click()
      await picker.waitForOpen()
    })

    await base.step(`Open folder -> ${folderName}`, async () => {
      await picker.row(folderName).click()
      // The folder's experiences load lazily; wait for the target to appear.
      await picker.row(experienceName).waitFor({ timeout: TIMEOUTS.LONG })
    })

    await base.step(`Select and add -> ${experienceName}`, async () => {
      // Selecting an experience fires the embed-codes fetch, but the Add button
      // enables on selection alone — so wait for the codes to land or Add
      // commits an empty embed and the block never previews.
      const embedCodes = page.waitForResponse((r) => r.url().includes('embed-codes') && r.ok(), {
        timeout: TIMEOUTS.LONG,
      })
      await picker.row(experienceName).click()
      await embedCodes
      await picker.addButton.click()
      await block.waitForPreview()
    })
  }
}
