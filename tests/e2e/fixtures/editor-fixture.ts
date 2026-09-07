import { BlockEditorActions } from '@actions/block-editor-actions'
import { wpPostFixture } from '@fixtures/wp-post-fixture'
import type { BlockEditorPage } from '@pages/block-editor.page'

export interface EditorFixture {
  /** Logged in, editor open on the fixture post, canvas ready. */
  editor: BlockEditorPage
}

export const editorFixture = wpPostFixture.extend<EditorFixture>({
  editor: async ({ page, post }, use, testInfo) => {
    try {
      const editor = await BlockEditorActions.for(page).logInAndOpenEditor(post.id)
      await use(editor)
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error)
      throw new Error(`[${testInfo.title}] failed to open the editor: ${message}`)
    }
  },
})
