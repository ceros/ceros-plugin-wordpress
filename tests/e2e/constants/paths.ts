/** Filesystem paths the suite writes at run time. All gitignored. */

/**
 * Admin session (cookies + REST nonce) global setup saves for the requestUtils fixture
 * and administrator specs. Under `playwright/.auth/` (gitignored) — a session is a credential.
 */
export const AUTH_STORAGE_STATE = 'playwright/.auth/admin.json'

/** The `e2e-author` session global setup saves. Browser contexts use it by default. */
export const AUTHOR_STORAGE_STATE = 'playwright/.auth/author.json'
