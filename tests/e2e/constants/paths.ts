/** Filesystem paths the suite writes at run time. All gitignored. */

/**
 * Where the `auth` setup project saves the logged-in browser session for every
 * other project to reuse. Under `playwright/.auth/`, which `.gitignore` already
 * reserves — a saved session is a credential and must never be committed.
 */
export const AUTH_STORAGE_STATE = 'playwright/.auth/admin.json'
