import type { Locator, Page } from '@playwright/test'
import { WP_ROUTES } from '@constants/wordpress-constants'
import { CerosBlock } from '@pages/modules/ceros-block'
import { CerosExperiencePicker } from '@pages/modules/ceros-experience-picker'
import { EditorCanvas } from '@pages/modules/editor-canvas'
import { WelcomeGuideModal } from '@pages/modals/welcome-guide.modal'

/** The Gutenberg post editor, plus the pieces of it this suite drives. */
export class BlockEditorPage {
  readonly canvas: EditorCanvas
  readonly welcomeGuide: WelcomeGuideModal
  readonly cerosBlock: CerosBlock
  readonly experiencePicker: CerosExperiencePicker
  readonly saveDraftButton: Locator
  readonly savedButton: Locator

  constructor(readonly page: Page) {
    this.canvas = new EditorCanvas(page)
    this.welcomeGuide = new WelcomeGuideModal(page)
    this.cerosBlock = new CerosBlock(this.canvas.frame)
    // The picker modal is portalled to the top document, not the canvas iframe
    // the block itself renders in.
    this.experiencePicker = new CerosExperiencePicker(page)
    this.saveDraftButton = page
      .getByRole('button', { name: 'Save draft' })
      .describe('save draft button')
    this.savedButton = page.getByRole('button', { name: 'Saved' }).describe('saved indicator')
  }

  async open(postId: number): Promise<void> {
    await this.page.goto(WP_ROUTES.postEdit(postId))
  }

  async waitForPageLoaded(): Promise<void> {
    await this.welcomeGuide.dismissIfPresent()
    await this.canvas.waitForReady()
  }
}
