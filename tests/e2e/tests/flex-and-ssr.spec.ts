import { expect, test } from '@fixtures/fixtures'
import { BlockEditorActions } from '@actions/block-editor-actions'
import { CerosBlockActions } from '@actions/ceros-block-actions'
import { CerosBlockControlsActions } from '@actions/ceros-block-controls-actions'
import { BLOCK_TEXT } from '@constants/ceros-block-constants'
import { FlexRender } from '@pages/modules/flex-render'
import { PublishedPostPage } from '@pages/published-post.page'
import { emptyCerosBlock, resolvedFlexBlock } from '@utils/block-serializer'
import { expectStoredFlexAttributes, type DeliveryMode } from '@utils/ceros-embed'
import { cerosFlexExperienceUrl, cerosFlexManifestUrl } from '@utils/env-utils'
import { TAGS } from '@utils/test-tags'

const flexUrl = cerosFlexExperienceUrl()
const flexSlug = new URL(flexUrl).pathname.split('/').filter(Boolean).pop() ?? ''
const manifestUrl = cerosFlexManifestUrl()

/** The marker render.php emits for each delivery mode. */
const embedFor = (render: FlexRender, mode: DeliveryMode) =>
  ({ iframe: render.iframeEmbed, inline: render.inlineEmbed, ssr: render.ssrEmbed })[mode]

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

      await expect(embedFor(block.ssrPreview, mode)).toHaveAttribute(
        'data-flex-manifest-url',
        manifestUrl,
      )

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

      await expect(embedFor(block.ssrPreview, mode)).toHaveAttribute(
        'data-flex-manifest-url',
        manifestUrl,
      )

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
        const flex = new FlexRender(page)

        await expect(embedFor(flex, mode)).toHaveCount(1)
        for (const other of ALL_MODES.filter((m) => m !== mode)) {
          await expect(embedFor(flex, other)).toHaveCount(0)
        }
        await expect(publishedPost.missingExperience).toHaveCount(0)
      })
    })
  }
})

/**
 * When the manifest can't be read, the editor warns that the server render isn't
 * the delivery mode in effect. The manifest request is stubbed to fail.
 */
test.describe(
  'Flex SSR preview — manifest unavailable',
  { tag: [TAGS.cerosBlock, TAGS.flex, TAGS.stubbedHttp] },
  () => {
    test.use({
      postOptions: {
        title: 'ceros block',
        blocks: [resolvedFlexBlock(flexSlug, manifestUrl, 'ssr')],
      },
    })

    test('the preview warns that server rendering is unavailable', async ({ editor, stubHttp }) => {
      await stubHttp({ [manifestUrl]: { status: 404 } })
      await editor.page.reload()

      await expect(editor.cerosBlock.previewWarning).toContainText(BLOCK_TEXT.ssrUnavailable)
    })
  },
)
