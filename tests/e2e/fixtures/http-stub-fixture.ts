/**
 * Lets a test answer chosen outbound requests from WordPress (`stubHttp`), and removes
 * the test's stubs after it, even when it fails.
 */
import { test as base } from '@playwright/test'
import { clearHttpStubs, setHttpStubs, type HttpStubs } from '@utils/http-stubs'

export interface HttpStubFixture {
  /** Stubs each URL in `stubs`, replacing any stub a killed run left on it. */
  stubHttp: (stubs: HttpStubs) => Promise<void>
}

export const httpStubFixture = base.extend<HttpStubFixture>({
  stubHttp: async ({}, use) => {
    const urls = new Set<string>()
    try {
      await use(async (stubs) => {
        Object.keys(stubs).forEach((url) => urls.add(url))
        await setHttpStubs(stubs)
      })
    } finally {
      if (urls.size) await clearHttpStubs([...urls])
    }
  },
})
