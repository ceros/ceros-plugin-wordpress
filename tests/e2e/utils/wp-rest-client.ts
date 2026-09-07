import type { APIRequestContext } from '@playwright/test'
import { parseBlockAttributes } from '@utils/block-serializer'

/**
 * Pretty permalinks are off on wp-env, so /wp-json/ 404s. The query form of the
 * REST route works regardless of permalink structure.
 */
const route = (path: string) => `/?rest_route=${path}`

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

export async function createPost(
  request: APIRequestContext,
  input: CreatePostInput,
): Promise<CreatedPost> {
  const response = await request.post(route('/wp/v2/posts'), { data: input })
  if (!response.ok()) {
    throw new Error(`create post failed: ${response.status()} ${await response.text()}`)
  }
  const body = (await response.json()) as { id: number; title: { raw?: string } }
  const stored = await readRawContent(request, body.id)
  return {
    id: body.id,
    title: input.title,
    permalink: `/?p=${body.id}`,
    attributes: parseBlockAttributes(stored),
  }
}

export async function readRawContent(request: APIRequestContext, id: number): Promise<string> {
  const response = await request.get(route(`/wp/v2/posts/${id}&context=edit`))
  if (!response.ok()) {
    throw new Error(`read post ${id} failed: ${response.status()}`)
  }
  const body = (await response.json()) as { content: { raw: string } }
  return body.content.raw
}

export async function deletePost(request: APIRequestContext, id: number): Promise<void> {
  await request.delete(route(`/wp/v2/posts/${id}&force=true`))
}
