import { mergeTests } from '@playwright/test'
import { editorFixture } from '@fixtures/editor-fixture'
import { httpStubFixture } from '@fixtures/http-stub-fixture'
import { pluginSettingsFixture } from '@fixtures/plugin-settings-fixture'
import { wpEditorRoleFixture } from '@fixtures/wp-editor-role-fixture'

/**
 * `editor` hangs off the post fixture. Fixtures are lazy, so a spec only pays
 * for what it names.
 */
export const test = mergeTests(
  editorFixture,
  httpStubFixture,
  pluginSettingsFixture,
  wpEditorRoleFixture,
)
export { expect } from '@playwright/test'
