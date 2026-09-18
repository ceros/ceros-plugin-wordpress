import { RequestUtils } from '@wordpress/e2e-test-utils-playwright'
import { AUTH_STORAGE_STATE } from '@constants/paths'
import { TIMEOUTS } from '@constants/timeouts'
import { BASE_URL, wpPassword, wpUser } from '@utils/env-utils'

/**
 * Fail fast if WordPress is unreachable, then log in once over REST and persist the
 * session (cookies + nonce) to AUTH_STORAGE_STATE. Browser contexts reuse it via
 * `storageState`; the requestUtils fixture loads it for REST.
 */
export default async function globalSetup(): Promise<void> {
  await assertWordPressReachable()

  const requestUtils = await RequestUtils.setup({
    baseURL: BASE_URL,
    user: { username: wpUser(), password: wpPassword() },
    storageStatePath: AUTH_STORAGE_STATE,
  })
  await requestUtils.setupRest()
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
