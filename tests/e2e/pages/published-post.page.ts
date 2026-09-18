import type { Locator, Page } from '@playwright/test'
import { WP_ROUTES } from '@constants/wordpress-constants'

/** A published post on the front end — where the render cascade shows its work. */
export class PublishedPostPage {
  readonly inlineEmbed: Locator
  readonly inlineManifestUrl: Locator
  readonly iframeEmbed: Locator
  readonly missingExperience: Locator
  readonly missingExperienceHeading: Locator

  constructor(readonly page: Page) {
    this.inlineEmbed = page.locator('div[data-flex-inline]').describe('inline embed marker')
    this.inlineManifestUrl = page
      .locator('div[data-flex-inline][data-flex-manifest-url]')
      .describe('inline embed with a manifest url')
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
