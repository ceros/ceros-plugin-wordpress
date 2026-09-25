import { test as base, type Locator } from '@playwright/test'
import type { CerosBlock } from '@pages/modules/ceros-block'
import type { EmbedSize } from '@utils/ceros-embed'

/** Multi-step workflows over the Ceros block's authoring panels. */
export class CerosBlockActions {
  static for(block: CerosBlock): CerosBlockActions {
    return new CerosBlockActions(block)
  }

  constructor(readonly block: CerosBlock) {}

  /** Paste a public experience URL and let the server resolve it, leaving the result panel open. */
  async resolvePublicUrl(url: string): Promise<void> {
    await base.step(`Resolve a public Ceros URL -> ${url}`, async () => {
      await this.block.pasteUrlInput.fill(url)
      await this.block.pasteLoadButton.click()
      await this.block.waitForPasteResult()
    })
  }

  /** Fill the paste input and submit, without waiting for a result — for inputs the resolver rejects. */
  async submitPublicUrl(url: string): Promise<void> {
    await base.step(`Submit a public Ceros URL expecting rejection -> ${url}`, async () => {
      await this.block.pasteUrlInput.fill(url)
      await this.block.pasteLoadButton.click()
    })
  }

  /** Pick an embed size in the resolved-result panel before adding. */
  async choosePasteEmbedSize(size: EmbedSize): Promise<void> {
    await base.step(`Choose embed size in the paste panel -> ${size}`, async () => {
      const radios: Record<EmbedSize, Locator> = {
        full: this.block.pasteEmbedFullRadio,
        scroll: this.block.pasteEmbedScrollRadio,
      }
      await radios[size].check()
    })
  }

  /** Add the resolved experience, leaving the block showing its preview. */
  async addResolvedExperience(): Promise<void> {
    await base.step('Add the resolved experience', async () => {
      await this.block.pasteAddButton.click()
      await this.block.waitForPreview()
    })
  }

  /** Resolve a public URL and add it in one step. Leaves the block showing its preview. */
  async resolveAndAddPublicUrl(url: string): Promise<void> {
    await this.resolvePublicUrl(url)
    await this.addResolvedExperience()
  }
}
