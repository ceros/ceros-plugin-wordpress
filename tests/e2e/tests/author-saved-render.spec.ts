import { expect, test } from '@fixtures/fixtures'
import { BlockEditorActions } from '@actions/block-editor-actions'
import { CerosBlockActions } from '@actions/ceros-block-actions'
import { emptyCerosBlock } from '@utils/block-serializer'
import {
  expectStoredFlexAttributes,
  expectStoredStudioAttributes,
  expectStudioEmbedRendered,
} from '@utils/ceros-embed'
import {
  cerosFlexExperienceUrl,
  cerosFlexManifestUrl,
  cerosLegacyExperienceUrl,
  experienceSlug,
} from '@utils/env-utils'
import { TAGS } from '@utils/test-tags'
import { readStoredBlockAttributes } from '@utils/wp-rest-client'

const legacySlug = experienceSlug(cerosLegacyExperienceUrl())
const manifestUrl = cerosFlexManifestUrl()

/**
 * An Author lacks `unfiltered_html`, so WordPress strips `<script>` from the embed codes
 * they save. render.php rebuilds the embed at request time, so readers still get it.
 */
test.describe('Author-saved embeds', { tag: [TAGS.cerosBlock, TAGS.rendered] }, () => {
  test.use({
    postOptions: { title: 'author saved embed', blocks: [emptyCerosBlock()], status: 'publish' },
  })

  test('a legacy Studio embed an Author pastes and saves renders its iframe for a logged-out reader', async ({
    editor,
    post,
    reader,
    requestUtils,
  }) => {
    await editor.cerosBlock.waitForEmptyState()
    await CerosBlockActions.for(editor.cerosBlock).resolveAndAddPublicUrl(
      cerosLegacyExperienceUrl(),
    )
    await BlockEditorActions.for(editor.page).savePublished(post.id)

    await expectStoredStudioAttributes(requestUtils, post.id, {
      experienceUrlFragment: legacySlug,
      selectedOption: 'full',
    })
    const stored = (await readStoredBlockAttributes(requestUtils, post.id)) ?? {}
    expect(String(stored.fullHeightEmbedCode)).not.toMatch(/<script|<iframe/i)
    expect(String(stored.scrollableEmbedCode ?? '')).not.toMatch(/<script|<iframe/i)

    await reader.open(post.id)
    await expect(reader.iframeEmbed).toHaveCount(1)
    await expectStudioEmbedRendered(reader.iframeEmbed, legacySlug)
    await expect(reader.missingExperience).toHaveCount(0)
  })

  test(
    'a Flex inline embed an Author pastes and saves renders its runtime for a logged-out reader',
    {
      tag: [TAGS.flex],
    },
    async ({ editor, post, reader, requestUtils }) => {
      await editor.cerosBlock.waitForEmptyState()
      const actions = CerosBlockActions.for(editor.cerosBlock)
      await actions.resolvePublicUrl(cerosFlexExperienceUrl())
      await actions.choosePasteDeliveryMode('inline')
      await actions.addResolvedExperience()
      await BlockEditorActions.for(editor.page).savePublished(post.id)

      await expectStoredFlexAttributes(requestUtils, post.id, {
        deliveryMode: 'inline',
        manifestUrl,
        experienceUrlFragment: experienceSlug(cerosFlexExperienceUrl()),
      })
      const stored = (await readStoredBlockAttributes(requestUtils, post.id)) ?? {}
      expect(String(stored.inlineEmbedCode)).not.toMatch(/<script/i)

      await reader.open(post.id)
      await expect(reader.flex.inlineEmbed).toHaveCount(1)
      await expect(reader.flex.inlineEmbed).toHaveAttribute('data-flex-manifest-url', manifestUrl)
      await expect(reader.flex.inlineRuntime).toHaveCount(1)
      await expect(reader.missingExperience).toHaveCount(0)
    },
  )
})
