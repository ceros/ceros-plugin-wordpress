import { expect, test } from '@fixtures/fixtures'
import { CerosBlockActions } from '@actions/ceros-block-actions'
import { BLOCK_TEXT } from '@constants/ceros-block-constants'
import { emptyCerosBlock } from '@utils/block-serializer'
import { cerosStubManifestUrl, cerosStudioEditorUrl, stubExperienceUrl } from '@utils/env-utils'
import type { HttpStubs } from '@utils/http-stubs'
import { TAGS } from '@utils/test-tags'

/**
 * Paste-URL errors. The block surfaces the resolver's message in its paste-error
 * line and shows no resolved result.
 *
 * Validation errors: the resolver rejects these on shape alone, before any
 * outbound request, so they need no network access.
 */
const REJECTED_URLS = [
  { label: 'a non-https URL', url: 'http://view.ceros.com/x', message: BLOCK_TEXT.pasteNonHttps },
  { label: 'a non-public host', url: 'https://10.0.0.1/x', message: BLOCK_TEXT.pasteBadHost },
  { label: 'a Studio editor URL', url: cerosStudioEditorUrl(), message: BLOCK_TEXT.pasteEditorUrl },
] as const

test.describe('Paste a public URL — validation errors', { tag: [TAGS.cerosBlock] }, () => {
  test.use({ postOptions: { title: 'ceros block', blocks: [emptyCerosBlock()] } })

  for (const { label, url, message } of REJECTED_URLS) {
    test(`rejects ${label} with a message and no resolved result`, async ({ editor }) => {
      const block = editor.cerosBlock
      await block.waitForEmptyState()

      await CerosBlockActions.for(block).submitPublicUrl(url)

      await expect(block.pasteError).toHaveText(message)
      await expect(block.pasteResult).toHaveCount(0)
    })
  }
})

/**
 * Errors decided by what the pasted URL answers. Each request the resolver makes is
 * stubbed, so the outcome is fixed. The hosts are real because the resolver resolves
 * them before it requests anything.
 */
const TRANSPORT_ERROR = 'Connection timed out'

const STUBBED_OUTCOMES: readonly {
  label: string
  stubs: (pageUrl: string, manifestUrl: string) => HttpStubs
  message: string
}[] = [
  {
    label: 'a URL it cannot reach',
    stubs: (pageUrl) => ({ [pageUrl]: { error: TRANSPORT_ERROR } }),
    message: `${BLOCK_TEXT.pasteUnreachable} ${TRANSPORT_ERROR}`,
  },
  {
    label: 'a non-Ceros page',
    stubs: (pageUrl) => ({ [pageUrl]: {} }),
    message: BLOCK_TEXT.pasteNotCeros,
  },
  {
    label: 'a manifest hosted outside Ceros',
    stubs: (pageUrl) => ({
      [pageUrl]: { headers: { 'x-flex-manifest': 'https://example.com/manifest.json' } },
    }),
    message: BLOCK_TEXT.pasteManifestUntrusted,
  },
  {
    label: 'a Flex manifest that fails to load',
    stubs: (pageUrl, manifestUrl) => ({
      [pageUrl]: { headers: { 'x-flex-manifest': manifestUrl } },
      [manifestUrl]: { status: 404 },
    }),
    message: BLOCK_TEXT.pasteManifestUnavailable,
  },
]

test.describe(
  'Paste a public URL — errors from the response',
  { tag: [TAGS.cerosBlock, TAGS.stubbedHttp] },
  () => {
    test.use({ postOptions: { title: 'ceros block', blocks: [emptyCerosBlock()] } })

    for (const { label, stubs, message } of STUBBED_OUTCOMES) {
      test(`rejects ${label} with a message and no resolved result`, async ({
        editor,
        stubHttp,
      }) => {
        const pageUrl = stubExperienceUrl()
        await stubHttp(stubs(pageUrl, cerosStubManifestUrl()))
        const block = editor.cerosBlock
        await block.waitForEmptyState()

        await CerosBlockActions.for(block).submitPublicUrl(pageUrl)

        await expect(block.pasteError).toHaveText(message)
        await expect(block.pasteResult).toHaveCount(0)
      })
    }
  },
)
