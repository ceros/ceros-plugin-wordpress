/**
 * Who the browser is logged in as. An Author by default, the least privileged role that
 * can publish; specs that need site settings or another user's post opt into administrator.
 */
import { test as base } from '@playwright/test'
import { AUTH_STORAGE_STATE, AUTHOR_STORAGE_STATE } from '@constants/paths'

export interface ActorFixture {
  actor: 'author' | 'administrator'
}

export const actorFixture = base.extend<ActorFixture>({
  actor: ['author', { option: true }],
  storageState: async ({ actor }, use) => {
    await use(actor === 'author' ? AUTHOR_STORAGE_STATE : AUTH_STORAGE_STATE)
  },
})
