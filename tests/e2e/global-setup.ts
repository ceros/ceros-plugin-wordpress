import { BASE_URL } from '@utils/env-utils'

/**
 * Fail with one actionable message when WordPress is not reachable, rather than
 * letting every spec time out on its first navigation.
 *
 * This is the only global setup. The suite provisions nothing: WordPress and its
 * credentials come from the environment, so the same specs run against local
 * wp-env, another install, or one built by CI.
 */
export default async function globalSetup(): Promise<void> {
  let detail: string

  try {
    const response = await fetch(`${BASE_URL}/wp-login.php`, {
      signal: AbortSignal.timeout(5000),
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
