/**
 * Tags for filtering runs: `npx playwright test --grep @cerosBlock`.
 * Every test.describe carries at least one.
 */
export const TAGS = {
  cerosBlock: '@cerosBlock',
  rendered: '@rendered',
  flex: '@flex',
  settings: '@settings',
  stubbedHttp: '@stubbedHttp',
} as const
