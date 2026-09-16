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
 * The Ceros dev environment the suite targets. This is the only piece that
 * varies by deployment — the experiences, account and API host are fixed test
 * data derived from it, so pointing the whole suite at a PR env or another dev
 * env is a one-value change. Defaults to the shared `latest` env.
 *
 * Prod is a different TLD and API host, so it is intentionally not expressible
 * through these templates — this targets dev envs (see the README CI note).
 */
export const cerosEnv = (): string => process.env.E2E_CEROS_ENV?.trim() || 'latest'

/**
 * The two experiences the paste-a-public-URL specs resolve — a legacy Studio one
 * and a Flex one — both on the fixed `automation` account. Studio serves from
 * `<env>.view.cerosdev.com/<account>/<slug>` and Flex from the vanity
 * `<account>.<env>.cerosdev.site/<slug>`, so each has its own template with the
 * environment injected. The experiences themselves are fixed test data.
 */
export const cerosLegacyExperienceUrl = (): string =>
  `https://${cerosEnv()}.view.cerosdev.com/automation/analytics-test-experience`

export const cerosFlexExperienceUrl = (): string =>
  `https://automation.${cerosEnv()}.cerosdev.site/sparkboard`

/**
 * The browse-picker spec navigates to a published experience by name: a folder,
 * then the one legacy Studio experience inside it (an iframe embed, so it avoids
 * the Flex inline manifest). Fixed test data, not configuration.
 */
export const CEROS_PICKER_FOLDER = 'Analytics Experiences'
export const CEROS_PICKER_EXPERIENCE = 'Analytics Test Experience'
