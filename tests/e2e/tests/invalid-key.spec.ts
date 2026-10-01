import { expect, test } from '@fixtures/fixtures'
import { BlockEditorActions } from '@actions/block-editor-actions'
import { BLOCK_TEXT } from '@constants/ceros-block-constants'
import { emptyCerosBlock } from '@utils/block-serializer'
import { TAGS } from '@utils/test-tags'

/**
 * A saved key the Ceros API rejects. The block reports the rejection to the author
 * instead of failing silently or crashing. Changes site-wide settings, which the
 * fixture restores.
 */
test.describe('Invalid API key', { tag: [TAGS.cerosBlock, TAGS.settings] }, () => {
  test.use({
    pluginApiKey: 'invalid',
    postOptions: { title: 'ceros block', blocks: [emptyCerosBlock()] },
  })

  test('the block reports the rejected key', async ({ page, post, pluginSettings }) => {
    // Named only so the key state is in place before the editor loads.
    void pluginSettings
    const block = (await BlockEditorActions.for(page).openEditor(post.id)).cerosBlock

    await expect(block.errorPanel).toContainText(BLOCK_TEXT.apiKeyRejected)
    await expect(block.errorBoundary).toHaveCount(0)
  })
})
