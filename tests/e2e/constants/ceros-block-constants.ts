/** Ceros-block UI strings the specs assert on, and the published content they target. */

export const BLOCK_TEXT = {
  notFoundHeading: 'Experience not found',
  notFoundBody: "possibly indicating that it's been deleted in Ceros admin",
  pasteNonHttps: 'The experience URL must start with https://.',
  pasteBadHost: 'The experience URL host is invalid or not publicly reachable.',
  pasteEditorUrl:
    'That looks like a Ceros Studio editor URL. Publish the experience, then paste its published URL here.',
  /** Followed by the transport error. */
  pasteUnreachable: 'Could not reach the experience to verify it:',
  pasteNotCeros:
    'This URL isn’t on a recognized Ceros domain and didn’t identify itself as a Ceros experience. To embed it, add a Ceros API key and use Browse.',
  pasteManifestUntrusted:
    'This experience reported a manifest hosted outside Ceros, so it can’t be trusted.',
  pasteManifestUnavailable:
    'This is a Ceros Flex experience, but its manifest couldn’t be loaded. Please try again in a moment.',
  /** How the block reports a key the Ceros API rejects (the staging-mode technical message). */
  apiKeyRejected: 'Ceros API error (401)',
} as const

/**
 * The published experience the browse-picker spec navigates to: a folder, then a
 * legacy Studio experience inside it (an iframe embed, so it avoids the Flex
 * inline manifest). Fixed test data.
 */
export const CEROS_PICKER_FOLDER = 'Analytics Experiences'
export const CEROS_PICKER_EXPERIENCE = 'Analytics Test Experience'

/** The block's REST routes, which any user who can edit posts may call. */
export const BLOCK_ENDPOINTS = {
  resolvePublicUrl: '/ceros/v1/resolve-public-url',
} as const
