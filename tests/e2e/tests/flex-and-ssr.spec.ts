import { expect, test } from '@fixtures/fixtures'
import { BlockEditorActions } from '@actions/block-editor-actions'
import { CerosBlockActions } from '@actions/ceros-block-actions'
import { CerosBlockControlsActions } from '@actions/ceros-block-controls-actions'
import { BLOCK_TEXT } from '@constants/ceros-block-constants'
import { PublishedPostPage } from '@pages/published-post.page'
import { emptyCerosBlock, resolvedFlexBlock } from '@utils/block-serializer'
import { expectStoredFlexAttributes, type DeliveryMode } from '@utils/ceros-embed'
import { cerosFlexExperienceUrl, cerosFlexManifestUrl } from '@utils/env-utils'
import { TAGS } from '@utils/test-tags'

const flexUrl = cerosFlexExperienceUrl()
const flexSlug = new URL(flexUrl).pathname.split('/').filter(Boolean).pop() ?? ''
const manifestUrl = cerosFlexManifestUrl()

/** The attribute that ties each mode's marker to the experience it embeds. */
const SOURCE_ATTRIBUTE: Record<DeliveryMode, [name: string, value: string]> = {
  iframe: ['data-ceros-experience', flexUrl],
  inline: ['data-flex-manifest-url', manifestUrl],
  ssr: ['data-flex-manifest-url', manifestUrl],
}

const NON_DEFAULT_MODES = ['inline', 'ssr'] as const satisfies readonly DeliveryMode[]

/**
 * Flex delivery modes in the editor. Every Flex block previews through the server
 * render, so the preview shows the same markup the published page gets.
 */
test.describe('Flex delivery modes', { tag: [TAGS.cerosBlock, TAGS.flex] }, () => {
  test.use({ postOptions: { title: 'ceros block', blocks: [emptyCerosBlock()] } })

  for (const mode of NON_DEFAULT_MODES) {
    test(`choosing ${mode} when pasting stores and previews it`, async ({
      editor,
      post,
      requestUtils,
    }) => {
      const block = editor.cerosBlock
      await block.waitForEmptyState()
      const actions = CerosBlockActions.for(block)
      await actions.resolvePublicUrl(flexUrl)
      await actions.choosePasteDeliveryMode(mode)
      await actions.addResolvedExperience()

      await expect(block.ssrPreview.embed(mode)).toHaveAttribute(...SOURCE_ATTRIBUTE[mode])

      await BlockEditorActions.for(editor.page).saveDraft()
      await expectStoredFlexAttributes(requestUtils, post.id, {
        deliveryMode: mode,
        manifestUrl,
        experienceUrlFragment: flexSlug,
      })
    })

    test(`switching to ${mode} in the inspector stores and previews it`, async ({
      editor,
      post,
      requestUtils,
    }) => {
      const block = editor.cerosBlock
      await block.waitForEmptyState()
      await CerosBlockActions.for(block).resolveAndAddPublicUrl(flexUrl)

      const controls = CerosBlockControlsActions.for(editor)
      await controls.waitForPlacedControls()
      await controls.chooseDeliveryModeFromInspector(mode)

      await expect(block.ssrPreview.embed(mode)).toHaveAttribute(...SOURCE_ATTRIBUTE[mode])

      await BlockEditorActions.for(editor.page).saveDraft()
      await expectStoredFlexAttributes(requestUtils, post.id, {
        deliveryMode: mode,
        manifestUrl,
        experienceUrlFragment: flexSlug,
      })
    })
  }
})

const ALL_MODES = ['iframe', 'inline', 'ssr'] as const satisfies readonly DeliveryMode[]

/** The published render for each delivery mode, rebuilt from the stored manifest URL. */
test.describe('Flex published render', { tag: [TAGS.cerosBlock, TAGS.rendered, TAGS.flex] }, () => {
  // A real reader is anonymous; render the published page logged out.
  test.use({ storageState: { cookies: [], origins: [] } })

  for (const mode of ALL_MODES) {
    test.describe(mode, () => {
      test.use({
        postOptions: {
          title: `flex ${mode} experience`,
          blocks: [resolvedFlexBlock(flexSlug, manifestUrl, mode)],
          status: 'publish',
        },
      })

      test(`a Flex experience renders its ${mode} embed`, async ({ page, post }) => {
        const publishedPost = new PublishedPostPage(page)
        await publishedPost.open(post.id)
        const { flex } = publishedPost

        await expect(flex.embed(mode)).toHaveCount(1)
        await expect(flex.embed(mode)).toHaveAttribute(...SOURCE_ATTRIBUTE[mode])
        for (const other of ALL_MODES.filter((m) => m !== mode)) {
          await expect(flex.embed(other)).toHaveCount(0)
        }
        await expect(publishedPost.missingExperience).toHaveCount(0)
      })
    })
  }
})

/** The SSR preview reads the manifest and warns when it can't, since the page then renders another mode. */
test.describe('Flex SSR preview — manifest warning', { tag: [TAGS.cerosBlock, TAGS.flex] }, () => {
  test.describe('manifest loads', () => {
    test.use({
      postOptions: {
        title: 'ceros block',
        blocks: [resolvedFlexBlock(flexSlug, manifestUrl, 'ssr')],
      },
    })

    test('the preview shows no warning', async ({ editor }) => {
      // Reload so the manifest read starts after the listener is in place.
      const manifestRead = editor.page.waitForResponse((r) => r.url().includes('manifest-meta'))
      await editor.page.reload()
      expect((await manifestRead).ok()).toBe(true)

      await expect(editor.cerosBlock.ssrPreview.ssrEmbed).toHaveCount(1)
      await expect(editor.cerosBlock.previewWarning).toHaveCount(0)
    })
  })

  // A manifest URL no other test uses, so a stub left by a killed run can't reach them.
  const unavailableManifestUrl = `${new URL(flexUrl).origin}/ceros-e2e/manifest.json`

  test.describe('manifest unavailable', { tag: [TAGS.stubbedHttp] }, () => {
    test.use({
      postOptions: {
        title: 'ceros block',
        blocks: [resolvedFlexBlock(flexSlug, unavailableManifestUrl, 'ssr')],
      },
    })

    test('the preview warns that server rendering is unavailable', async ({ editor, stubHttp }) => {
      await stubHttp({ [unavailableManifestUrl]: { status: 404 } })
      await editor.page.reload()

      await expect(editor.cerosBlock.previewWarning).toContainText(BLOCK_TEXT.ssrUnavailable)
    })
  })
})
