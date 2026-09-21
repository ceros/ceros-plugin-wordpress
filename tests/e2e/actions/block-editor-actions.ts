import { test as base, type Page } from '@playwright/test'
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
}
