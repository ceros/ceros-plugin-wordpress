import { test as base } from '@playwright/test'
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright'
import { AUTH_STORAGE_STATE } from '@constants/paths'
import { BASE_URL, wpPassword, wpUser } from '@utils/env-utils'

export interface WpRequestUtilsFixture {
  /** WordPress REST helper (@wordpress/e2e-test-utils-playwright), authed by the shared session. Worker scoped. */
  requestUtils: RequestUtils
}

export const wpRequestUtilsFixture = base.extend<NonNullable<unknown>, WpRequestUtilsFixture>({
  requestUtils: [
    async ({}, use) => {
      // Loads the session global setup persisted, so REST calls carry the nonce with no per-worker login.
      const requestUtils = await RequestUtils.setup({
        baseURL: BASE_URL,
        user: { username: wpUser(), password: wpPassword() },
        storageStatePath: AUTH_STORAGE_STATE,
      })
      await use(requestUtils)
    },
    { scope: 'worker' },
  ],
})
