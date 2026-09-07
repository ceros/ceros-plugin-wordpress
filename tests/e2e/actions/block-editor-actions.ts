import { test as base, type Page } from '@playwright/test'
import { BlockEditorPage } from '@pages/block-editor.page'
import { WordPressLoginPage } from '@pages/wp-login.page'
import { wpPassword, wpUser } from '@utils/env-utils'

/** Driver-free workflows over the login and editor pages. */
export class BlockEditorActions {
  static for(page: Page): BlockEditorActions {
    return new BlockEditorActions(new WordPressLoginPage(page), new BlockEditorPage(page))
  }

  constructor(
    readonly loginPage: WordPressLoginPage,
    readonly editor: BlockEditorPage,
  ) {}

  async logIn(): Promise<void> {
    await base.step(`Log in to wp-admin -> ${wpUser()}`, async () => {
      await this.loginPage.open()
      await this.loginPage.signIn(wpUser(), wpPassword())
    })
  }

  async openEditor(postId: number): Promise<BlockEditorPage> {
    await base.step(`Open the block editor -> post ${postId}`, async () => {
      await this.editor.open(postId)
      await this.editor.waitForPageLoaded()
    })

    return this.editor
  }

  async logInAndOpenEditor(postId: number): Promise<BlockEditorPage> {
    await this.logIn()
    return this.openEditor(postId)
  }
}
