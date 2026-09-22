import { test } from '@fixtures/fixtures'
import { BlockEditorActions } from '@actions/block-editor-actions'
import { CerosExperiencePickerActions } from '@actions/ceros-experience-picker-actions'
import { CEROS_PICKER_EXPERIENCE, CEROS_PICKER_FOLDER } from '@constants/ceros-block-constants'
import { emptyCerosBlock } from '@utils/block-serializer'
import { expectStoredStudioAttributes, expectStudioEmbedRendered } from '@utils/ceros-embed'
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

  test('picks a published experience and stores the iframe embed', async ({
    editor,
    post,
    requestUtils,
  }) => {
    const block = editor.cerosBlock
    await block.waitForEmptyState()

    await CerosExperiencePickerActions.for(editor).browseAndAdd(
      CEROS_PICKER_FOLDER,
      CEROS_PICKER_EXPERIENCE,
    )

    // The preview renders the real Studio iframe, not merely a visible container.
    await expectStudioEmbedRendered(block.previewEmbedFrame)

    await BlockEditorActions.for(editor.page).saveDraft()
    // The browse flow resolves a resource id (the paste flow does not), so
    // require it alongside the Studio iframe attributes.
    await expectStoredStudioAttributes(requestUtils, post.id, {
      selectedOption: 'full',
      requireResourceId: true,
    })
  })
})
