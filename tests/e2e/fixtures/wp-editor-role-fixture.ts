/**
 * A logged-in Editor-role user for permission checks: created over REST before the
 * test and deleted after it. Changes no plugin settings.
 */
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright'
import { wpRequestUtilsFixture } from '@fixtures/wp-request-utils-fixture'
import { BASE_URL } from '@utils/env-utils'
import { deleteUser, uniqueSuffix } from '@utils/wp-rest-client'

export interface WpEditorRoleFixture {
  /** REST helper logged in as a new user with the Editor role, deleted after the test. */
  editorRoleUser: RequestUtils
}

export const wpEditorRoleFixture = wpRequestUtilsFixture.extend<WpEditorRoleFixture>({
  editorRoleUser: async ({ requestUtils }, use) => {
    const username = `e2e-editor-${uniqueSuffix()}`
    const password = uniqueSuffix()
    const { id } = await requestUtils.createUser({
      username,
      email: `${username}@example.com`,
      password,
      roles: ['editor'],
    })
    const editorRoleUser = await RequestUtils.setup({
      baseURL: BASE_URL,
      user: { username, password },
    })
    try {
      await editorRoleUser.setupRest()
      await use(editorRoleUser)
    } finally {
      await editorRoleUser.request.dispose()
      await deleteUser(requestUtils, id)
    }
  },
})
