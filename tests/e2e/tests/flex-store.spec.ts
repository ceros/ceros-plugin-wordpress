import { expect, test } from '@fixtures/fixtures'
import { BlockEditorActions } from '@actions/block-editor-actions'
import { CerosBlockControlsActions } from '@actions/ceros-block-controls-actions'
import { BLOCK_ENDPOINTS, BLOCK_TEXT } from '@constants/ceros-block-constants'
import { cerosBlock, resolvedFlexBlock } from '@utils/block-serializer'
import {
  BASE_URL,
  cerosFlexExperienceUrl,
  cerosFlexManifestUrl,
  cerosStubManifestUrl,
} from '@utils/env-utils'
import { watchOrigins } from '@utils/origin-watch'
import { TAGS } from '@utils/test-tags'
import { publishPost, readStoredBlockAttributes } from '@utils/wp-rest-client'

const flexSlug = new URL(cerosFlexExperienceUrl()).pathname.split('/').filter(Boolean).pop() ?? ''
const manifestUrl = cerosFlexManifestUrl()

/**
 * Storing a Flex experience on the site: the bundle is written under the post's
 * folder in uploads and the block renders that copy instead of the live one.
 */
test.describe('Flex store', { tag: [TAGS.cerosBlock, TAGS.flex] }, () => {
  // Tagged as storing files: a regression in the check would store for real.
  test(
    'refuses an Author storing into a post they cannot edit',
    { tag: [TAGS.storesFiles] },
    async ({ authorRoleUser, post, storedFiles }) => {
      // The handler's own message, not `rest_forbidden`, shows the Author got past the
      // route's edit_posts check and was refused for this post.
      await expect(
        authorRoleUser.rest({
          method: 'POST',
          path: BLOCK_ENDPOINTS.storeManifest,
          data: { url: manifestUrl, post_id: post.id },
        }),
      ).rejects.toMatchObject({ error: BLOCK_TEXT.storeNotAllowed })
      expect(await storedFiles()).toEqual([])
    },
  )

  test.describe('lifecycle', { tag: [TAGS.storesFiles] }, () => {
    test.use({
      postOptions: {
        title: 'flex store',
        blocks: [resolvedFlexBlock(flexSlug, manifestUrl, 'ssr')],
      },
    })

    test('a stored experience renders from the site with other origins blocked in the browser, and live once removed', async ({
      editor,
      post,
      reader,
      requestUtils,
      storedFiles,
    }) => {
      const controls = CerosBlockControlsActions.for(editor)
      const storeFolder = `ceros-flex/${post.id}/`
      const storedUrlPath = `/wp-content/uploads/${storeFolder}`

      await test.step('store it from the inspector', async () => {
        await controls.openStoreControls()
        await expect(controls.controls.storeStatus).toHaveText(BLOCK_TEXT.notStored)
        await controls.storeLocally()

        await expect(controls.controls.storeStatus).toContainText(BLOCK_TEXT.storedLocally)
        await expect(editor.cerosBlock.previewNote).toHaveText(BLOCK_TEXT.storedPreviewNote)
      })

      await test.step('the stored copy is saved on the post and written to uploads', async () => {
        await BlockEditorActions.for(editor.page).saveDraft()
        const attrs = (await readStoredBlockAttributes(requestUtils, post.id)) ?? {}
        const indexPath = String(attrs.storedIndexPath ?? '')
        expect(indexPath).toMatch(new RegExp(`^${storeFolder}.+/index\\.json$`))
        expect.soft(String(attrs.storedAt ?? '')).not.toBe('')

        const files = await storedFiles()
        expect(files).toContain(indexPath.slice(storeFolder.length))
        expect.soft(files.some((file) => file.includes('/pages/'))).toBe(true)
      })

      await test.step('a reader gets the stored copy with every other origin blocked', async () => {
        await publishPost(requestUtils, post.id)
        const network = await watchOrigins(reader.page, new URL(BASE_URL).origin)
        await reader.open(post.id)
        // Not a readiness wait: the off-site check below needs every request the runtime
        // makes, and only a quiet network says it has made them.
        // eslint-disable-next-line playwright/no-networkidle
        await reader.page.waitForLoadState('networkidle')

        await expect(reader.flex.ssrEmbed).toHaveCount(1)
        await expect(reader.flex.ssrEmbed).toHaveAttribute(
          'data-flex-manifest-url',
          new RegExp(storedUrlPath),
        )
        await expect(reader.missingExperience).toHaveCount(0)
        const statuses = network.statusesUnder(storedUrlPath)
        expect.soft(statuses.length, 'assets served from the stored copy').toBeGreaterThan(0)
        expect.soft(statuses.filter((status) => status >= 400)).toEqual([])
        expect.soft(network.blocked, 'requests that left the site').toEqual([])
      })

      await test.step('removing the stored copy renders it live again', async () => {
        await BlockEditorActions.for(editor.page).openEditor(post.id)
        await controls.openStoreControls()
        await controls.removeStoredCopy()
        await expect(controls.controls.storeStatus).toHaveText(BLOCK_TEXT.notStored)
        await BlockEditorActions.for(editor.page).savePublished(post.id)

        const attrs = (await readStoredBlockAttributes(requestUtils, post.id)) ?? {}
        expect(attrs.storedIndexPath ?? '').toBe('')

        await reader.page.unrouteAll()
        await reader.open(post.id)
        await expect(reader.flex.ssrEmbed).toHaveAttribute('data-flex-manifest-url', manifestUrl)
      })
    })
  })

  test.describe('failure', { tag: [TAGS.storesFiles, TAGS.stubbedHttp] }, () => {
    test.use({
      postOptions: async ({}, use) =>
        use({
          title: 'flex store',
          blocks: [resolvedFlexBlock(flexSlug, cerosStubManifestUrl(), 'ssr')],
        }),
    })

    test('a manifest that fails to load is reported and nothing is stored', async ({
      editor,
      storedFiles,
      stubHttp,
    }) => {
      await stubHttp({ [cerosStubManifestUrl()]: { status: 404 } })
      const controls = CerosBlockControlsActions.for(editor)
      await controls.openStoreControls()
      await controls.storeLocally()

      await expect(controls.controls.storeError).toHaveText(BLOCK_TEXT.storeManifestNotFound)
      await expect(controls.controls.storeStatus).toHaveText(BLOCK_TEXT.notStored)
      expect(await storedFiles()).toEqual([])
    })
  })

  test.describe('stale copy', () => {
    // Stored details only, no files: the hint compares them with the live manifest.
    test.use({
      postOptions: {
        title: 'flex store',
        blocks: [
          cerosBlock({
            experienceName: flexSlug,
            manifestUrl,
            deliveryMode: 'ssr',
            storedAt: '2000-01-01T00:00:00Z',
            storedPublishedAt: '2000-01-01T00:00:00Z',
          }),
        ],
      },
    })

    test('a copy stored before the experience was republished offers a refresh', async ({
      editor,
    }) => {
      const controls = CerosBlockControlsActions.for(editor)
      await controls.openStoreControls()

      await expect(controls.controls.storeUpdateHint).toHaveText(
        BLOCK_TEXT.storeNewExperienceVersion,
      )
    })
  })
})
