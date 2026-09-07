/** Block attribute vocabulary and the UI strings the specs assert on. */

export const DELIVERY_MODES = ['iframe', 'inline', 'ssr'] as const
export const SIZING_OPTIONS = ['full', 'scroll'] as const

export type DeliveryMode = (typeof DELIVERY_MODES)[number]
export type SizingOption = (typeof SIZING_OPTIONS)[number]

export const BLOCK_TEXT = {
  browseExperiences: 'Browse Experiences',
  pickerHeading: 'Browse Published Ceros Content',
  addExperience: 'Add Experience',
  replace: 'Replace',
  settingsLink: 'Go to Ceros Settings',
  apiKeyErrorHeading: 'Ceros API Key Required',
  connectionErrorHeading: 'Ceros connection error',
  notFoundHeading: 'Experience not found',
  notFoundBody: "possibly indicating that it's been deleted in Ceros admin",
} as const
