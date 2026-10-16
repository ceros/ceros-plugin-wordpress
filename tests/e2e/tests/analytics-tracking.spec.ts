import { expect, test } from '@fixtures/fixtures'
import { BLOCK_NAME, WP_ROUTES } from '@constants/wordpress-constants'
import { PublishedPostPage } from '@pages/published-post.page'
import { unfetchableFlexBlock } from '@utils/block-serializer'
import { cerosFlexExperienceUrl } from '@utils/env-utils'
import { TAGS } from '@utils/test-tags'

const ANALYTICS = 'data-ceros-analytics'

/**
 * The Ceros analytics switch, as rendered. Every block here falls back to its
 * stored iframe embed code (see unfetchableFlexBlock), which is where render.php
 * stamps the choice with no manifest to rebuild from, so these run with no
 * network. The live delivery paths write the same attribute through the same
 * decision.
 */
test.describe('Ceros analytics tracking', { tag: [TAGS.cerosBlock, TAGS.rendered] }, () => {
  test.describe('on the published page', () => {
    // A real reader is anonymous; render the published page logged out.
    test.use({ storageState: { cookies: [], origins: [] } })

    test.describe('with the switch at its default', () => {
      test.use({
        postOptions: {
          title: 'flex analytics default',
          blocks: [unfetchableFlexBlock(cerosFlexExperienceUrl())],
          status: 'publish',
        },
      })

      test('reports', async ({ page, post }) => {
        const published = new PublishedPostPage(page)
        await published.open(post.id)
        await expect(published.iframeEmbed).toHaveAttribute(ANALYTICS, 'enabled')
      })
    })

    test.describe('with the switch off', () => {
      test.use({
        postOptions: {
          title: 'flex analytics off',
          blocks: [unfetchableFlexBlock(cerosFlexExperienceUrl(), { cerosAnalytics: false })],
          status: 'publish',
        },
      })

      test('does not report', async ({ page, post }) => {
        const published = new PublishedPostPage(page)
        await published.open(post.id)
        await expect(published.iframeEmbed).toHaveAttribute(ANALYTICS, 'disabled')
      })
    })

    test.describe('on a block saved before the manifest URL was stored', () => {
      test.use({
        postOptions: {
          title: 'flex analytics off, no manifest url',
          blocks: [
            unfetchableFlexBlock(cerosFlexExperienceUrl(), {
              cerosAnalytics: false,
              withManifestUrl: false,
            }),
          ],
          status: 'publish',
        },
      })

      test('the switch is still honoured', async ({ page, post }) => {
        const published = new PublishedPostPage(page)
        await published.open(post.id)
        await expect(published.iframeEmbed).toHaveAttribute(ANALYTICS, 'disabled')
      })
    })

    test.describe('in SSR Store mode, with the switch on', () => {
      test.use({
        postOptions: {
          title: 'flex analytics store mode',
          blocks: [
            unfetchableFlexBlock(cerosFlexExperienceUrl(), { cerosAnalytics: true, stored: true }),
          ],
          status: 'publish',
        },
      })

      // A stored copy has no reporting bundle, so Store mode never reports, even
      // when the stored copy can't be read and the block falls back.
      test('does not report', async ({ page, post }) => {
        const published = new PublishedPostPage(page)
        await published.open(post.id)
        await expect(published.iframeEmbed).toHaveAttribute(ANALYTICS, 'disabled')
      })
    })
  })

  test.describe('in a preview, with the switch at its default', () => {
    test.use({
      postOptions: {
        title: 'flex analytics preview',
        blocks: [unfetchableFlexBlock(cerosFlexExperienceUrl())],
      },
    })

    test('the editor preview does not report', async ({ post, requestUtils }) => {
      // What SsrPreview asks for: the block renderer, for this block.
      const { rendered } = await requestUtils.rest<{ rendered: string }>({
        method: 'POST',
        path: `/wp/v2/block-renderer/${BLOCK_NAME}`,
        data: {
          context: 'edit',
          attributes: post.attributes ?? {},
          post_id: post.id,
        },
      })
      expect(rendered).toContain(`${ANALYTICS}="disabled"`)
      expect(rendered).not.toContain(`${ANALYTICS}="enabled"`)
    })

    test("WordPress's post preview does not report", async ({ page, post }) => {
      // The saved session is the admin's, who can preview the draft.
      const published = new PublishedPostPage(page)
      await page.goto(`${WP_ROUTES.permalink(post.id)}&preview=true`)
      await expect(published.iframeEmbed).toHaveAttribute(ANALYTICS, 'disabled')
    })
  })
})
