import type { FrameLocator, Locator, Page } from '@playwright/test'

/**
 * What render.php emits for a Flex block, one marker per delivery mode. The root
 * is the published page, or the editor's server-rendered preview frame.
 */
export class FlexRender {
  readonly iframeEmbed: Locator
  readonly inlineEmbed: Locator
  readonly ssrEmbed: Locator

  constructor(readonly root: Page | FrameLocator) {
    this.iframeEmbed = root.locator('div[data-ceros-experience]').describe('flex iframe embed')
    this.inlineEmbed = root
      .locator('div[data-flex-inline][data-flex-manifest-url]')
      .describe('flex inline embed')
    this.ssrEmbed = root
      .locator('div.ceros-block__flex-ssr[data-flex-manifest-url]')
      .describe('flex server-rendered embed')
  }
}
