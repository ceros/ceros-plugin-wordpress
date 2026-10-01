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
