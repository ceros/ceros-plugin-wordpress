import type { Locator, Page } from '@playwright/test'
import { WP_ROUTES } from '@constants/wordpress-constants'
import { FlexRender } from '@pages/modules/flex-render'

/** A published post on the front end — where the render cascade shows its work. */
export class PublishedPostPage {
  readonly flex: FlexRender
  readonly iframeEmbed: Locator
  readonly missingExperience: Locator
  readonly missingExperienceHeading: Locator

  constructor(readonly page: Page) {
    this.flex = new FlexRender(page)
    this.iframeEmbed = page
      .locator('div[data-ceros-experience], div[data-aspectRatio] iframe.ceros-experience')
      .describe('iframe embed marker')
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
}
