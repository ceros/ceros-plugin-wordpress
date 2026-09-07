import { expect, test } from '@fixtures/fixtures'
import { BLOCK_TEXT } from '@constants/ceros-block-constants'
import { PublishedPostPage } from '@pages/published-post.page'
import { unresolvableCerosBlock } from '@utils/block-serializer'
import { uniqueSuffix } from '@utils/wp-rest-client'
import { TAGS } from '@utils/test-tags'

/**
 * No driver, no Ceros API, no model key. The most deterministic oracle here,
 * and it covers a state the block has regressed into before.
 */
test.describe('Published post rendering', { tag: [TAGS.cerosBlock, TAGS.rendered] }, () => {
  test.use({
    postOptions: {
      title: 'unresolvable experience',
      blocks: [unresolvableCerosBlock(uniqueSuffix())],
      status: 'publish',
    },
  })

  test('an unresolvable experience renders the not-found block', async ({ page, post }) => {
    const publishedPost = new PublishedPostPage(page)
    await publishedPost.open(post.id)

    await expect(publishedPost.missingExperience).toBeVisible()
    await expect(publishedPost.missingExperienceHeading).toHaveText(BLOCK_TEXT.notFoundHeading)
    await expect(publishedPost.missingExperience).toContainText(BLOCK_TEXT.notFoundBody)

    // Prove the cascade fell through to not-found, not merely that nothing errored.
    await expect(publishedPost.iframeEmbed).toHaveCount(0)
    await expect(publishedPost.inlineEmbed).toHaveCount(0)
  })
})
