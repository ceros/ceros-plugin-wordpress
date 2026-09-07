import { expect, test } from '@fixtures/fixtures'
import { BLOCK_TEXT } from '@constants/ceros-block-constants'
import { emptyCerosBlock } from '@utils/block-serializer'
import { apiMode, pluginEnv } from '@utils/env-utils'
import { TAGS } from '@utils/test-tags'

/*
 * The panel's text depends on the plugin's configured environment, so the
 * assertions branch on it. That is the behaviour under test, not a conditional
 * to refactor away.
 */
/* eslint-disable playwright/no-conditional-in-test, playwright/no-conditional-expect, playwright/no-skipped-test */
test.describe('Editor error states', { tag: [TAGS.cerosBlock, TAGS.editorErrors] }, () => {
  test.skip(apiMode() !== 'reject', 'needs the stubbed API: bash rig/mode.sh reject')
  test.use({ postOptions: { title: 'ceros block', blocks: [emptyCerosBlock()] } })

  test('a rejected API key surfaces an error panel carrying the 403 detail', async ({ editor }) => {
    const block = editor.cerosBlock
    await block.waitForErrorPanel()

    // True in both environments: the panel must carry the rejection itself,
    // not swallow it into "Unknown error".
    await expect(block.errorPanel).toContainText('Forbidden resource')
    await expect(block.errorPanel).toContainText(
      pluginEnv() === 'staging' ? '[Staging]' : '[Production]',
    )

    if (pluginEnv() === 'staging') {
      // On staging the friendly message is dropped, so the text reaching the
      // editor contains no "API key" substring, the panel is classified as a
      // generic connection error, and the author gets no settings link.
      // Asserted so the divergence cannot drift unnoticed.
      await expect(block.errorHeading).toHaveText(BLOCK_TEXT.connectionErrorHeading)
      await expect(block.errorSettingsLink).toHaveCount(0)
    } else {
      await expect(block.errorHeading).toHaveText(BLOCK_TEXT.apiKeyErrorHeading)
      await expect(block.errorSettingsLink).toBeVisible()
    }
  })
})
