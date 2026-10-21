import type { Locator, Page } from '@playwright/test'
import { WP_ROUTES } from '@constants/wordpress-constants'
import { FlexRender } from '@pages/modules/flex-render'

export type ImportMap = { imports: Record<string, string>; integrity?: Record<string, string> }

/** A published post on the front end — where the render cascade shows its work. */
export class PublishedPostPage {
  readonly flex: FlexRender
  readonly iframeEmbed: Locator
  readonly importMaps: Locator
  readonly missingExperience: Locator
  readonly missingExperienceHeading: Locator

  constructor(readonly page: Page) {
    this.flex = new FlexRender(page)
    this.iframeEmbed = page
      .locator('div[data-ceros-experience], div[data-aspectRatio] iframe.ceros-experience')
      .describe('iframe embed marker')
    this.importMaps = page.locator('script[type="importmap"]').describe('import map')
    this.missingExperience = page
      .locator('div.ceros-missing-experience')
      .describe('missing experience block')
    this.missingExperienceHeading = this.missingExperience
      .getByRole('heading', { level: 2 })
      .describe('missing experience heading')
  }

  async open(postId: number): Promise<void> {
    await this.page.goto(WP_ROUTES.permalink(postId))
  }

  async importMap(): Promise<ImportMap> {
    return JSON.parse((await this.importMaps.first().textContent()) ?? '{}') as ImportMap
  }

  /** Whether the import map comes before every module script and module preload. */
  async importMapPrecedesModules(): Promise<boolean> {
    return this.page.evaluate(() => {
      const map = document.querySelector('script[type="importmap"]')
      const modules = document.querySelectorAll('script[type="module"], link[rel="modulepreload"]')
      return (
        !!map &&
        Array.from(modules).every(
          (m) => map.compareDocumentPosition(m) & Node.DOCUMENT_POSITION_FOLLOWING,
        )
      )
    })
  }
}
