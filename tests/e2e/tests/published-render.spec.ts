import { expect, test } from '@fixtures/fixtures'
import { PublishedPostPage } from '@pages/published-post.page'
import { resolvedLegacyStudioBlock } from '@utils/block-serializer'
import { cerosLegacyExperienceUrl } from '@utils/env-utils'
import { TAGS } from '@utils/test-tags'

/** The last path segment of the legacy experience URL — the slug the rendered iframe points at. */
const legacySlug =
  new URL(cerosLegacyExperienceUrl()).pathname.split('/').filter(Boolean).pop() ?? ''

/**
 * The published front-end render for a legacy Studio experience. render.php
 * rebuilds the scroll-proxy iframe embed from the stored experience URL at
 * request time, so a published post carrying only that URL is enough to prove
 * the iframe renders for a real reader.
 */
test.describe('Legacy Studio published render', { tag: [TAGS.cerosBlock, TAGS.rendered] }, () => {
  // A real reader is anonymous; render the published page logged out.
  test.use({ storageState: { cookies: [], origins: [] } })

  test.use({
    postOptions: {
      title: 'legacy studio experience',
      blocks: [resolvedLegacyStudioBlock(cerosLegacyExperienceUrl(), 'full')],
      status: 'publish',
    },
  })

  test('a legacy Studio experience renders its iframe embed', async ({ page, post }) => {
    const publishedPost = new PublishedPostPage(page)
    await publishedPost.open(post.id)

    await expect(publishedPost.iframeEmbed).toBeVisible()
    await expect(publishedPost.iframeEmbed).toHaveCount(1)
    expect((await publishedPost.iframeEmbed.getAttribute('src')) ?? '').toContain(legacySlug)

    // The Studio branch rendered, not the not-found or Flex-inline branches.
    await expect(publishedPost.missingExperience).toHaveCount(0)
    await expect(publishedPost.inlineEmbed).toHaveCount(0)
  })
})
