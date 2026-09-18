import { BLOCK_NAME } from '@constants/wordpress-constants'

export type CerosBlockAttributes = {
  experienceName: string
  experienceResourceId: string
  experienceUrl: string
  manifestUrl: string
  deliveryMode: string
  selectedOption: string
  fullHeightEmbedCode: string
  scrollableEmbedCode: string
}

/** `save.js` returns null, so post content is a self-closing comment carrying the attributes. */
export const cerosBlock = (attributes: Partial<CerosBlockAttributes> = {}): string =>
  Object.keys(attributes).length
    ? `<!-- wp:${BLOCK_NAME} ${JSON.stringify(attributes)} /-->`
    : `<!-- wp:${BLOCK_NAME} /-->`

/** A placed but unconfigured block — the editor renders its empty state. */
export const emptyCerosBlock = (): string => cerosBlock()

/** An id with no embed data, so the render cascade reaches not-found with no network. */
export const unresolvableCerosBlock = (suffix: string): string =>
  cerosBlock({
    experienceResourceId: `does-not-exist-${suffix}`,
    experienceName: 'Deleted Experience',
  })

/**
 * Read back the attributes WordPress actually stored.
 *
 * It re-encodes quotes as " on the way in, so stored content is never
 * byte-identical to what was sent. Compare parsed attributes, not raw strings.
 */
export function parseBlockAttributes(content: string): Record<string, unknown> | null {
  const match = content.match(new RegExp(`<!--\\s*wp:${BLOCK_NAME}\\s*(\\{.*?\\})?\\s*/-->`, 's'))
  if (!match) return null
  return match[1] ? (JSON.parse(match[1]) as Record<string, unknown>) : {}
}
