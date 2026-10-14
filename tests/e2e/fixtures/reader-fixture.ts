/**
 * A logged-out reader of the published site, beside the logged-in page, for tests that
 * also drive the editor. Its browser context is closed after the test.
 */
import { test as base } from '@playwright/test'
import { PublishedPostPage } from '@pages/published-post.page'
import { BASE_URL } from '@utils/env-utils'

export interface ReaderFixture {
  reader: PublishedPostPage
}

export const readerFixture = base.extend<ReaderFixture>({
  reader: async ({ browser, viewport }, use) => {
    const context = await browser.newContext({
      baseURL: BASE_URL,
      viewport,
      storageState: { cookies: [], origins: [] },
    })
    try {
      await use(new PublishedPostPage(await context.newPage()))
    } finally {
      await context.close()
    }
  },
})
