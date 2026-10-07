import { expect, type Locator } from '@playwright/test'
import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright'
import { readStoredBlockAttributes } from '@utils/wp-rest-client'

/** The two iframe embed sizes the block stores as `selectedOption`. */
export type EmbedSize = 'full' | 'scroll'

/** How a Flex experience is delivered, stored as `deliveryMode`. */
export type DeliveryMode = 'iframe' | 'inline' | 'ssr'

/** The class the legacy Studio (scroll-proxy) iframe carries; present in every stored Studio embed code. */
export const STUDIO_EMBED_MARKER = 'class="ceros-experience"'

/** The attribute every Flex inline snippet carries. */
export const FLEX_INLINE_MARKER = 'data-flex-inline'

/** A pattern that requires a non-empty value containing the fragment; an empty fragment just requires non-empty. */
const containsPattern = (fragment: string): RegExp =>
  new RegExp(`.+${fragment.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`)

/**
 * Assert a legacy Studio iframe embed is rendered under the given locator — the
 * real embed with a source, not just a visible container. A fragment, when
 * given, must appear in that source.
 */
export async function expectStudioEmbedRendered(
  embedFrame: Locator,
  expectedSrcFragment = '',
): Promise<void> {
  await expect(embedFrame).toBeVisible()
  await expect(embedFrame).toHaveAttribute('src', containsPattern(expectedSrcFragment))
}

/**
 * Assert the block attributes WordPress stored describe a legacy Studio iframe
 * embed of the expected experience and size. The paste-URL flow leaves the
 * resource id empty; the browse flow sets it, so callers opt in.
 */
export async function expectStoredStudioAttributes(
  requestUtils: RequestUtils,
  postId: number,
  expected: {
    experienceUrlFragment?: string
    selectedOption: EmbedSize
    requireResourceId?: boolean
  },
): Promise<void> {
  const attrs = await readStoredBlockAttributes(requestUtils, postId)
  expect(attrs, 'the post should carry a configured Ceros block').toBeTruthy()
  const stored = attrs ?? {}

  // WordPress omits attributes equal to their block.json default on save, so
  // read each through its default: deliveryMode 'iframe', manifestUrl '',
  // selectedOption 'full'.
  expect(stored.deliveryMode ?? 'iframe').toBe('iframe')
  expect(stored.manifestUrl ?? '').toBe('')

  expect(stored.selectedOption ?? 'full').toBe(expected.selectedOption)
  // A resolved Studio experience always stores a view URL; it must contain the fragment when one is given.
  expect(String(stored.experienceUrl ?? '')).toMatch(
    containsPattern(expected.experienceUrlFragment ?? ''),
  )

  const embedCode =
    expected.selectedOption === 'scroll' ? stored.scrollableEmbedCode : stored.fullHeightEmbedCode
  expect(String(embedCode ?? '')).toContain(STUDIO_EMBED_MARKER)

  if (expected.requireResourceId) {
    expect(String(stored.experienceResourceId ?? '')).not.toBe('')
  }
}

/**
 * Assert the block attributes WordPress stored describe a Flex experience in the
 * given delivery mode. render.php rebuilds every Flex embed from the manifest URL.
 */
export async function expectStoredFlexAttributes(
  requestUtils: RequestUtils,
  postId: number,
  expected: { deliveryMode: DeliveryMode; manifestUrl: string; experienceUrlFragment: string },
): Promise<void> {
  const attrs = await readStoredBlockAttributes(requestUtils, postId)
  expect(attrs, 'the post should carry a configured Ceros block').toBeTruthy()
  const stored = attrs ?? {}

  expect(stored.deliveryMode ?? 'iframe').toBe(expected.deliveryMode)
  expect(stored.manifestUrl).toBe(expected.manifestUrl)
  expect(String(stored.experienceUrl ?? '')).toMatch(
    containsPattern(expected.experienceUrlFragment),
  )
  expect(String(stored.inlineEmbedCode ?? '')).toContain(FLEX_INLINE_MARKER)
}
