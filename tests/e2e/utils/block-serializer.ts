import { BLOCK_NAME } from '@constants/wordpress-constants'
import type { DeliveryMode } from '@utils/ceros-embed'

export type CerosBlockAttributes = {
  experienceName: string
  experienceResourceId: string
  experienceUrl: string
  manifestUrl: string
  deliveryMode: string
  selectedOption: string
  fullHeightEmbedCode: string
  scrollableEmbedCode: string
  storedAt: string
  storedIndexPath: string
  storedPublishedAt: string
  inlineEmbedCode: string
  cerosAnalytics: boolean
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
 * A published legacy Studio block. render.php rebuilds the iframe embed from the
 * experience URL at request time, so only the URL and size need to be stored to
 * exercise the front-end render.
 */
export const resolvedLegacyStudioBlock = (
  experienceUrl: string,
  selectedOption: 'full' | 'scroll' = 'full',
): string => cerosBlock({ experienceUrl, selectedOption })

/** A placed Flex block. render.php rebuilds each delivery mode from the manifest URL alone. */
export const resolvedFlexBlock = (
  experienceName: string,
  manifestUrl: string,
  deliveryMode: DeliveryMode,
): string => cerosBlock({ experienceName, manifestUrl, deliveryMode })

/**
 * A Flex block whose manifest can't be fetched, so render.php falls back to its
 * stored iframe embed code and stamps the block's tracking choice onto it. The
 * manifest URL is http, which the fetch rejects before making a request, so the
 * render needs no network. `withManifestUrl: false` models a block saved before
 * the manifest URL was stored, which carries only the Inline snippet. `stored`
 * puts the block in SSR Store mode with a stored copy that isn't on disk, so the
 * render still falls through to the stored embed code.
 */
export const unfetchableFlexBlock = (
  experienceUrl: string,
  options: { cerosAnalytics?: boolean; withManifestUrl?: boolean; stored?: boolean } = {},
): string => {
  const manifestUrl = `${experienceUrl.replace(/^https:/, 'http:')}/manifest.v1.json`
  return cerosBlock({
    ...(options.withManifestUrl === false ? {} : { manifestUrl }),
    ...(options.stored
      ? { deliveryMode: 'ssr', storedIndexPath: 'ceros-flex/0/not-stored/v1/index.json' }
      : {}),
    inlineEmbedCode: `<div data-flex-inline data-flex-manifest-url="${manifestUrl}"></div>`,
    fullHeightEmbedCode: `<div data-embed-width="100%" data-embed-height="auto" data-ceros-experience="${experienceUrl}"></div>`,
    ...(options.cerosAnalytics === undefined ? {} : { cerosAnalytics: options.cerosAnalytics }),
  })
}

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
