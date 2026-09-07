import { BLOCK_NAME } from '@constants/wordpress-constants'
import { wpAuthFixture } from '@fixtures/wp-auth-fixture'
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

export const wpPostFixture = wpAuthFixture.extend<WpPostFixture>({
  postOptions: [{}, { option: true }],

  post: async ({ wpRequest, postOptions }, use, testInfo) => {
    const suffix = uniqueSuffix()
    const created = await createPost(wpRequest, {
      title: `${postOptions.title ?? 'e2e post'} ${suffix}`,
      content: (postOptions.blocks ?? []).join('\n\n'),
      status: postOptions.status ?? 'draft',
    })

    // WordPress re-encodes quotes on the way in. If a block was requested but
    // its attributes did not survive, the render cascade silently falls through
    // to the not-found branch and reads like a product bug.
    if (postOptions.blocks?.some((block) => block.includes(BLOCK_NAME)) && !created.attributes) {
      throw new Error(
        `[${testInfo.title}] block attributes did not survive the insert for post ${created.id}`,
      )
    }

    try {
      await use(created)
    } finally {
      await deletePost(wpRequest, created.id)
    }
  },
})
