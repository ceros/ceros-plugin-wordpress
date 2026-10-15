import { test } from '@playwright/test'
import { WP_DEFAULT_PASSWORD, WP_DEFAULT_USER } from '@constants/wordpress-constants'

/** wp-env's development port. Everything else derives from BASE_URL. */
export const DEFAULT_BASE_URL = 'http://localhost:8888'

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

/** wp-env's admin account. Global setup signs in once; the session authenticates both browser and REST. */
export const wpUser = () => process.env.E2E_WP_USER ?? WP_DEFAULT_USER
export const wpPassword = () => process.env.E2E_WP_PASSWORD ?? WP_DEFAULT_PASSWORD

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

/** The last path segment of an experience URL, which its embed points at. */
export const experienceSlug = (url: string): string =>
  new URL(url).pathname.split('/').filter(Boolean).pop() ?? ''

export const cerosFlexExperienceUrl = (): string =>
  `https://automation.${cerosEnv()}.cerosdev.site/wp-paste-url`

/** Where the Flex experience serves its manifest, as its `x-flex-manifest` header reports. */
export const cerosFlexManifestUrl = (): string => `${cerosFlexExperienceUrl()}/manifest.v1.json`

/**
 * A path under `/ceros-e2e/` unique to the running test, for stubbed-response tests. No
 * real page or other test requests it, so a stub on it only ever answers this test.
 */
const stubPath = (file: string): string => `/ceros-e2e/${test.info().testId}/${file}`

/** A manifest URL on the Flex host that serves nothing, unique to the running test. */
export const cerosStubManifestUrl = (): string =>
  `${new URL(cerosFlexExperienceUrl()).origin}${stubPath('manifest.json')}`

/** A page URL that is not Ceros, unique to the running test. */
export const stubExperienceUrl = (): string => `https://example.com${stubPath('experience')}`

/**
 * A Ceros Studio *editor* URL (not a published experience). The resolver rejects
 * it as a non-publish URL on host/path shape, so it drives the paste-error path
 * without an outbound request. Built on the same `<env>.admin.cerosdev.com` host
 * the suite already targets so it resolves from the wp-env container.
 */
export const cerosStudioEditorUrl = (): string =>
  `https://${cerosEnv()}.admin.cerosdev.com/account/1/studio/experience/1`
