import { mergeTests } from '@playwright/test'
import { editorFixture } from '@fixtures/editor-fixture'

/**
 * `editor` hangs off the post fixture. Fixtures are lazy, so a spec only pays
 * for what it names.
 */
export const test = mergeTests(editorFixture)
export { expect } from '@playwright/test'
