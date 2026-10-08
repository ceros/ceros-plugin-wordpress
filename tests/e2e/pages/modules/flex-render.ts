import type { FrameLocator, Locator, Page } from '@playwright/test'
import type { DeliveryMode } from '@utils/ceros-embed'

/**
 * What render.php emits for a Flex block, one marker per delivery mode. The root
 * is the published page, or the editor's server-rendered preview frame.
 */
export class FlexRender {
  readonly iframeEmbed: Locator
  readonly inlineEmbed: Locator
  readonly ssrEmbed: Locator
  readonly embed: (mode: DeliveryMode) => Locator

  constructor(readonly root: Page | FrameLocator) {
    this.iframeEmbed = root.locator('div[data-ceros-experience]').describe('flex iframe embed')
    this.inlineEmbed = root.locator('div[data-flex-inline]').describe('flex inline embed')
    this.ssrEmbed = root.locator('div.ceros-block__flex-ssr').describe('flex server-rendered embed')
    this.embed = (mode: DeliveryMode) =>
      ({ iframe: this.iframeEmbed, inline: this.inlineEmbed, ssr: this.ssrEmbed })[mode]
  }
}
