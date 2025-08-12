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
2. **Activate Plugin**: Go to WordPress Admin → Plugins and activate "Ceros"
3. **Configure API**: Add your Ceros API key in the plugin settings

## Configuration

### API Setup

1. Obtain your Ceros API key from your Ceros account
2. In WordPress Admin, go to the Ceros plugin settings
3. Enter your API key and save

#### Proof of Concept API Key

For testing purposes, you can use the following API key:

```
sk-cat-GJHnXT7n3Q3Xi1bsWT4wiUxntxnbnFXyChm13Iup8XaogaYTrUzxOEk52
```

This key is provided specifically for demonstrating the Ceros plugin functionality.

### External API Endpoints

The plugin connects to the following Ceros API endpoints:
- `https://api-wordpresspoc.dev.flex.cerosdev.com/folder-tree` - Folder structure
- `https://api-wordpresspoc.dev.flex.cerosdev.com/accounts/{accountResourceID}` - Account info
- `https://api-wordpresspoc.dev.flex.cerosdev.com/folder/{resourceId}/experiences` - Folder experiences
- `https://api-wordpresspoc.dev.flex.cerosdev.com/experiences/{resourceId}/embed-codes` - Embed codes

### WordPress REST API Endpoints

The plugin registers the following WordPress REST API endpoints:
- `GET /wp-json/ceros/v1/api-key-status` - Check API key configuration status
- `GET /wp-json/ceros/v1/current-account` - Get current Ceros account information
- `GET /wp-json/ceros/v1/folder-tree` - Get folder tree structure
- `GET /wp-json/ceros/v1/experiences` - Get experiences from a folder
- `GET /wp-json/ceros/v1/embed-codes` - Get embed codes for an experience

## Usage

### Adding a Ceros Block

1. **Create/Edit Post**: Go to Posts → Add New or edit an existing post
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

## Technical Details

### File Structure

```
ceros/
├── ceros.php                 # Main plugin file
├── includes/
│   ├── class-ceros-api.php   # API integration class
│   └── functions.php         # Helper functions and utilities
├── src/ceros/
│   ├── block.json           # Block metadata and attributes
│   ├── edit.js              # Block editor component
│   ├── render.php           # Server-side render function
│   ├── style.scss           # Front-end styles
│   └── editor.scss          # Editor styles
├── build/ceros/             # Compiled assets
├── package.json             # Node.js dependencies
├── CHANGELOG.md             # Detailed version history
└── README.md               # This file
```

### Block Registration

The plugin uses WordPress's block API with:
- **Manual registration** with explicit render callback
- **Dynamic rendering** via PHP for optimal performance
- **React-based editor** for rich user experience

### API Integration

- **REST API endpoints** for secure server-side API calls
- **Bearer token authentication** with Ceros API
- **API key validation** with dedicated status endpoint
- **Comprehensive error handling** and user feedback
- **403 Forbidden detection** for invalid API keys
- **Domain replacement** for proper embed URL handling
- **Upfront validation** to prevent unnecessary API calls
- **Automatic experience pre-loading** for all folders in the tree structure
- **Parallel API processing** for efficient data loading
- **Smart caching** to minimize redundant API requests

## Development

### Prerequisites

- Node.js and npm
- WordPress development environment
- Ceros API access

### Setup

```bash
# Install dependencies
npm install

# Build for development
npm run start

# Build for production
npm run build
```

### File Watching

```bash
# Watch for changes during development
npm run start
```

### Packaging the Plugin (ZIP for WordPress Upload)

To create a ZIP ready for upload in WordPress (Plugins → Add New → Upload Plugin):

1. Build assets (required after any code changes):
   - Run: `npm run build`
2. Remove development-only folders from the plugin directory:
   - Remove Git files/folders: `.git`, `.gitignore`, `.gitattributes`, `.github/` (if present)
   - Remove `node_modules/`
3. Create the ZIP from the plugin folder (the folder itself, not just its contents):
   - On macOS/Linux: From the directory that contains the `ceros/` folder, run:
     ```bash
     zip -r ceros.zip ceros
     ```
   - Ensure the resulting ZIP contains the `ceros` directory at the top level (with `ceros.php`, `build/`, `includes/`, `src/`, etc.).

Notes:
- Every time you make changes, repeat: `npm run build` → remove `.git*`, `.github/` (if any), and `node_modules/` → zip the `ceros/` folder.
- Do not include `node_modules/` or Git metadata in the final ZIP; WordPress does not need them and they bloat the package.

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
6. **Modal opening on configured blocks**: This was fixed in v0.6.0 - update plugin if experiencing this issue

### Error Messages

- **"Ceros API key is not set"**: Go to WordPress Admin → Settings → Ceros and add your API key
- **"The API call was forbidden"**: Your API key may be invalid or expired - verify with Ceros support
- **"Unknown error occurred"**: Check browser console for detailed error information

### Debug Mode

The plugin includes debug logging. Check `/tmp/ceros_debug.log` for detailed information about:
- Block registration
- API calls
- Render function execution
- Domain replacement

## Support

For technical support or feature requests, please contact the development team.

## Version History

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

### Current Version: 0.7.0

### Recent Updates

- **v0.15.0** - Improved CSS cache busting, manifest-based block registration with server render callback, enhanced debug logging, better 403 handling, and editor polish
- **v0.7.0** - Added automatic experience loading with parallel API processing and smart caching
- **v0.6.0** - Fixed modal persistence and block state management
- **v0.5.0** - Enhanced API key validation and error handling  
- **v0.4.0** - Fixed admin URL detection for all WordPress configurations
- **v0.3.0** - Added comprehensive error handling and 403 response management
- **v0.2.0** - Improved API key validation with upfront checks
- **v0.1.0** - Initial release with core functionality

### Key Features by Version

- **0.7.0**: Automatic experience pre-loading, parallel API calls, and smart caching
- **0.6.0**: Smart modal behavior for configured blocks
- **0.5.0**: Specific API key error messages and validation
- **0.4.0**: Cross-platform WordPress compatibility
- **0.3.0**: Robust error handling and user feedback
- **0.2.0**: API key validation before API calls
- **0.1.0**: Core Ceros integration and block functionality

## License

This plugin is proprietary software developed for WordPress integration with Ceros.com.
