import type { Locator, Page } from '@playwright/test'
import { TIMEOUTS } from '@constants/timeouts'
import { WP_ROUTES } from '@constants/wordpress-constants'
import { CerosBlock } from '@pages/modules/ceros-block'
import { EditorCanvas } from '@pages/modules/editor-canvas'
import { WelcomeGuideModal } from '@pages/modals/welcome-guide.modal'

type EditorBlock = { name: string; attributes: Record<string, unknown> }

/** Only the slice of Gutenberg's global this suite reads. */
type WordPressGlobal = {
  data: {
    select: (store: string) => { getBlocks: () => EditorBlock[] }
    dispatch: (store: string) => { set?: (scope: string, key: string, value: unknown) => void }
  }
}

/** The Gutenberg post editor, plus the pieces of it this suite drives. */
export class BlockEditorPage {
  readonly canvas: EditorCanvas
  readonly welcomeGuide: WelcomeGuideModal
  readonly cerosBlock: CerosBlock
  readonly blockToolbar: Locator
  readonly toolbarButton: (name: string) => Locator

  constructor(readonly page: Page) {
    this.canvas = new EditorCanvas(page)
    this.welcomeGuide = new WelcomeGuideModal(page)
    this.cerosBlock = new CerosBlock(this.canvas.frame)
    this.blockToolbar = page
      .getByRole('toolbar', { name: /block tools/i })
      .describe('block toolbar')
    this.toolbarButton = (name: string) =>
      this.blockToolbar.getByRole('button', { name }).describe(`toolbar button: ${name}`)
  }

  async open(postId: number): Promise<void> {
    await this.page.goto(WP_ROUTES.postEdit(postId))
  }

  async waitForPageLoaded(): Promise<void> {
    await this.welcomeGuide.dismissIfPresent()
    await this.canvas.waitForReady()
  }

  /** Read the block's stored attributes straight out of the editor store. */
  async getBlockAttributes(blockName: string): Promise<Record<string, unknown>> {
    return this.page.evaluate((name) => {
      const wp = (window as unknown as { wp: WordPressGlobal }).wp
      const found = wp.data
        .select('core/block-editor')
        .getBlocks()
        .find((block) => block.name === name)
      if (!found) throw new Error(`no ${name} block in the editor`)
      return found.attributes
    }, blockName)
  }

  /** Turn the first-run guide off in preferences so it never renders. */
  async disableWelcomeGuide(): Promise<void> {
    await this.page.evaluate(() => {
      const wp = (window as unknown as { wp?: WordPressGlobal }).wp
      wp?.data?.dispatch('core/preferences')?.set?.('core/edit-post', 'welcomeGuide', false)
    })
  }

  async waitForBlockToolbar(): Promise<void> {
    await this.blockToolbar.waitFor({ timeout: TIMEOUTS.MEDIUM })
  }
}
