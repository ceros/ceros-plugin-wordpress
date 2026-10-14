/**
 * Reads what a Flex store wrote for the fixture post, and deletes it after the test,
 * even when the test fails. Local `wp-env` only, like the other WP-CLI fixtures.
 */
import { wpPostFixture } from '@fixtures/wp-post-fixture'
import { listStoredFiles, removeStoredFiles } from '@utils/flex-store-files'

export interface FlexStoreFixture {
  /** Lists the files stored for the fixture post, relative to its store folder. */
  storedFiles: () => Promise<string[]>
}

export const flexStoreFixture = wpPostFixture.extend<FlexStoreFixture>({
  storedFiles: async ({ post }, use) => {
    try {
      await use(() => listStoredFiles(post.id))
    } finally {
      await removeStoredFiles(post.id)
    }
  },
})
