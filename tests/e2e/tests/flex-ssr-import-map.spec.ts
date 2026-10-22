import { expect, test } from '@fixtures/fixtures'
import { TIMEOUTS } from '@constants/timeouts'
import { PublishedPostPage } from '@pages/published-post.page'
import { resolvedFlexBlock } from '@utils/block-serializer'
import { expectImportMapCarries, readManifestImportMap } from '@utils/ceros-embed'
import { cerosFlexExperienceUrl, cerosFlexManifestUrl, experienceSlug } from '@utils/env-utils'
import { TAGS } from '@utils/test-tags'

const manifestUrl = cerosFlexManifestUrl()

/** WordPress prints one import map for the page; the SSR runtime's imports join it. */
test.describe('Flex SSR import map', { tag: [TAGS.cerosBlock, TAGS.rendered, TAGS.flex] }, () => {
  // A real reader is anonymous; render the published page logged out.
  test.use({ storageState: { cookies: [], origins: [] } })

  test.use({
    postOptions: {
      title: 'flex ssr import map',
      blocks: [resolvedFlexBlock(experienceSlug(cerosFlexExperienceUrl()), manifestUrl, 'ssr')],
      status: 'publish',
    },
  })

  test("the import map leads the modules and carries the experience's imports", async ({
    page,
    post,
    request,
  }) => {
    const pageErrors: Error[] = []
    page.on('pageerror', (error) => pageErrors.push(error))
    const manifestMap = await readManifestImportMap(request, manifestUrl)

    const publishedPost = new PublishedPostPage(page)
    await publishedPost.open(post.id)

    // The runtime loads from the Ceros CDN, slower on a cold edge after a deploy.
    await expect(publishedPost.flex.ssrHydrated).toHaveCount(1, { timeout: TIMEOUTS.MEDIUM })
    await expectImportMapCarries(publishedPost, manifestMap)
    expect(pageErrors).toEqual([])
  })
})
