import { expect, test } from '@fixtures/fixtures'
import { CerosPickerActions } from '@actions/ceros-picker-actions'
import { emptyCerosBlock } from '@utils/block-serializer'
import { CEROS_PICKER_EXPERIENCE, CEROS_PICKER_FOLDER } from '@utils/env-utils'
import { TAGS } from '@utils/test-tags'

/**
 * The browse-a-published-experience flow. With an API key configured, the block
 * offers the picker: the author opens it, drills into a folder, selects an
 * experience, and adds it — the plugin fetches the account, folder tree, and
 * embed codes from the real Ceros API. Targets a legacy Studio experience (an
 * iframe embed), so it does not depend on the Flex inline manifest.
 */
test.describe('Browse experiences', { tag: [TAGS.cerosBlock] }, () => {
  test.use({ postOptions: { title: 'ceros block', blocks: [emptyCerosBlock()] } })

  test('picks a published experience from the browser and previews the embed', async ({
    editor,
  }) => {
    await editor.cerosBlock.waitForEmptyState()

    await CerosPickerActions.for(editor).browseAndAdd(CEROS_PICKER_FOLDER, CEROS_PICKER_EXPERIENCE)

    await expect(editor.cerosBlock.preview).toBeVisible()
  })
})
