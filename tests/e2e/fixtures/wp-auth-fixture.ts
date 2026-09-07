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
