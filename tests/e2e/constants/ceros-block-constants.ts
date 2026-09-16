/** Ceros-block UI strings the specs assert on, and the published content they target. */

export const BLOCK_TEXT = {
  notFoundHeading: 'Experience not found',
  notFoundBody: "possibly indicating that it's been deleted in Ceros admin",
} as const

/**
 * The published experience the browse-picker spec navigates to: a folder, then a
 * legacy Studio experience inside it (an iframe embed, so it avoids the Flex
 * inline manifest). Fixed test data.
 */
export const CEROS_PICKER_FOLDER = 'Analytics Experiences'
export const CEROS_PICKER_EXPERIENCE = 'Analytics Test Experience'
