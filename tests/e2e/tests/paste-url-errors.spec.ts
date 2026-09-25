import { expect, test } from '@fixtures/fixtures'
import { CerosBlockActions } from '@actions/ceros-block-actions'
import { BLOCK_TEXT } from '@constants/ceros-block-constants'
import { emptyCerosBlock } from '@utils/block-serializer'
import { cerosStudioEditorUrl } from '@utils/env-utils'
import { TAGS } from '@utils/test-tags'

/**
 * Paste-URL validation errors. The resolver rejects each of these on shape alone
 * — before it makes any outbound request — so the cases are deterministic and
 * need no network access. The block surfaces the message in its paste-error line
 * and shows no resolved result.
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
