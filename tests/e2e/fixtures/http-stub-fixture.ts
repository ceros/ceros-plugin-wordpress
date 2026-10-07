/**
 * Lets a test answer chosen outbound requests from WordPress (`stubHttp`), and removes
 * every stub after the test, even when it fails.
 */
import { test as base } from '@playwright/test'
import { clearHttpStubs, setHttpStubs, type HttpStubs } from '@utils/http-stubs'

export interface HttpStubFixture {
  /** Replaces any stubs in place, including ones a killed run left behind, with `stubs`. */
  stubHttp: (stubs: HttpStubs) => Promise<void>
}

export const httpStubFixture = base.extend<HttpStubFixture>({
  stubHttp: async ({}, use) => {
    try {
      await use(setHttpStubs)
    } finally {
      await clearHttpStubs()
    }
  },
})
