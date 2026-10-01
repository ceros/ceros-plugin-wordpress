import { test as base } from '@playwright/test'
import { CerosSettingsActions } from '@actions/ceros-settings-actions'
import type { CerosSettingsPage } from '@pages/ceros-settings.page'
import { phpString, wpCli, wpEval, wpEvalJson } from '@utils/wp-cli'

/** Where the settings page finds the API key when it opens. */
export type PluginApiKeyState =
  /** The wp-config constant bootstrap sets; the page shows it read-only. */
  | 'constant'
  /** No key anywhere; the page offers an empty key field. */
  | 'none'
  /** The provisioned key in the plugin's encrypted storage, as a save from the page leaves it. */
  | 'saved'

const KEY_CONSTANT = 'CEROS_API_KEY'

/** The plugin's key storage: production, staging, and the legacy option. */
const KEY_OPTIONS = [
  'ceros_api_key_encrypted',
  'ceros_api_key_encrypted_staging',
  'ceros_api_key',
] as const

const SETTINGS_OPTIONS = ['ceros_api_environment', 'ceros_staging_api_url', ...KEY_OPTIONS] as const

type SettingsSnapshot = {
  apiKey: string | null
  options: Record<(typeof SETTINGS_OPTIONS)[number], string | null>
}

const readSnapshot = (): Promise<SettingsSnapshot> =>
  wpEvalJson<SettingsSnapshot>(
    `[
      'apiKey' => defined(${phpString(KEY_CONSTANT)}) ? (string) ${KEY_CONSTANT} : null,
      'options' => [${SETTINGS_OPTIONS.map((name) => `${phpString(name)} => get_option(${phpString(name)}, null)`).join(', ')}],
    ]`,
  )

const requireKey = (snapshot: SettingsSnapshot): string => {
  if (!snapshot.apiKey) {
    throw new Error(
      `the settings specs need the ${KEY_CONSTANT} constant bootstrap-wp.sh sets from E2E_CEROS_API_KEY`,
    )
  }
  return snapshot.apiKey
}

async function applyKeyState(state: PluginApiKeyState, snapshot: SettingsSnapshot): Promise<void> {
  if (state === 'constant') {
    requireKey(snapshot)
    return
  }

  const key = state === 'saved' ? requireKey(snapshot) : ''
  if (snapshot.apiKey !== null) await wpCli(['config', 'delete', KEY_CONSTANT])
  await wpEval(
    `foreach ([${KEY_OPTIONS.map(phpString).join(', ')}] as $name) { delete_option($name); }` +
      (key ? ` Ceros_Encryption::save_api_key(${phpString(key)});` : ''),
    [key],
  )
}

async function restore(snapshot: SettingsSnapshot): Promise<void> {
  const options = phpString(JSON.stringify(snapshot.options))
  await wpEval(
    `foreach (json_decode(${options}, true) as $name => $value) {
      null === $value ? delete_option($name) : update_option($name, $value);
    }`,
  )
  if (snapshot.apiKey !== null) {
    await wpCli(
      ['config', 'set', KEY_CONSTANT, snapshot.apiKey, '--type=constant'],
      [snapshot.apiKey],
    )
  }
}

export interface PluginSettingsFixture {
  /** The key state the settings page opens in. */
  pluginApiKey: PluginApiKeyState
  /** Applies `pluginApiKey` before the test and puts every plugin setting back after it. */
  pluginSettings: SettingsSnapshot
  /** The key bootstrap provisioned, for specs that enter it on the settings page. */
  provisionedApiKey: string
  /** The Ceros settings page, opened once the key state is in place. */
  settingsPage: CerosSettingsPage
}

/**
 * Plugin settings are site-wide, so the page a test opens depends on state every
 * other test shares. Each test declares the state it needs, the fixture applies it
 * through wp-env, and teardown restores what was there, even when the test fails.
 *
 * This assumes one worker per WordPress instance (the config's `workers: 1`). Two
 * workers on one instance would change settings under each other's tests; scale out
 * with shards that each run their own wp-env.
 */
export const pluginSettingsFixture = base.extend<PluginSettingsFixture>({
  pluginApiKey: ['constant', { option: true }],

  pluginSettings: async ({ pluginApiKey }, use) => {
    const snapshot = await readSnapshot()
    try {
      await applyKeyState(pluginApiKey, snapshot)
      await use(snapshot)
    } finally {
      await restore(snapshot)
    }
  },

  provisionedApiKey: async ({ pluginSettings }, use) => {
    await use(requireKey(pluginSettings))
  },

  settingsPage: async ({ page, pluginSettings }, use) => {
    // Named only so the key state is in place before the page loads.
    void pluginSettings
    await use(await CerosSettingsActions.for(page).open())
  },
})
