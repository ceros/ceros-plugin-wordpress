import { test as base, type APIRequestContext } from '@playwright/test'
import { BASE_URL, wpAppPassword, wpUser } from '@utils/env-utils'

export interface WpAuthFixture {
  /** REST context authenticated with an application password. Worker scoped. */
  wpRequest: APIRequestContext
}

export const wpAuthFixture = base.extend<NonNullable<unknown>, WpAuthFixture>({
  wpRequest: [
    async ({ playwright }, use) => {
      // Application passwords are allowed over plain HTTP because wp-env
      // reports the environment as local. Only an application password works
      // here — the account password is not valid for REST Basic auth.
      const secret = wpAppPassword()
      const context = await playwright.request.newContext({
        baseURL: BASE_URL,
        // Start from an empty jar rather than inheriting the project's
        // storageState: those cookies carry the admin's logged-in session, and
        // WordPress would then authenticate the REST write by cookie — which
        // needs an X-WP-Nonce this context has no way to send — and reject it
        // 401, ignoring the application-password header entirely.
        storageState: { cookies: [], origins: [] },
        extraHTTPHeaders: {
          Authorization: `Basic ${Buffer.from(`${wpUser()}:${secret}`).toString('base64')}`,
        },
      })
      await use(context)
      await context.dispose()
    },
    { scope: 'worker' },
  ],
})
