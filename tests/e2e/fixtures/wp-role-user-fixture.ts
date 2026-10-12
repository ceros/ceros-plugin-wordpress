/**
 * Logged-in users of a given role for permission checks: created over REST before
 * the test and deleted after it. Changes no plugin settings.
 */
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright'
import { wpRequestUtilsFixture } from '@fixtures/wp-request-utils-fixture'
import { BASE_URL } from '@utils/env-utils'
import { deleteUser, uniqueSuffix } from '@utils/wp-rest-client'

export interface WpRoleUserFixture {
  /** REST helper logged in as a new user with the Editor role, deleted after the test. */
  editorRoleUser: RequestUtils
  /** REST helper logged in as a new user with the Author role, deleted after the test. */
  authorRoleUser: RequestUtils
}

async function withRoleUser(
  requestUtils: RequestUtils,
  role: 'editor' | 'author',
  use: (user: RequestUtils) => Promise<void>,
): Promise<void> {
  const username = `e2e-${role}-${uniqueSuffix()}`
  const password = uniqueSuffix()
  const { id } = await requestUtils.createUser({
    username,
    email: `${username}@example.com`,
    password,
    roles: [role],
  })
  const roleUser = await RequestUtils.setup({
    baseURL: BASE_URL,
    user: { username, password },
  })
  try {
    await roleUser.setupRest()
    await use(roleUser)
  } finally {
    await roleUser.request.dispose()
    await deleteUser(requestUtils, id)
  }
}

export const wpRoleUserFixture = wpRequestUtilsFixture.extend<WpRoleUserFixture>({
  editorRoleUser: async ({ requestUtils }, use) => withRoleUser(requestUtils, 'editor', use),
  authorRoleUser: async ({ requestUtils }, use) => withRoleUser(requestUtils, 'author', use),
})
