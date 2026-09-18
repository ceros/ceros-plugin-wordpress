import { wpRequestUtilsFixture } from '@fixtures/wp-request-utils-fixture'
import { parseBlockAttributes } from '@utils/block-serializer'
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

    // WordPress re-encodes quotes on insert; if a block was requested with
    // attributes but they did not survive, the render silently falls through to
    // not-found. parseBlockAttributes returns {} for an attribute-less block, so
    // assert the requested keys are actually present rather than merely non-null.
    const requested = (postOptions.blocks ?? [])
      .map(parseBlockAttributes)
      .find((attrs) => attrs && Object.keys(attrs).length > 0)
    if (requested) {
      const stored = created.attributes ?? {}
      const missing = Object.keys(requested).filter((key) => !(key in stored))
      if (missing.length) {
        throw new Error(
          `[${testInfo.title}] block attributes did not survive the insert for post ${created.id} (missing: ${missing.join(', ')})`,
        )
      }
    }

    try {
      await use(created)
    } finally {
      await deletePost(requestUtils, created.id)
    }
  },
})
