import { expect, test } from '@fixtures/fixtures'
import { BlockEditorActions } from '@actions/block-editor-actions'
import { CerosBlockActions } from '@actions/ceros-block-actions'
import { CerosBlockControlsActions } from '@actions/ceros-block-controls-actions'
import { CerosExperiencePickerActions } from '@actions/ceros-experience-picker-actions'
import { CEROS_PICKER_EXPERIENCE, CEROS_PICKER_FOLDER } from '@constants/ceros-block-constants'
import { emptyCerosBlock } from '@utils/block-serializer'
import { expectStoredStudioAttributes } from '@utils/ceros-embed'
import { cerosLegacyExperienceUrl } from '@utils/env-utils'
import { TAGS } from '@utils/test-tags'

/** The last path segment of the legacy experience URL, stored on the block. */
const legacySlug =
  new URL(cerosLegacyExperienceUrl()).pathname.split('/').filter(Boolean).pop() ?? ''

/**
 * The toolbar and inspector controls for a placed legacy Studio experience: the
 * embed-size dropdown, the inspector size radios, and reopening the picker.
 */
test.describe('Authoring controls — embed size', { tag: [TAGS.cerosBlock] }, () => {
  test.use({ postOptions: { title: 'ceros block', blocks: [emptyCerosBlock()] } })

  test('the toolbar dropdown switches the stored embed size', async ({
    editor,
    post,
    requestUtils,
  }) => {
    const block = editor.cerosBlock
    await block.waitForEmptyState()
    await CerosBlockActions.for(block).resolveAndAddPublicUrl(cerosLegacyExperienceUrl())

    const controls = CerosBlockControlsActions.for(editor)
    await controls.selectBlock()
    await controls.chooseEmbedSizeFromToolbar('scroll')

    await BlockEditorActions.for(editor.page).saveDraft()
    await expectStoredStudioAttributes(requestUtils, post.id, {
      experienceUrlFragment: legacySlug,
      selectedOption: 'scroll',
    })
  })

  test('the inspector radios switch the stored embed size', async ({
    editor,
    post,
    requestUtils,
  }) => {
    const block = editor.cerosBlock
    await block.waitForEmptyState()
    await CerosBlockActions.for(block).resolveAndAddPublicUrl(cerosLegacyExperienceUrl())

    const controls = CerosBlockControlsActions.for(editor)
    await controls.selectBlock()
    await controls.chooseEmbedSizeFromInspector('scroll')

    await BlockEditorActions.for(editor.page).saveDraft()
    await expectStoredStudioAttributes(requestUtils, post.id, {
      experienceUrlFragment: legacySlug,
      selectedOption: 'scroll',
    })
  })
})

/**
 * Reopening the picker is offered only when experience browsing is configured,
 * so these place via the browse flow and need an API key — the same requirement
 * as the browse-picker spec.
 */
test.describe('Authoring controls — reopen the picker', { tag: [TAGS.cerosBlock] }, () => {
  test.use({ postOptions: { title: 'ceros block', blocks: [emptyCerosBlock()] } })

  test('the toolbar Replace button reopens the picker', async ({ editor }) => {
    await editor.cerosBlock.waitForEmptyState()
    await CerosExperiencePickerActions.for(editor).browseAndAdd(
      CEROS_PICKER_FOLDER,
      CEROS_PICKER_EXPERIENCE,
    )

    const controls = CerosBlockControlsActions.for(editor)
    await controls.selectBlock()
    await controls.reopenPickerFromToolbar()

    await editor.experiencePicker.waitForOpen()
    await expect(editor.experiencePicker.modal).toBeVisible()
  })

  test('the inspector edit button reopens the picker', async ({ editor }) => {
    await editor.cerosBlock.waitForEmptyState()
    await CerosExperiencePickerActions.for(editor).browseAndAdd(
      CEROS_PICKER_FOLDER,
      CEROS_PICKER_EXPERIENCE,
    )

    const controls = CerosBlockControlsActions.for(editor)
    await controls.selectBlock()
    await controls.reopenPickerFromInspector()

    await editor.experiencePicker.waitForOpen()
    await expect(editor.experiencePicker.modal).toBeVisible()
  })
})
