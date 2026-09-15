import { expect, test } from '@fixtures/fixtures'
import { CerosBlockActions } from '@actions/ceros-block-actions'
import { emptyCerosBlock } from '@utils/block-serializer'
import { cerosExperienceUrl } from '@utils/env-utils'
import { TAGS } from '@utils/test-tags'

/**
 * The paste-a-public-URL authoring flow. The author pastes a public Ceros
 * experience URL, the plugin resolves it server-side against the real Ceros API
 * (no API key — this is the no-key path), and the block previews the embed.
 *
 * The experience is a real, published one on the shared `latest` dev env; its
 * URL comes from the environment (see `cerosExperienceUrl`). Whatever runs the
 * suite must be able to reach that host.
 */
test.describe('Paste a public URL', { tag: [TAGS.cerosBlock] }, () => {
  test.use({ postOptions: { title: 'ceros block', blocks: [emptyCerosBlock()] } })

  test('resolving a public experience URL previews the embed', async ({ editor }) => {
    const block = editor.cerosBlock
    await block.waitForEmptyState()

    await CerosBlockActions.for(block).resolveAndAddPublicUrl(cerosExperienceUrl())

    // The block advanced from the paste panel to a rendered preview — the
    // positive path resolved against the real API and embedded, end to end in
    // the editor. (A failed resolve never reaches here: the action waits on the
    // result panel, which a rejected URL never shows.)
    await expect(block.preview).toBeVisible()
  })
})
