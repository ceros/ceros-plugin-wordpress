import { expect, test } from '@fixtures/fixtures'
import { CerosSettingsActions } from '@actions/ceros-settings-actions'
import { BLOCK_ENDPOINTS } from '@constants/ceros-block-constants'
import {
  INVALID_API_KEY,
  SETTINGS_ENDPOINTS,
  SETTINGS_NOTICES,
  SETTINGS_TEXT,
} from '@constants/ceros-settings-constants'
import { WP_ROUTES } from '@constants/wordpress-constants'
import { TAGS } from '@utils/test-tags'

/**
 * Settings → Ceros. The key is validated against the real Ceros API on save and on
 * Test Connection. Every test changes site-wide settings, which the fixture restores.
 */
// The real key is entered on this page; a trace or video would record it.
test.use({ trace: 'off', video: 'off' })

test.describe('Plugin settings', { tag: [TAGS.settings] }, () => {
  test.describe('key in wp-config', () => {
    test.use({ pluginApiKey: 'constant' })

    test('shows the key as defined in wp-config, read-only, and tests it', async ({
      settingsPage,
    }) => {
      await expect(settingsPage.constantKeyField).toBeDisabled()
      await expect(settingsPage.apiKeyInput).toHaveCount(0)
      await expect(settingsPage.removeKeyButton).toHaveCount(0)

      await new CerosSettingsActions(settingsPage).testConnection()
      await expect(settingsPage.testConnectionResult).toHaveText(SETTINGS_TEXT.connectionSuccessful)
    })
  })

  test.describe('no key', () => {
    test.use({ pluginApiKey: 'none' })

    test('verifies and saves a valid key', async ({ settingsPage, provisionedApiKey }) => {
      const actions = new CerosSettingsActions(settingsPage)
      await actions.enterApiKey(provisionedApiKey)
      await actions.save()

      await expect(settingsPage.notice(SETTINGS_NOTICES.keySaved)).toContainText(
        SETTINGS_TEXT.keySaved,
      )
      await expect(settingsPage.keySavedStatus).toBeVisible()
      await expect(settingsPage.removeKeyButton).toBeVisible()
    })

    test('rejects a key the API does not accept', async ({ settingsPage }) => {
      const actions = new CerosSettingsActions(settingsPage)
      await actions.enterApiKey(INVALID_API_KEY)
      await actions.save()

      await expect(settingsPage.notice(SETTINGS_NOTICES.keyInvalid)).toBeVisible()
      await expect(settingsPage.keySavedStatus).toHaveCount(0)
      await expect(settingsPage.apiKeyInput).toHaveAttribute(
        'placeholder',
        SETTINGS_TEXT.noKeyPlaceholder,
      )
    })

    test('shows the staging URL only for staging, and requires it there', async ({
      settingsPage,
    }) => {
      const actions = new CerosSettingsActions(settingsPage)
      await actions.chooseEnvironment('Production')
      await expect(settingsPage.stagingUrlInput).toBeHidden()

      await actions.chooseEnvironment('Staging')
      await settingsPage.stagingUrlInput.fill('')
      await actions.enterApiKey(INVALID_API_KEY)
      await actions.save()

      await expect(settingsPage.notice(SETTINGS_NOTICES.stagingUrlRequired)).toContainText(
        SETTINGS_TEXT.stagingUrlRequired,
      )
      await expect(settingsPage.keySavedStatus).toHaveCount(0)
    })
  })

  test.describe('saved key', () => {
    test.use({ pluginApiKey: 'saved' })

    test('removes the saved key', async ({ settingsPage }) => {
      await new CerosSettingsActions(settingsPage).removeApiKey()

      await expect(settingsPage.keySavedStatus).toHaveCount(0)
      await expect(settingsPage.removeKeyButton).toHaveCount(0)
      await expect(settingsPage.apiKeyInput).toHaveAttribute(
        'placeholder',
        SETTINGS_TEXT.noKeyPlaceholder,
      )
    })

    test('the connection test reports a key the API does not accept', async ({ settingsPage }) => {
      const actions = new CerosSettingsActions(settingsPage)
      await actions.enterApiKey(INVALID_API_KEY)
      await actions.testConnection()

      await expect(settingsPage.testConnectionResult.locator('.dashicons-warning')).toBeVisible()
      await expect(settingsPage.testConnectionResult).not.toContainText(
        SETTINGS_TEXT.connectionSuccessful,
      )
    })
  })

  test.describe('as an Editor', () => {
    test('can use the block routes but not the settings page, connection test, or key removal', async ({
      editorRoleUser,
    }) => {
      // Reaching the resolver's own validation shows the session is a working Editor.
      await expect(
        editorRoleUser.rest({
          method: 'POST',
          path: BLOCK_ENDPOINTS.resolvePublicUrl,
          data: { url: 'http://view.ceros.com/x' },
        }),
      ).rejects.toMatchObject({ error_code: 'ceros_url_scheme' })

      const settings = await editorRoleUser.request.get(WP_ROUTES.cerosSettings)
      expect(settings.status()).toBe(403)

      await expect(
        editorRoleUser.rest({ method: 'POST', path: SETTINGS_ENDPOINTS.testConnection }),
      ).rejects.toMatchObject({ code: 'rest_forbidden' })

      const removal = await editorRoleUser.request.post(SETTINGS_ENDPOINTS.adminAjax, {
        form: { action: SETTINGS_ENDPOINTS.removeKeyAction },
      })
      // Refused at the nonce (403 `-1`) or the capability check (`success: false`).
      const outcome: unknown = await removal.json().catch(() => null)
      expect((outcome as { success?: boolean } | null)?.success).not.toBe(true)
    })
  })
})
