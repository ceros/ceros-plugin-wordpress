import type { Page } from '@playwright/test'

export type OriginWatch = {
  /** URLs the page tried to load from any other origin, all aborted. */
  blocked: string[]
  /** Statuses of the page's responses whose path starts with `path`. */
  statusesUnder: (path: string) => number[]
}

/** Abort every request a page makes outside `origin`, and record what it loaded. */
export async function watchOrigins(page: Page, origin: string): Promise<OriginWatch> {
  const blocked: string[] = []
  const responses: { path: string; status: number }[] = []
  await page.route(
    (url) => url.origin !== origin,
    (route) => {
      blocked.push(route.request().url())
      return route.abort()
    },
  )
  page.on('response', (response) => {
    responses.push({ path: new URL(response.url()).pathname, status: response.status() })
  })
  return {
    blocked,
    statusesUnder: (path) => responses.filter((r) => r.path.startsWith(path)).map((r) => r.status),
  }
}
