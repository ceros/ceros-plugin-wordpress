[![Readme freshness](https://github.com/ceros/ceros-plugin-wordpress/actions/workflows/readme-freshness.yml/badge.svg)](https://github.com/ceros/ceros-plugin-wordpress/actions/workflows/readme-freshness.yml)

# Ceros WordPress Plugin

A WordPress block plugin that integrates with the Ceros API to embed interactive Ceros experiences directly into your WordPress posts and pages.

## Features

- **Browse Ceros Content**: Interactive tree view to browse your Ceros folder structure
- **Lazy Experience Loading**: Experiences are fetched on demand when a folder is expanded, keeping initial load fast
- **Experience Selection**: Click to expand folders and select individual Ceros experiences
- **Parallel API Processing**: Efficient loading with parallel API calls for improved performance
- **Smart Caching**: Intelligent data caching to avoid redundant API requests
- **Embed Options**: Choose between "Full height" and "Scrollable" embed variants
- **Live Preview**: Real-time preview of embed codes in the WordPress block editor
- **Dynamic Rendering**: Server-side rendering for optimal front-end performance
- **Responsive Design**: Embed codes adapt to different screen sizes
- **API Key Validation**: Automatic validation with specific error messages for missing or invalid API keys
- **Smart Modal Behavior**: Modal only opens for new blocks, preserves existing block configurations
- **Error Handling**: Comprehensive error handling for API failures and network issues
- **Cross-Platform Compatibility**: Works with all WordPress installations including subdirectory setups

## Installation

1. **Upload Plugin**: Copy the `ceros` folder to your WordPress `wp-content/plugins/` directory
2. **Activate Plugin**: Go to WordPress Admin > Plugins and activate "Ceros"
3. **Configure API**: Navigate to Settings > Ceros and add your API key

## Configuration

### API Setup

1. Obtain your Ceros API key from your Ceros account
2. In WordPress Admin, go to Settings > Ceros
3. Select your environment (Production or Staging)
4. Enter your API key and save

Your API key is stored securely using encryption. See [API Key Encryption](#api-key-encryption) for details.

### Alternative: Define API Key in wp-config.php

For enhanced security or to simplify deployment across multiple environments, you can define your API key directly in `wp-config.php`:

```php
define( 'CEROS_API_KEY', 'your-api-key-here' );
```

Add this line to your `wp-config.php` file (before the line that says "That's all, stop editing!").

**Benefits of using wp-config.php:**
- API key is not stored in the database
- Key is not included in database exports or backups
- Easier to manage different keys across development, staging, and production environments
- The settings page will show "Defined in wp-config.php" when this method is used

**Note:** When using the constant, the API key field in Settings > Ceros will be disabled.

### API Key Encryption

API keys are encrypted at rest using PHP's Sodium extension (`sodium_crypto_secretbox`). The plugin will **not** save a key if encryption fails — plain text storage is never used.

#### How it works

1. **Key derivation** — A 32-byte encryption key is derived from the WordPress `LOGGED_IN_KEY` and `LOGGED_IN_SALT` constants (defined in `wp-config.php`) using `sodium_crypto_generichash`.
2. **Encryption** — The API key is encrypted with `sodium_crypto_secretbox` using a random nonce (`SODIUM_CRYPTO_SECRETBOX_NONCEBYTES`). The nonce is prepended to the ciphertext.
3. **Storage** — The nonce + ciphertext is Base64-encoded and stored in the `wp_options` table as `ceros_api_key_encrypted` (production) or `ceros_api_key_encrypted_staging` (staging).
4. **Decryption** — On read, the process is reversed: Base64-decode, split nonce from ciphertext, decrypt with the same derived key.

#### Per-environment keys

Production and staging API keys are stored separately, each with their own encrypted option. Switching environments does not affect the other environment's stored key.

#### Requirements

- **PHP Sodium extension** — required (bundled with PHP 7.2+, enabled by default in most hosting environments). If Sodium is not available, the plugin will show an error and refuse to save the key.
- **Stable WordPress salts** — The encryption key is derived from `LOGGED_IN_KEY` and `LOGGED_IN_SALT`. Changing these constants in `wp-config.php` will invalidate all stored encrypted keys (they will need to be re-entered).

#### Legacy migration

Older versions of the plugin stored the API key in plain text in the `ceros_api_key` option. On first access, the plugin deletes the plain text key immediately, then attempts to re-save it encrypted. If encryption fails (e.g. Sodium is unavailable), the plain text key is still removed — it is never left in the database. The user will need to re-enter the key once Sodium is available.

### API Environments

The plugin supports two API environments:

| Environment | Base URL |
|-------------|----------|
| Production  | `https://rest.ceros.com` |
| Staging     | User-configured (entered in Settings > Ceros when staging is selected) |

### External API Endpoints

The plugin connects to the following Ceros API endpoints:

- `/accounts/current-account` - Current account info
- `/accounts/{accountResourceID}/folder-tree` - Folder structure
- `/folder/{resourceId}/experiences` - Folder experiences
- `/experiences/{resourceId}/embed-codes` - Embed codes

### WordPress REST API Endpoints

The plugin registers the following WordPress REST API endpoints:

- `GET /wp-json/ceros/v1/current-account` - Get current Ceros account information
- `GET /wp-json/ceros/v1/folder-tree/{account_resource_id}` - Get folder tree structure
- `GET /wp-json/ceros/v1/folder/{resource_id}/experiences` - Get experiences from a folder
- `GET /wp-json/ceros/v1/experiences/{resource_id}/embed-codes` - Get embed codes for an experience

## Usage

### Adding a Ceros Block

1. **Create/Edit Post**: Go to Posts > Add New or edit an existing post
2. **Add Block**: Click the "+" button and search for "Ceros"
3. **API Key Check**: The block automatically validates your API key configuration
   - If API key is missing: Clear error message with link to settings
   - If API key is invalid: Specific error message about forbidden access
4. **Browse Content**: The block will display your Ceros folder tree and load experiences on demand when you expand a folder (once API key is valid)
5. **Select Experience**: All experiences are immediately visible - simply click on any experience to select it
6. **Choose Embed Type**: Select either "Full height" or "Scrollable"
7. **Preview**: The embed code preview will appear in the editor
8. **Save**: Update/Publish your post

### Editing Existing Blocks

- **Previously configured blocks**: Show existing settings without opening the modal
- **Change Experience**: Click "Change Experience" button to browse for a different experience
- **Embed Options**: Radio buttons remain functional for switching between full height and scrollable modes

### Embed Options

- **Full Height**: Creates a responsive embed that maintains aspect ratio
- **Scrollable**: Creates a fixed-height embed with scrolling capability

## File Structure

```
ceros/
├── ceros.php                 # Main plugin file
├── includes/
│   ├── class-ceros-api.php        # API integration class
│   ├── class-ceros-encryption.php # API key encryption/decryption
│   └── functions.php              # Helper functions and utilities
├── src/ceros/
│   ├── block.json            # Block metadata and attributes
│   ├── edit.js               # Block editor component
│   ├── render.php            # Server-side render function
│   ├── style.scss            # Front-end styles
│   └── editor.scss           # Editor styles
├── build/ceros/              # Compiled assets (generated)
├── node_modules/             # Dependencies (generated, not distributed)
├── package.json              # Node.js dependencies and scripts
├── CHANGELOG.md              # Detailed version history
└── README.md                 # This file
```

---

## Developer Guide

This section covers development workflows for building, testing, and packaging the plugin.

### Prerequisites

- **Node.js** (v18+ recommended) and npm
- **WordPress** development environment (see [Local Development Environment](#local-development-environment-wp-env) below, or use Local, DDEV, etc.)
- **Ceros API** access with valid API key

### Initial Setup

```bash
# Clone the plugin repository and enter it
cd ceros-plugin-wordpress

# Install dependencies (includes the local dev environment, wp-env)
npm install
```

### Local Development Environment (wp-env)

The fastest way to test the plugin locally is with [`@wordpress/env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) (`wp-env`), the official WordPress tooling. It spins up a complete, throwaway WordPress site in Docker and **mounts this repository as a plugin automatically** — no manual copying into `wp-content/plugins/`.

#### Requirements

- [Docker](https://docs.docker.com/get-docker/) installed and running
- Dependencies installed (`npm install`) — this pulls in `@wordpress/env`

#### Start the environment

```bash
npm run env:start
```

This will:

- Download and start WordPress + MySQL containers
- Mount this repo into `wp-content/plugins/ceros` and **activate the plugin**
- Serve the site at **http://localhost:8888**

Log in to the admin at **http://localhost:8888/wp-admin** with:

- **Username:** `admin`
- **Password:** `password`

Then go to **Settings > Ceros** to add your API key, and add a **Ceros** block to any post or page.

#### How the local plugin is loaded

The mapping lives in [`.wp-env.json`](.wp-env.json):

```json
{
	"plugins": [ "." ]
}
```

The `"."` entry mounts the current directory (this repo) into the container as a plugin. Any change you make to the PHP files is reflected **immediately** — no restart needed. For block JavaScript/CSS, run `npm run start` in a separate terminal so `build/` rebuilds on save, then refresh the browser.

To test against an additional local plugin, add its path to the array, e.g.:

```json
{
	"plugins": [ ".", "../some-other-plugin" ]
}
```

#### Useful environment commands

| Command | Description |
|---------|-------------|
| `npm run env:start` | Start (or restart) the WordPress environment |
| `npm run env:stop` | Stop the containers (data is preserved) |
| `npm run env:clean` | Reset the WordPress database to a clean state |
| `npm run env:destroy` | Remove the environment and all its data |
| `npm run env:cli -- plugin list` | Run WP-CLI inside the container (anything after `--`) |

> **Tip:** Personal overrides (e.g. a different port or PHP version) can go in a `.wp-env.override.json` file, which is git-ignored.

### Building JavaScript/CSS Assets

The plugin uses `@wordpress/scripts` for building block assets.

#### Development Build (with watch mode)

```bash
npm run start
```

This will:
- Watch for file changes in `src/`
- Automatically rebuild on changes
- Generate source maps for debugging
- Output to `build/` directory

#### Production Build

```bash
npm run build
```

This will:
- Create optimized, minified assets
- Generate the `blocks-manifest.php` for efficient block registration
- Output to `build/` directory

### Available npm Scripts

| Command | Description |
|---------|-------------|
| `npm run start` | Start development mode with file watching |
| `npm run build` | Create production build |
| `npm run plugin-zip` | Create distributable ZIP file |
| `npm run check:tested-upto` | Check `readme.txt` against the current WordPress release |
| `npm run format` | Format code using WordPress coding standards |
| `npm run lint:js` | Lint JavaScript files |
| `npm run lint:css` | Lint CSS/SCSS files |
| `npm run packages-update` | Update WordPress packages to latest versions |
| `npm run env:start` | Start the local WordPress environment (wp-env) |
| `npm run env:stop` | Stop the local WordPress environment |
| `npm run env:clean` | Reset the local WordPress database |
| `npm run env:destroy` | Remove the local environment and its data |
| `npm run env:cli` | Run WP-CLI inside the environment |

### Creating a Plugin ZIP

To create a ZIP file for distribution or WordPress upload:

```bash
# Ensure production assets are built first
npm run build

# Create the ZIP file
npm run plugin-zip
```

This will create a `ceros.zip` file in the plugin directory, ready for:
- WordPress Admin > Plugins > Add New > Upload Plugin
- Distribution to clients
- Deployment to staging/production servers

The `plugin-zip` command automatically:
- Includes only necessary files (excludes `node_modules/`, `.git/`, etc.)
- Uses the plugin slug from `package.json` as the filename
- Creates proper directory structure for WordPress

### Releasing

The version is bumped in the release pull request, not while a feature is in
flight. A number bumped early labels every commit that lands after it, so two
builds carrying the same version can behave differently.

Entries accumulate under `## [Unreleased]` in `CHANGELOG.md` as work merges.

To cut a release:

1. Open a release pull request that does exactly three things:

   - Renames `## [Unreleased]` to `## [X.Y.Z] - YYYY-MM-DD`, dated the day you
     will tag.
   - Sets the version to `X.Y.Z` everywhere it is declared. Run
     `npm run check:versions` rather than working from a list here: it names
     every site it compares, and it is the same check that gates the release.
   - Nothing else.

   ```bash
   npm run check:versions
   npm run check:changelog
   ```

   Both run in CI as well, and the release workflow refuses to publish if
   either fails.

2. Get the approving review and merge it.

3. Tag the merge commit and push the tag. Name the branch in step 1
   `release-X.Y.Z`, never `vX.Y.Z`, so it cannot collide with the tag.

   ```bash
   git switch main && git pull
   git tag vX.Y.Z
   git push origin vX.Y.Z
   ```

   The tag triggers the build, which packages
   `ceros-wordpress-plugin-X.Y.Z.zip` and publishes it on a GitHub Release. A
   manual run of that workflow builds the same ZIP as an artifact and publishes
   nothing.

4. Installed copies are not offered the update, so anyone on an earlier version
   upgrades by uploading the new ZIP.

`readme.txt` carries a `Tested up to:` header naming the WordPress version this
plugin has been verified against. It goes stale when WordPress ships rather than
when anything here changes, and a weekly workflow reports on it. Raising it
means testing against that WordPress release first: bring the local environment
up on it and exercise the block in the editor, the settings page, and a
front-end render.

```bash
npm run check:tested-upto
npm run env:destroy
npx wp-env start --update
```

`--update` matters. `.wp-env.json` sets `core` to `null`, so a plain start can
reuse whatever WordPress version the existing containers already hold. Set
`Tested up to:` to the major version you tested, `7.1` rather than `7.1.2`; the
directory fills in the minor itself.

### Manual ZIP Creation

If you need more control over the ZIP contents:

```bash
# From the plugins directory (parent of ceros/)
cd wp-content/plugins

# Build first
cd ceros && npm run build && cd ..

# Create ZIP excluding dev files
zip -r ceros.zip ceros \
  -x "ceros/node_modules/*" \
  -x "ceros/.git/*" \
  -x "ceros/.gitignore" \
  -x "ceros/.gitattributes" \
  -x "ceros/.github/*" \
  -x "ceros/src/*" \
  -x "ceros/*.lock"
```

### Code Style & Linting

The plugin follows WordPress coding standards. Before committing:

```bash
# Format all files
npm run format

# Check for JavaScript issues
npm run lint:js

# Check for CSS issues
npm run lint:css
```

### Block Development

#### Block Registration

The plugin uses WordPress 6.7+ manifest-based block registration for performance:

```php
// Registers blocks from blocks-manifest.php
wp_register_block_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
```

#### Adding Block Attributes

Edit `src/ceros/block.json` to add new attributes:

```json
{
  "attributes": {
    "yourNewAttribute": {
      "type": "string",
      "default": ""
    }
  }
}
```

#### Server-Side Rendering

The block uses dynamic rendering via `src/ceros/render.php`. The render callback is registered in `ceros.php`:

```php
register_block_type( __DIR__ . "/build/ceros", array(
    'render_callback' => 'render_create_block_ceros',
) );
```

### API Development

#### Adding New API Endpoints

1. Add the method to `includes/class-ceros-api.php`:

```php
public function get_new_endpoint( $param ) {
    $param = ceros_sanitize_resource_id( $param );
    if ( false === $param ) {
        return new WP_Error( 'invalid_param', __( 'Invalid parameter.', 'ceros' ) );
    }
    return $this->make_authenticated_request( '/new-endpoint/' . $param );
}
```

2. Register the REST route in `ceros.php`:

```php
register_rest_route( 'ceros/v1', '/new-endpoint/(?P<param>[a-zA-Z0-9\-_]+)', array(
    'methods'             => 'GET',
    'callback'            => 'ceros_rest_get_new_endpoint',
    'permission_callback' => 'ceros_rest_permission_check',
) );
```

---

## Troubleshooting

### Common Issues

1. **Block not appearing**: Ensure plugin is activated and API key is configured
2. **API Key Issues**:
   - **Missing API key**: Block shows "Ceros API Key Required" with link to settings
   - **Invalid API key**: Block shows "The API call was forbidden" error message
   - **403 Forbidden**: Usually indicates an invalid or expired API key
3. **API errors**: Check API key validity and network connectivity
4. **Embed not loading**: Verify domain settings and embed code format
5. **Preview issues**: Clear browser cache and check console for errors

### Error Messages

Error messages are environment-aware. In **Production**, messages are user-friendly and technical details are written to `error_log()`. In **Staging**, the full technical detail is shown directly in the UI. All messages are prefixed with the active environment, e.g. `[Production]` or `[Staging]`.

#### Settings Page — Save (Form Validation)

| Message | Trigger |
|---------|---------|
| A staging API URL is required when using the staging environment. The key was not saved. | Save API key with staging environment selected but no staging URL provided |
| Could not connect to the Ceros API. The key was not saved. | Network error (DNS failure, timeout, connection refused) during API key validation |
| The API key could not be verified. Please check that the key is correct and try again. | Ceros API returns a non-2xx HTTP status during key validation |
| The staging API URL is not a valid URL. | Staging URL fails format validation |
| The staging API URL must use HTTPS. | Staging URL uses HTTP instead of HTTPS |

#### Settings Page — Test Connection (AJAX)

| Message | Trigger |
|---------|---------|
| You do not have permission to perform this action. | User lacks `manage_options` capability |
| No API key is configured. Please save an API key first. | API key is empty for the current environment |
| Staging URL is required. | Staging environment selected but no staging URL entered |
| The staging URL must use HTTPS. | Staging URL does not use HTTPS |
| Could not connect to the Ceros API. Please try again. | Network error during test connection (production-friendly) |
| Connection test failed. Please check the URL and API key. | Ceros API returns a non-2xx HTTP status (production-friendly) |

#### Block Editor — REST API

| Message | Trigger |
|---------|---------|
| Ceros API key is not set. Please add it in the Ceros settings first. | No API key configured for the current environment |
| Staging API URL is not configured. Please set it in the Ceros settings. | Staging environment selected but no URL set |
| Your API key appears to be invalid. Please confirm that it is correct in the Ceros settings. | 403 Forbidden response from Ceros API (production-friendly) |
| The Ceros API returned an error. Please try again or check the Ceros settings. | Any other 4xx/5xx response from Ceros API (production-friendly) |
| Unable to connect to the Ceros API. Please check your internet connection and try again. | DNS resolution failure or generic cURL error |
| Failed to connect to the Ceros API. Please check your internet connection and try again. | Connection refused (cURL error 7) |
| The connection to the Ceros API timed out. Please try again in a moment. | Request timeout (cURL error 28) |
| Account resource ID is missing or invalid. | Internal: malformed account resource ID |
| Resource ID is missing or invalid. | Internal: malformed folder or experience resource ID |

#### Block Editor — UI

| Message | Trigger |
|---------|---------|
| Something went wrong | React Error Boundary caught an unhandled JavaScript error |
| The Ceros block encountered an error. This might be a temporary issue. | Error Boundary detail message, shown with "Try Again" button |
| No tree data available | Folder tree data is empty or failed to load |
| No experiences found | A folder contains no experiences |

### Build Issues

- **"Cannot find module"**: Run `npm install` to install dependencies
- **Build fails**: Ensure Node.js v18+ is installed; try deleting `node_modules/` and running `npm install`
- **Changes not appearing**: Clear browser cache and WordPress cache; ensure `npm run build` completed successfully

---

## Version History

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

**Current Version: 0.33.0**

---

## Support

For technical support or feature requests, please contact the development team.

## License

This plugin is proprietary software developed for WordPress integration with Ceros.
