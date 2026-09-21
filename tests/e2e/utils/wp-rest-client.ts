import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright'
import { parseBlockAttributes } from '@utils/block-serializer'

export type CreatedPost = {
  id: number
  title: string
  permalink: string
  attributes: Record<string, unknown> | null
}

export type CreatePostInput = {
  title: string
  content: string
  status: 'draft' | 'publish'
}

/** Short, collision-resistant suffix so repeated runs never clash. */
export const uniqueSuffix = (): string =>
  `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`

/** Create a post, then read back what WordPress stored so the caller can check the block attributes survived. */
export async function createPost(
  requestUtils: RequestUtils,
  input: CreatePostInput,
): Promise<CreatedPost> {
  const created = await requestUtils.rest<{ id: number }>({
    method: 'POST',
    path: '/wp/v2/posts',
    data: { title: input.title, content: input.content, status: input.status },
  })
  const stored = await readRawContent(requestUtils, created.id)
  return {
    id: created.id,
    title: input.title,
    permalink: `/?p=${created.id}`,
    attributes: parseBlockAttributes(stored),
  }
}

/** Unrendered block markup (context=edit), for the attribute guard. */
async function readRawContent(requestUtils: RequestUtils, id: number): Promise<string> {
  const body = await requestUtils.rest<{ content: { raw: string } }>({
    path: `/wp/v2/posts/${id}`,
    params: { context: 'edit' },
  })
  return body.content.raw
}

/**
 * The block attributes WordPress currently has stored for a post, read back
 * after a save so a spec asserts what actually persisted rather than what the
 * browser held in memory. {} for an attribute-less block, null for a post with
 * no Ceros block.
 */
export async function readStoredBlockAttributes(
  requestUtils: RequestUtils,
  id: number,
): Promise<Record<string, unknown> | null> {
  return parseBlockAttributes(await readRawContent(requestUtils, id))
}

export async function deletePost(requestUtils: RequestUtils, id: number): Promise<void> {
  await requestUtils.rest({ method: 'DELETE', path: `/wp/v2/posts/${id}`, params: { force: true } })
}
