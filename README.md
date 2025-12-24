# Ceros WordPress Plugin

A WordPress block plugin that integrates with the Ceros API to embed interactive Ceros experiences directly into your WordPress posts and pages.

## Features

- **Browse Ceros Content**: Interactive tree view to browse your Ceros folder structure
- **Automatic Experience Loading**: All experiences are pre-loaded for immediate browsing without folder expansion
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

Your API key is stored securely using encryption (Sodium/libsodium).

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

### API Environments

The plugin supports two API environments:

| Environment | Base URL |
|-------------|----------|
| Production  | `https://rest.ceros.com` |
| Staging     | `https://api-wordpresspoc.dev.flex.cerosdev.com` |

### External API Endpoints

The plugin connects to the following Ceros API endpoints:

- `/accounts/current-account` - Current account info
- `/accounts/{accountResourceID}/folder-tree` - Folder structure
- `/folder/{resourceId}/experiences` - Folder experiences
- `/experiences/{resourceId}/embed-codes` - Embed codes

### WordPress REST API Endpoints

The plugin registers the following WordPress REST API endpoints:

- `GET /wp-json/ceros/v1/api-key-status` - Check API key configuration status
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
4. **Browse Content**: The block will display your Ceros folder tree with all experiences pre-loaded (once API key is valid)
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
- **WordPress** development environment (Local, DDEV, Docker, etc.)
- **Ceros API** access with valid API key

### Initial Setup

```bash
# Navigate to the plugin directory
cd wp-content/plugins/ceros

# Install dependencies
npm install
```

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
| `npm run format` | Format code using WordPress coding standards |
| `npm run lint:js` | Lint JavaScript files |
| `npm run lint:css` | Lint CSS/SCSS files |
| `npm run packages-update` | Update WordPress packages to latest versions |

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

- **"Ceros API key is not set"**: Go to WordPress Admin > Settings > Ceros and add your API key
- **"The API call was forbidden"**: Your API key may be invalid or expired - verify with Ceros support
- **"Unknown error occurred"**: Check browser console for detailed error information

### Build Issues

- **"Cannot find module"**: Run `npm install` to install dependencies
- **Build fails**: Ensure Node.js v18+ is installed; try deleting `node_modules/` and running `npm install`
- **Changes not appearing**: Clear browser cache and WordPress cache; ensure `npm run build` completed successfully

---

## Version History

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

**Current Version: 0.27.0**

---

## Support

For technical support or feature requests, please contact the development team.

## License

This plugin is proprietary software developed for WordPress integration with Ceros.
