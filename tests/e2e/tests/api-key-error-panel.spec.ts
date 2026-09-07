import { expect, test } from '@fixtures/fixtures'
import { BLOCK_TEXT } from '@constants/ceros-block-constants'
import { emptyCerosBlock } from '@utils/block-serializer'
import { apiMode, pluginEnv } from '@utils/env-utils'
import { TAGS } from '@utils/test-tags'

/*
 * ceros_format_error() is asymmetric, so the panel's text depends on the
 * plugin's configured environment. Staging shows the technical message and
 * drops the friendly one; production shows the friendly one and only logs the
 * technical detail. That asymmetry is the behaviour under test, not a
 * conditional to refactor away.
 */
/* eslint-disable playwright/no-conditional-in-test, playwright/no-conditional-expect, playwright/no-skipped-test */
test.describe('Editor error states', { tag: [TAGS.cerosBlock, TAGS.editorErrors] }, () => {
  test.skip(apiMode() !== 'reject', 'needs the stubbed API: bash rig/mode.sh reject')
  test.use({ postOptions: { title: 'ceros block', blocks: [emptyCerosBlock()] } })

  test('a rejected API key surfaces an actionable error panel', async ({ editor }) => {
    const block = editor.cerosBlock
    await block.waitForErrorPanel()

    // True in both environments: the panel says which environment was called,
    // and it never swallows the rejection into a generic failure.
    await expect(block.errorPanel).toContainText(
      pluginEnv() === 'staging' ? '[Staging]' : '[Production]',
    )
    await expect(block.errorPanel).not.toContainText('Unknown error')

    if (pluginEnv() === 'staging') {
      // The technical message survives, so the author sees the rejection
      // itself. It contains no "API key" substring, so the panel is classified
      // as a generic connection error and offers no settings link: an invalid
      // key becomes a dead end. Asserted so the divergence cannot drift
      // unnoticed.
      await expect(block.errorPanel).toContainText('Forbidden resource')
      await expect(block.errorHeading).toHaveText(BLOCK_TEXT.connectionErrorHeading)
      await expect(block.errorSettingsLink).toHaveCount(0)
    } else {
      // The friendly message replaces the technical one, which is logged
      // instead. It names the API key, so the author gets the actionable panel
      // and a route to fix it.
      await expect(block.errorPanel).toContainText(BLOCK_TEXT.apiKeyErrorBody)
      await expect(block.errorHeading).toHaveText(BLOCK_TEXT.apiKeyErrorHeading)
      await expect(block.errorSettingsLink).toBeVisible()
    }
  })
})
