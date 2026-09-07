/** WordPress and Gutenberg facts the suite depends on. */

/** The block registers as `create-block/ceros`, not `ceros/embed`. */
export const BLOCK_NAME = 'create-block/ceros'

/** wp-env activates the plugin under its directory name. */
export const PLUGIN_SLUG = 'ceros-plugin-wordpress'

/** Gutenberg renders the post canvas in a same-origin iframe with this name. */
export const EDITOR_CANVAS_FRAME = 'iframe[name="editor-canvas"]'

/** wp-env's documented defaults. Override with E2E_WP_USER / E2E_WP_PASSWORD. */
export const WP_DEFAULT_USER = 'admin'
export const WP_DEFAULT_PASSWORD = 'password'

export const WP_ROUTES = {
  login: '/wp-login.php',
  postEdit: (postId: number) => `/wp-admin/post.php?post=${postId}&action=edit`,
  permalink: (postId: number) => `/?p=${postId}`,
  restPosts: '/wp-json/wp/v2/posts',
} as const
