import { BLOCK_NAME } from '@constants/wordpress-constants'
import { wpRequestUtilsFixture } from '@fixtures/wp-request-utils-fixture'
import { createPost, deletePost, uniqueSuffix, type CreatedPost } from '@utils/wp-rest-client'

export type PostOptions = {
  title?: string
  /** Serialized blocks, built with utils/block-serializer. */
  blocks?: string[]
  status?: 'draft' | 'publish'
}

export interface WpPostFixture {
  postOptions: PostOptions
  post: CreatedPost
}

export const wpPostFixture = wpRequestUtilsFixture.extend<WpPostFixture>({
  postOptions: [{}, { option: true }],

  post: async ({ requestUtils, postOptions }, use, testInfo) => {
    const suffix = uniqueSuffix()
    const created = await createPost(requestUtils, {
      title: `${postOptions.title ?? 'e2e post'} ${suffix}`,
      content: (postOptions.blocks ?? []).join('\n\n'),
      status: postOptions.status ?? 'draft',
    })

    // WordPress re-encodes quotes on insert; if a requested block's attributes
    // did not survive, the render falls through to the not-found branch.
    if (postOptions.blocks?.some((block) => block.includes(BLOCK_NAME)) && !created.attributes) {
      throw new Error(
        `[${testInfo.title}] block attributes did not survive the insert for post ${created.id}`,
      )
    }

    try {
      await use(created)
    } finally {
      await deletePost(requestUtils, created.id)
    }
  },
})
