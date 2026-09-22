import { expect, test } from '@fixtures/fixtures'
import { BlockEditorActions } from '@actions/block-editor-actions'
import { CerosBlockActions } from '@actions/ceros-block-actions'
import { emptyCerosBlock } from '@utils/block-serializer'
import { expectStoredStudioAttributes, expectStudioEmbedRendered } from '@utils/ceros-embed'
import { readStoredBlockAttributes } from '@utils/wp-rest-client'
import { cerosFlexExperienceUrl, cerosLegacyExperienceUrl } from '@utils/env-utils'
import { TAGS } from '@utils/test-tags'

/** The last path segment of the legacy experience URL — the slug the embed iframe points at. */
const legacySlug =
  new URL(cerosLegacyExperienceUrl()).pathname.split('/').filter(Boolean).pop() ?? ''

/**
 * The paste-a-public-URL authoring flow. The author pastes a public Ceros
 * experience URL, the plugin resolves it server-side against the real Ceros API,
 * and the block previews the embed. The resolver branches on delivery type, so
 * each type gets its own case; the experience URLs come from the environment.
 */
test.describe('Paste a public URL', { tag: [TAGS.cerosBlock] }, () => {
  test.use({ postOptions: { title: 'ceros block', blocks: [emptyCerosBlock()] } })

  test('resolves a legacy Studio experience and stores the iframe embed', async ({
    editor,
    post,
    requestUtils,
  }) => {
    const block = editor.cerosBlock
    await block.waitForEmptyState()

    await CerosBlockActions.for(block).resolveAndAddPublicUrl(cerosLegacyExperienceUrl())

    // The preview renders the real Studio iframe pointing at the experience,
    // not merely a visible container.
    await expectStudioEmbedRendered(block.previewEmbedFrame, legacySlug)

    await BlockEditorActions.for(editor.page).saveDraft()
    await expectStoredStudioAttributes(requestUtils, post.id, {
      experienceUrlFragment: legacySlug,
      selectedOption: 'full',
    })
  })

  test('stores the scrolling embed when that size is chosen', async ({
    editor,
    post,
    requestUtils,
  }) => {
    const block = editor.cerosBlock
    await block.waitForEmptyState()

    const actions = CerosBlockActions.for(block)
    await actions.resolvePublicUrl(cerosLegacyExperienceUrl())
    await actions.choosePasteEmbedSize('scroll')
    await actions.addResolvedExperience()

    await expectStudioEmbedRendered(block.previewEmbedFrame, legacySlug)

    await BlockEditorActions.for(editor.page).saveDraft()
    await expectStoredStudioAttributes(requestUtils, post.id, {
      experienceUrlFragment: legacySlug,
      selectedOption: 'scroll',
    })
  })

  test('a placed but unconfigured block shows the empty state and stores no embed', async ({
    editor,
    post,
    requestUtils,
  }) => {
    const block = editor.cerosBlock
    await block.waitForEmptyState()

    // Nothing authored: the block offers the paste flow and has no embed to show.
    await expect(block.previewEmbedFrame).toHaveCount(0)

    const stored = (await readStoredBlockAttributes(requestUtils, post.id)) ?? {}
    expect(stored.fullHeightEmbedCode ?? '').toBe('')
    expect(stored.experienceUrl ?? '').toBe('')
  })

  test.fixme('resolves a Flex experience and previews the embed', async ({ editor }) => {
    // Blocked, not flaky: the Flex inline-manifest path the plugin needs is not
    // available in the target environment, so a Flex experience cannot resolve to
    // a preview yet. Un-fixme when that path resolves, or when the plugin degrades
    // to an iframe embed.
    const block = editor.cerosBlock
    await block.waitForEmptyState()

    await CerosBlockActions.for(block).resolveAndAddPublicUrl(cerosFlexExperienceUrl())

    await expect(block.preview).toBeVisible()
  })
})
