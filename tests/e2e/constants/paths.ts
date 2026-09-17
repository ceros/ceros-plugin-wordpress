/** Filesystem paths the suite writes at run time. All gitignored. */

/**
 * Session (cookies + REST nonce) global setup saves for browser contexts and the
 * requestUtils fixture. Under `playwright/.auth/` (gitignored) — a session is a credential.
 */
export const AUTH_STORAGE_STATE = 'playwright/.auth/admin.json'
