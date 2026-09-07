import type { FrameLocator, Locator, Page } from '@playwright/test'
import { EDITOR_CANVAS_FRAME } from '@constants/wordpress-constants'
import { TIMEOUTS } from '@constants/timeouts'

/** The same-origin iframe Gutenberg renders post content into. */
export class EditorCanvas {
  readonly iframe: Locator
  readonly frame: FrameLocator
  readonly firstBlock: Locator

  constructor(readonly page: Page) {
    this.iframe = page.locator(EDITOR_CANVAS_FRAME).describe('editor canvas iframe')
    this.frame = page.frameLocator(EDITOR_CANVAS_FRAME)
    this.firstBlock = this.frame.locator('.wp-block').first().describe('first block in canvas')
  }

  /** Gutenberg mounts the canvas after the editor shell, so this must gate every canvas read. */
  async waitForReady(): Promise<void> {
    await this.iframe.waitFor({ state: 'attached', timeout: TIMEOUTS.LONG })
    await this.firstBlock.waitFor({ timeout: TIMEOUTS.LONG })
  }
}
