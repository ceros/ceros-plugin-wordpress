import { randomBytes } from 'node:crypto'
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright'
import { AUTH_STORAGE_STATE, AUTHOR_STORAGE_STATE } from '@constants/paths'
import { TIMEOUTS } from '@constants/timeouts'
import { BASE_URL, wpPassword, wpUser } from '@utils/env-utils'
import { upsertUser } from '@utils/wp-rest-client'

const AUTHOR_USERNAME = 'e2e-author'

/**
 * Fail fast if WordPress is unreachable, then log in once over REST and persist the
 * session (cookies + nonce) to AUTH_STORAGE_STATE, for the requestUtils fixture and
 * administrator specs. Then does the same for the `e2e-author` user, created or given a
 * fresh password and role, whose session browser contexts use by default.
 */
export default async function globalSetup(): Promise<void> {
  await assertWordPressReachable()

  const requestUtils = await RequestUtils.setup({
    baseURL: BASE_URL,
    user: { username: wpUser(), password: wpPassword() },
    storageStatePath: AUTH_STORAGE_STATE,
  })
  await requestUtils.setupRest()

  const password = randomBytes(24).toString('base64url')
  const authorId = await upsertUser(requestUtils, {
    username: AUTHOR_USERNAME,
    password,
    role: 'author',
  })
  // Workers inherit it, so the post fixture can hand the author its posts.
  process.env.E2E_AUTHOR_ID = String(authorId)
  const author = await RequestUtils.setup({
    baseURL: BASE_URL,
    user: { username: AUTHOR_USERNAME, password },
    storageStatePath: AUTHOR_STORAGE_STATE,
  })
  await author.setupRest()
}

async function assertWordPressReachable(): Promise<void> {
  let detail: string
  try {
    const response = await fetch(`${BASE_URL}/wp-login.php`, {
      signal: AbortSignal.timeout(TIMEOUTS.BRIEF),
    })
    if (response.ok) return
    detail = `responded ${response.status}`
  } catch (error) {
    detail = error instanceof Error ? error.message : String(error)
  }

  throw new Error(
    `WordPress is not reachable at ${BASE_URL} (${detail}).\n` +
      `Start it with "npm run env:start" in the repository root, or point BASE_URL elsewhere.`,
  )
}
