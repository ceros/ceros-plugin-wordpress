import { test as setup } from '@playwright/test'
import { BlockEditorActions } from '@actions/block-editor-actions'
import { AUTH_STORAGE_STATE } from '@constants/paths'

/**
 * Log in once for the whole run and persist the browser session, so every test
 * seeds its context from the saved state (see the `storageState` project option
 * in playwright.config.ts) instead of re-driving wp-login. That is what lets the
 * suite raise the worker count without concurrent browsers stampeding the single
 * WordPress login.
 *
 * Sharing one saved session across parallel contexts is safe: WordPress keeps a
 * user's concurrent sessions valid, and every admin nonce this suite needs is
 * read fresh from the page it is on, not from the saved cookies.
 *
 * Credentials come from the environment (wpUser/wpPassword), so this runs
 * against local wp-env or any other install unchanged.
 */
/* eslint-disable-next-line playwright/expect-expect -- a login setup drives wp-login and saves the session; it asserts nothing */
setup('authenticate', async ({ page }) => {
  await BlockEditorActions.for(page).logIn()
  await page.context().storageState({ path: AUTH_STORAGE_STATE })
})
