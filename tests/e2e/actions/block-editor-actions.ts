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

  /** Save a published post and wait for its write; it has no "Saved" state to settle into. */
  async savePublished(postId: number): Promise<void> {
    // This post's own write, not its autosaves or another post's; the route may sit in a query string.
    const postWrite = new RegExp(`/wp/v2/posts/${postId}(?![\\d/])`)

    await base.step('Save the published post', async () => {
      const saved = this.editor.page.waitForResponse(
        (response) =>
          response.request().method() === 'POST' &&
          postWrite.test(decodeURIComponent(response.url())) &&
          response.ok(),
        { timeout: TIMEOUTS.MEDIUM },
      )
      await this.editor.saveButton.click()
      await saved
    })
  }
}
