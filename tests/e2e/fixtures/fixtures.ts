import { mergeTests } from '@playwright/test'
import { editorFixture } from '@fixtures/editor-fixture'
import { flexStoreFixture } from '@fixtures/flex-store-fixture'
import { httpStubFixture } from '@fixtures/http-stub-fixture'
import { pluginSettingsFixture } from '@fixtures/plugin-settings-fixture'
import { readerFixture } from '@fixtures/reader-fixture'
import { wpRoleUserFixture } from '@fixtures/wp-role-user-fixture'

/**
 * `editor` hangs off the post fixture. Fixtures are lazy, so a spec only pays
 * for what it names.
 */
export const test = mergeTests(
  editorFixture,
  flexStoreFixture,
  httpStubFixture,
  pluginSettingsFixture,
  readerFixture,
  wpRoleUserFixture,
)
export { expect } from '@playwright/test'
