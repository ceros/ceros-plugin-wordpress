/** Read, change, and restore the plugin's site-wide settings in the local wp-env instance. */

import {
  API_KEY_CONSTANT,
  API_KEY_OPTIONS,
  PLUGIN_SETTINGS_OPTIONS,
} from '@constants/ceros-settings-constants'
import { phpString, wpCli, wpEval, wpEvalJson } from '@utils/wp-cli'

/** Where the settings page finds the API key when it opens. */
export type PluginApiKeyState =
  /** The wp-config constant bootstrap sets; the page shows it read-only. */
  | 'constant'
  /** No key anywhere; the page offers an empty key field. */
  | 'none'
  /** The provisioned key in the plugin's encrypted storage, as a save from the page leaves it. */
  | 'saved'

export type PluginSettingsSnapshot = {
  apiKey: string | null
  options: Record<(typeof PLUGIN_SETTINGS_OPTIONS)[number], string | null>
}

export const readPluginSettings = (): Promise<PluginSettingsSnapshot> =>
  wpEvalJson<PluginSettingsSnapshot>(
    `[
      'apiKey' => defined(${phpString(API_KEY_CONSTANT)}) ? (string) ${API_KEY_CONSTANT} : null,
      'options' => [${PLUGIN_SETTINGS_OPTIONS.map((name) => `${phpString(name)} => get_option(${phpString(name)}, null)`).join(', ')}],
    ]`,
  )

/** The key bootstrap provisioned, or a clear failure when bootstrap did not set one. */
export const requireProvisionedKey = (snapshot: PluginSettingsSnapshot): string => {
  if (!snapshot.apiKey) {
    throw new Error(
      `the settings specs need the ${API_KEY_CONSTANT} constant bootstrap-wp.sh sets from E2E_CEROS_API_KEY`,
    )
  }
  return snapshot.apiKey
}

export async function applyApiKeyState(
  state: PluginApiKeyState,
  snapshot: PluginSettingsSnapshot,
): Promise<void> {
  if (state === 'constant') {
    requireProvisionedKey(snapshot)
    return
  }

  const key = state === 'saved' ? requireProvisionedKey(snapshot) : ''
  if (snapshot.apiKey !== null) await wpCli(['config', 'delete', API_KEY_CONSTANT])
  await wpEval(
    `foreach ([${API_KEY_OPTIONS.map(phpString).join(', ')}] as $name) { delete_option($name); }` +
      (key ? ` Ceros_Encryption::save_api_key(${phpString(key)});` : ''),
    [key],
  )
}

export async function restorePluginSettings(snapshot: PluginSettingsSnapshot): Promise<void> {
  const options = phpString(JSON.stringify(snapshot.options))
  await wpEval(
    `foreach (json_decode(${options}, true) as $name => $value) {
      null === $value ? delete_option($name) : update_option($name, $value);
    }`,
  )
  if (snapshot.apiKey !== null) {
    await wpCli(
      ['config', 'set', API_KEY_CONSTANT, snapshot.apiKey, '--type=constant'],
      [snapshot.apiKey],
    )
  }
}
