import { test as base } from '@playwright/test'
import type { CerosBlock } from '@pages/modules/ceros-block'

/** Multi-step workflows over the Ceros block's authoring panels. */
export class CerosBlockActions {
  static for(block: CerosBlock): CerosBlockActions {
    return new CerosBlockActions(block)
  }

  constructor(readonly block: CerosBlock) {}

  /**
   * Paste a public experience URL, let the server resolve it, and add the
   * resolved experience to the block. Leaves the block showing its preview.
   */
  async resolveAndAddPublicUrl(url: string): Promise<void> {
    await base.step(`Resolve a public Ceros URL -> ${url}`, async () => {
      await this.block.pasteUrlInput.fill(url)
      await this.block.pasteLoadButton.click()
      await this.block.waitForPasteResult()
    })

    await base.step('Add the resolved experience', async () => {
      await this.block.pasteAddButton.click()
      await this.block.waitForPreview()
    })
  }
}
