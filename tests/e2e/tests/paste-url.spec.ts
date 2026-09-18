import { expect, test } from '@fixtures/fixtures'
import { CerosBlockActions } from '@actions/ceros-block-actions'
import { emptyCerosBlock } from '@utils/block-serializer'
import { cerosFlexExperienceUrl, cerosLegacyExperienceUrl } from '@utils/env-utils'
import { TAGS } from '@utils/test-tags'

/**
 * The paste-a-public-URL authoring flow. The author pastes a public Ceros
 * experience URL, the plugin resolves it server-side against the real Ceros API,
 * and the block previews the embed. The resolver branches on delivery type, so
 * each type gets its own case; the experience URLs come from the environment.
 */
test.describe('Paste a public URL', { tag: [TAGS.cerosBlock] }, () => {
  test.use({ postOptions: { title: 'ceros block', blocks: [emptyCerosBlock()] } })

  test('resolves a legacy Studio experience and previews the embed', async ({ editor }) => {
    const block = editor.cerosBlock
    await block.waitForEmptyState()

    await CerosBlockActions.for(block).resolveAndAddPublicUrl(cerosLegacyExperienceUrl())

    // Studio resolves to an iframe embed; the block advances from the paste
    // panel to a rendered preview.
    await expect(block.preview).toBeVisible()
  })

  test.fixme('resolves a Flex experience and previews the embed', async ({ editor }) => {
    // Blocked, not flaky: the Flex inline-manifest path the plugin needs is not
    // available in the target environment, so a Flex experience cannot resolve to
    // a preview yet. Un-fixme when that path resolves, or when the plugin degrades
    // to an iframe embed. Details in the tracking ticket.
    const block = editor.cerosBlock
    await block.waitForEmptyState()

    await CerosBlockActions.for(block).resolveAndAddPublicUrl(cerosFlexExperienceUrl())

    await expect(block.preview).toBeVisible()
  })
})
