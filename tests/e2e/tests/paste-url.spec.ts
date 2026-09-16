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

  // eslint-disable-next-line playwright/no-skipped-test -- see the comment below.
  test.fixme('resolves a Flex experience and previews the embed', async ({ editor }) => {
    // BLOCKED, not flaky: every Flex experience on latest (and prod) advertises
    // an `x-flex-manifest` header pointing at `<experience>/manifest.v1.json`,
    // which 404s, so the resolver hard-fails with `ceros_manifest_unavailable`
    // and never previews. Un-fixme once a Flex experience serves a live inline
    // manifest, or the plugin falls back to an iframe when a header-advertised
    // manifest fails. Tracked by the manifest-404 investigation.
    const block = editor.cerosBlock
    await block.waitForEmptyState()

    await CerosBlockActions.for(block).resolveAndAddPublicUrl(cerosFlexExperienceUrl())

    await expect(block.preview).toBeVisible()
  })
})
