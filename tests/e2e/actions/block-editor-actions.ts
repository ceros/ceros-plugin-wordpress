import { test as base, type Page } from '@playwright/test'
import { TIMEOUTS } from '@constants/timeouts'
import { BlockEditorPage } from '@pages/block-editor.page'

/** Driver-free workflow over the editor page. */
export class BlockEditorActions {
  static for(page: Page): BlockEditorActions {
    return new BlockEditorActions(new BlockEditorPage(page))
  }

  constructor(readonly editor: BlockEditorPage) {}

  async openEditor(postId: number): Promise<BlockEditorPage> {
    await base.step(`Open the block editor -> post ${postId}`, async () => {
      await this.editor.open(postId)
      await this.editor.waitForPageLoaded()
    })

    return this.editor
  }

  /**
   * Save the post and wait for the write to land, so a later REST read sees the
   * authored attributes. The button settles into "Saved" once the write
   * completes — a reliable signal regardless of the REST URL shape.
   */
  async saveDraft(): Promise<void> {
    await base.step('Save the post', async () => {
      await this.editor.saveDraftButton.click()
      await this.editor.savedButton.waitFor({ timeout: TIMEOUTS.MEDIUM })
    })
  }
}
