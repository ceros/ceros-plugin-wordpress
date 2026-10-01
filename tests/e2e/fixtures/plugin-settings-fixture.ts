/**
 * Puts the plugin in the API-key state a test declares (`pluginApiKey`) and restores
 * every plugin setting afterwards, even when the test fails.
 */
import { test as base } from '@playwright/test'
import { CerosSettingsActions } from '@actions/ceros-settings-actions'
import type { CerosSettingsPage } from '@pages/ceros-settings.page'
import {
  applyApiKeyState,
  readPluginSettings,
  requireProvisionedKey,
  restorePluginSettings,
  type PluginApiKeyState,
  type PluginSettingsSnapshot,
} from '@utils/plugin-settings'

export interface PluginSettingsFixture {
  /** The key state the settings page opens in. */
  pluginApiKey: PluginApiKeyState
  /** Applies `pluginApiKey` before the test and puts every plugin setting back after it. */
  pluginSettings: PluginSettingsSnapshot
  /** The key bootstrap provisioned, for specs that enter it on the settings page. */
  provisionedApiKey: string
  /** The Ceros settings page, opened once the key state is in place. */
  settingsPage: CerosSettingsPage
}

/**
 * Assumes one worker per WordPress instance (the config's `workers: 1`). Two workers
 * on one instance would change settings under each other's tests; scale out with
 * shards that each run their own wp-env.
 */
export const pluginSettingsFixture = base.extend<PluginSettingsFixture>({
  pluginApiKey: ['constant', { option: true }],

  pluginSettings: async ({ pluginApiKey }, use) => {
    const snapshot = await readPluginSettings()
    try {
      await applyApiKeyState(pluginApiKey, snapshot)
      await use(snapshot)
    } finally {
      await restorePluginSettings(snapshot)
    }
  },

  provisionedApiKey: async ({ pluginSettings }, use) => {
    await use(requireProvisionedKey(pluginSettings))
  },

  settingsPage: async ({ page, pluginSettings }, use) => {
    // Named only so the key state is in place before the page loads.
    void pluginSettings
    await use(await CerosSettingsActions.for(page).open())
  },
})
