/**
 * Tags for filtering runs: `npx playwright test --grep @cerosBlock`.
 * Every test.describe carries at least one.
 */
export const TAGS = {
  cerosBlock: '@cerosBlock',
  rendered: '@rendered',
  editorErrors: '@editorErrors',
} as const

export type TestTag = (typeof TAGS)[keyof typeof TAGS]
