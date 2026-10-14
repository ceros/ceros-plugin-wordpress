/** Settings-page vocabulary, verbatim from the plugin. */

/** Notice codes the save path reports through `add_settings_error`. */
export const SETTINGS_NOTICES = {
  keySaved: 'ceros_api_key_valid',
  keyInvalid: 'ceros_api_key_invalid',
  stagingUrlRequired: 'ceros_staging_url_required',
} as const

export const SETTINGS_TEXT = {
  keySaved: 'API key verified and saved successfully.',
  stagingUrlRequired:
    'A staging API URL is required when using the staging environment. The key was not saved.',
  noKeyPlaceholder: 'Enter your API key',
  connectionSuccessful: 'Connection successful.',
} as const

/** A key no Ceros environment issues, for the rejection paths. */
export const INVALID_API_KEY = 'e2e-invalid-key'

/** The settings endpoints, as an Editor-role user would call them directly. */
export const SETTINGS_ENDPOINTS = {
  testConnection: '/ceros/v1/test-connection',
  adminAjax: '/wp-admin/admin-ajax.php',
  removeKeyAction: 'ceros_remove_api_key',
} as const

/** The wp-config constant bootstrap sets the key in; it wins over a saved key. */
export const API_KEY_CONSTANT = 'CEROS_API_KEY'

/** The plugin's key storage: production, staging, and the legacy option. */
export const API_KEY_OPTIONS = [
  'ceros_api_key_encrypted',
  'ceros_api_key_encrypted_staging',
  'ceros_api_key',
] as const

/** Every option the settings page writes. */
export const PLUGIN_SETTINGS_OPTIONS = [
  'ceros_api_environment',
  'ceros_staging_api_url',
  ...API_KEY_OPTIONS,
] as const
