import { WP_DEFAULT_PASSWORD, WP_DEFAULT_USER, WP_ROUTES } from '@constants/wordpress-constants'

/** wp-env's development port. Everything else derives from BASE_URL. */
export const DEFAULT_BASE_URL = 'http://localhost:8894'

/**
 * Normalise BASE_URL: trim, drop trailing slashes, add a scheme if missing.
 * The scheme defaults to http, not https — wp-env serves plain HTTP and a TLS
 * failure on the first navigation reads like a plugin bug.
 */
export const BASE_URL = (() => {
  const candidate = (process.env.BASE_URL ?? DEFAULT_BASE_URL).trim()
  if (!candidate) return DEFAULT_BASE_URL
  const raw = candidate.replace(/\/+$/, '')
  return /^https?:\/\//.test(raw) ? raw : `http://${raw}`
})()

/** Browser login only. WordPress rejects this for REST Basic auth. */
export const wpUser = () => process.env.E2E_WP_USER ?? WP_DEFAULT_USER
export const wpPassword = () => process.env.E2E_WP_PASSWORD ?? WP_DEFAULT_PASSWORD

/**
 * A WordPress application password, for REST Basic auth. Not interchangeable
 * with the account password: `wp_authenticate_application_password()` compares
 * against hashed application passwords only, so the account password 401s.
 *
 * Generated per install, so it has no default. `scripts/bootstrap-wp.sh` mints
 * one and writes it to `.env`.
 */
export const wpAppPassword = (): string => {
  const value = process.env.E2E_WP_APP_PASSWORD?.trim()
  if (!value) {
    throw new Error(
      'E2E_WP_APP_PASSWORD is not set.\n' +
        'Run "bash scripts/bootstrap-wp.sh" to mint one and write it to .env, ' +
        'or set it yourself. The account password will not work: WordPress only ' +
        'accepts application passwords for REST Basic auth.',
    )
  }
  return value
}

export const restUrl = (path = '') => `${BASE_URL}${WP_ROUTES.restPosts}${path}`
export const postEditUrl = (postId: number) => `${BASE_URL}${WP_ROUTES.postEdit(postId)}`
export const permalink = (postId: number) => `${BASE_URL}${WP_ROUTES.permalink(postId)}`

/**
 * The public Ceros experience the paste-a-public-URL specs resolve, composed
 * from a base origin and a path. The host carries the account and environment
 * (`<account>.<env>.cerosdev.site`), so the base is the full origin and only
 * the path changes per experience. Defaults to a durable experience on the
 * shared `latest` dev env; override either half for another env or experience.
 */
export const CEROS_EXPERIENCE_BASE_URL = 'https://automation.latest.cerosdev.site'
export const CEROS_EXPERIENCE_PATH = '/inclusion-and-leadership'

export const cerosExperienceBaseUrl = (): string =>
  (process.env.E2E_CEROS_BASE_URL?.trim() || CEROS_EXPERIENCE_BASE_URL).replace(/\/+$/, '')

export const cerosExperiencePath = (): string => {
  const path = process.env.E2E_CEROS_EXPERIENCE_PATH?.trim() || CEROS_EXPERIENCE_PATH
  return path.startsWith('/') ? path : `/${path}`
}

/** The full public experience URL an author pastes into the block. */
export const cerosExperienceUrl = (): string =>
  `${cerosExperienceBaseUrl()}${cerosExperiencePath()}`
