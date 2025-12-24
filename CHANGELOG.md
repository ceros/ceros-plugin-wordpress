# Changelog

All notable changes to the Ceros WordPress Plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.27.0] - 2025-12-24

### Changed
- Updated all relevant files to version 0.27.0

## [0.26.0] - 2025-12-24

### Changed
- Updated Changelog.md file

## [0.25.0] - 2025-12-24

### Changed
- Fixed broken path

## [0.24.0] - 2025-12-24

### Fixed
- **Critical Bug**: Fixed render.php path - changed from `src/ceros/render.php` to `build/ceros/render.php` to work correctly with release zip that excludes `src/` folder

### Changed
- **Release Workflow**: Updated rsync exclusions to only exclude files that actually exist in the project

## [0.23.0] - 2025-12-24

### Added
- **Error Boundary**: Added React Error Boundary component to gracefully handle JavaScript errors in the block editor, showing user-friendly error message with "Try Again" button instead of crashing
- **Loading States**: Added loading spinner to main block area during initial API calls (API key status, account info, folder tree fetch)

### Changed
- **PHP Coding Standards**: Converted all `array()` to short syntax `[]` in `includes/functions.php`, `ceros.php`, and `includes/class-ceros-api.php`
- **Function Naming**: Renamed functions to use `ceros_` prefix per WordPress coding standards:
  - `create_block_ceros_block_init` → `ceros_block_init`
  - `render_create_block_ceros` → `ceros_render_block`

### Removed
- **Dead Code**: Removed unused `ceros_debug_log()` function from `includes/functions.php`

## [0.22.0] - 2025-12-23

### Code Quality
- **Constants for Embed Options**: Replaced hardcoded `'full'` and `'scroll'` strings with `EMBED_OPTIONS.FULL` and `EMBED_OPTIONS.SCROLL` constants across all JS files (`edit.js`, `modal.js`, `sidebar-controls.js`, `preview.js`)
- **Removed Unused Imports**: Cleaned up `edit.js` by removing unused imports (`Button`, `PanelBody`, `BaseControl`, `InspectorControls`) that were left after sidebar refactoring
- **Icon Components Optimization**: Moved `FullHeightIcon` and `ScrollingIcon` components from inside the Edit component to module level, preventing recreation on every render
- **Code Formatting**: Ran `npm run format` to standardize quotes and formatting across all JS files using WordPress coding standards

### Changed
- **PHP Modernization**: Removed unnecessary `function_exists()` check in `render.php` (file is loaded via `require_once`); modernized `isset() ? :` ternary to null coalescing operator (`??`)
- **Version Sync**: Updated `block.json` version from `0.1.0` to `0.21.0` to match plugin version

## [0.21.0] - 2025-12-23

### Security
- **Encrypted API Key Storage**: API keys are now encrypted using Sodium (libsodium) before storing in the database. Uses WordPress salts for key derivation, providing site-specific encryption.
- **Masked Display**: API key is now displayed as masked dots in the settings page, never shown in plain text
- **wp-config.php Support**: Added support for defining API key via `CEROS_API_KEY` constant in wp-config.php for enhanced security
- **XSS Prevention**: Embed codes from Ceros API are now sanitized using `wp_kses()` with a custom allow-list before storage and output
- **Embed Code Sanitization**: Added `ceros_sanitize_embed_code()` function with filterable allow-list for HTML tags/attributes

### Changed
- **Production API URL**: Updated production API endpoint from `api-wordpresspoc.prod.flex.cerosdev.com` to `rest.ceros.com`
- **Release Workflow**: Simplified GitHub Actions workflow to read version from `ceros.php` instead of prompting for input; developers now update version manually before triggering release
- **Render Cleanup**: Removed unused `$replace_placeholders` function and `$host` variable from `render.php`; simplified embed code output logic

### Added
- **Settings Link**: Added "Settings" link to plugin action links on the WordPress Plugins page for quick access to configuration
- **Ceros_Encryption Class**: New class (`includes/class-ceros-encryption.php`) handles all API key encryption, decryption, and migration from legacy plain text storage

### Removed
- **Workflow Version Input**: Removed version number prompt from release workflow; version is now extracted from plugin header
- **Auto Version Update**: Removed automatic version number updates in release workflow; developers manage version in code

### Documentation
- **Developer Guide**: Added comprehensive developer documentation to README including build commands, `npm run plugin-zip`, linting, and API development guides
- **Updated README**: Refreshed API endpoint documentation to reflect current production and staging URLs

## [0.20.0] - 2025-12-18

### Fixed
- **Plugin Installation**: Fixed file permissions on existing plugin files to allow overwrite during plugin updates and reinstallation

### Changed
- **Code Cleanup**: Removed debug code and comments
- **Error Messages**: Removed redundant experience missing message

## [0.19.0] - 2025-12-18

### Changed
- **Deployment**: Updated git push command to use HEAD instead of main branch for more flexible release workflow

## [0.18.0] - 2025-12-18

### Added
- **Missing Experience Handling**: Enhanced block rendering to gracefully handle cases where a selected experience no longer exists or is unavailable
- **User Feedback**: Added informative error message when no experience is available, guiding users to select a valid experience

### Enhanced
- **Block Resilience**: Block now displays helpful message instead of breaking when experience data is missing

## [0.17.0] - 2025-12-15

### Added
- **API Environment Selector**: New dropdown in Settings > Ceros to switch between Production and Staging API environments
- **HTTP Error Handling**: Added proper handling for 404 and 500 responses from the Ceros API, returning appropriate HTTP status codes to the client

### Changed
- **API Class Refactored**: Split single `API_BASE_URL` constant into `API_BASE_URL_PRODUCTION` and `API_BASE_URL_STAGING` with dynamic selection via `get_api_base_url()` method
- **Default Environment**: Production API is now the default environment for new installations
- **UI Improvements**: Moved embed type selection controls from preview area to sidebar and block toolbar for better accessibility and user experience
- **Embed Type Controls**: Embed type options (Full height / Scrolling) are now available in both the sidebar settings panel and the block toolbar dropdown menu
- **Code Cleanup**: Removed debug code, comments, and unused code throughout the codebase

### Enhanced
- **Preview Component**: Simplified preview component to focus solely on displaying the selected embed code without embedded controls
- **Sidebar Controls**: Enhanced sidebar with dedicated Settings panel containing embed type radio buttons with descriptions
- **Toolbar Integration**: Added embed type dropdown menu to block toolbar with visual icons for Full height and Scrolling options

## [0.16.0] - 2025-11-28

### Changed
- **Consolidated Cache Busting**: Merged duplicate cache busting functions into single smart implementation using `wp_parse_url()` to replace (not duplicate) version parameters
- **Refactored API Class**: Extracted common API request logic into `make_authenticated_request()` private method, eliminating code duplication across all API methods
- **Consolidated REST Callbacks**: Created `ceros_handle_api_response()` function to handle common error/403 checking, simplifying all REST callbacks to one-liners
- **Consolidated Permission Callbacks**: Created `ceros_rest_permission_check()` function replacing 5 anonymous permission callback functions

### Security
- **Resource ID Sanitization**: Implemented `ceros_sanitize_resource_id()` validation in all API methods accepting resource IDs (`get_folder_tree()`, `get_experiences()`, `get_embed_codes()`)
- **Input Validation**: All resource IDs are now validated to contain only alphanumeric characters, hyphens, and underscores before use in API URLs

### Removed
- **Debug Logging**: Removed all `file_put_contents()` debug calls from block registration
- **Unused Functions**: Removed unused helper functions: `ceros_get_plugin_path()`, `ceros_get_error_message()`, `ceros_format_experience()`, `ceros_get_experience_embed_code()`
- **Legacy Code**: Removed unused `$base_url`, `get()`, `request()`, and `get_example()` methods from API class

### Fixed
- **CSS Triple Version Parameters**: Fixed issue where Ceros CSS files were loading with multiple `ver=` query parameters by using URL parsing to replace instead of append

### Documentation
- Updated API-DIAGRAMS.md with resource ID sanitization flowcharts
- Removed references to deleted functions from readme.txt

## [0.15.0] - 2025-08-12

### Changed
- Improved CSS asset cache busting by appending a version or file modification time to stylesheet URLs so updates take effect immediately
- Inject block metadata `version` during registration for more reliable cache invalidation

### Enhanced
- Updated block registration to load from `build/blocks-manifest.php` and manually register the `ceros` block with a server render callback
- Added detailed debug logging around manifest loading and block registration

### Fixed
- More robust handling of 403 Forbidden responses across REST endpoints with clear, user-facing error messages
- Minor editor polish: auto-select embed option based on available codes and reliable toolbar-hide cleanup when closing the modal

### Developer Experience
- Rebuilt compiled assets to include the above changes

## [0.14.0] - 2025-08-11

### Fixed
- Experience selection now works on the first click (no longer requires two clicks)
- Experiences no longer toggle expand/collapse state; selection is independent of folder expansion

### Performance
- Selection is applied immediately on click for experience nodes (before network requests)
- Optimized tree updates to avoid full-tree rewrites by using structural sharing when attaching embed codes or children
- Significantly reduced re-renders and UI lag after multiple selections

### Developer Experience
- Rebuilt compiled assets to include the above changes

## [0.13.0] - 2025-08-08

### Changed
- Modal now opens automatically only when the block is newly inserted and not yet configured; it does not auto-open on page load for existing blocks

### Fixed
- Improved editor robustness by guarding access to the global `wp` object to avoid `ReferenceError: wp is not defined`
- Corrected JSX attributes on SVG icons to prevent runtime React warnings/errors that can block editor scripts

## [0.12.0] - 2025-08-08

### Changed
- Modal no longer opens automatically when the editor loads; the normal block view is shown by default
- Modal now opens only when the user clicks “Change Experience” or “Browse Experiences”

## [0.11.0] - 2025-08-08

### Documentation
- Added clear packaging instructions to README for creating a WordPress-ready ZIP
  - Run `npm run build` after any code changes
  - Remove Git files/folders (`.git`, `.gitignore`, `.gitattributes`, `.github/` if present)
  - Remove `node_modules/`
  - Zip the `ceros/` folder (so the ZIP contains `ceros/` at the root)

### Developer Experience
- Clarified release workflow steps for consistent, lightweight plugin archives

## [0.10.0] - 2025-08-08

### Enhanced
- More reliable toolbar suppression while the modal is open using a body class approach
- Cleanup logic ensures editor chrome is restored on close/unmount

### Fixed
- Block toolbar occasionally showing above the modal in some editor layouts/themes
- Minor editor UI polish (consistent pointer cursor on actionable buttons)

### Technical
- Consolidated modal state handling (removed z-index workaround in favor of body class)
- Hardened CSS selectors covering editor popovers, contextual toolbars, and floating portals

## [0.9.0]

### Added
- **WordPress-Style Modal Behavior**: Implemented proper block toolbar hiding when modal is open
- **Comprehensive Toolbar Management**: Added CSS rules to hide all possible WordPress toolbar elements during modal display
- **Body Class Management**: Added `ceros-modal-open` class system for reliable modal state tracking

### Enhanced
- **User Experience**: Modal now provides clean, distraction-free interface without floating toolbars
- **WordPress Core Compliance**: Modal behavior now follows WordPress core block patterns (similar to Image block "Replace" modal)
- **Button Styling**: Added cursor pointer to all buttons for better user interaction feedback
- **Modal Interface**: Improved modal presentation with proper toolbar hiding

### Technical
- **CSS Targeting**: Added comprehensive CSS selectors to hide all WordPress editor toolbar elements:
  - Block editor contextual toolbars
  - Component popovers and floating UI portals
  - Block mover and settings controls
  - Header and sidebar toolbars
- **State Management**: Implemented proper cleanup effects to prevent memory leaks
- **Multiple Hiding Methods**: Used `display: none`, `visibility: hidden`, `opacity: 0`, and `pointer-events: none` for robust hiding
- **Automatic Cleanup**: Added useEffect cleanup to remove body classes when component unmounts

### Security
- **API Key Input**: Changed API key input field from text to password type for enhanced security
- **Visual Privacy**: API keys are now hidden when displayed in WordPress admin settings

## [0.8.0]

### Enhanced
- **UI/UX Improvements**: Various styling enhancements and visual improvements
- **Design Updates**: Refined user interface elements for better user experience
- **Visual Polish**: Updated styles and layout adjustments for improved aesthetics

### Technical
- **CSS/SCSS Updates**: Modified stylesheets for enhanced visual presentation
- **Component Styling**: Updated block editor and frontend component styles
- **Theme Compatibility**: Improved compatibility with various WordPress themes

### Contributors
- Styling changes implemented by external developer

## [0.7.0]

### Added
- **Automatic Experience Loading**: Added recursive fetching of experiences for all folders in the folder tree during initial load
- **Pre-loaded Tree Structure**: Experiences are now loaded upfront for all folders, eliminating the need to click folders to discover their contents
- **Parallel API Calls**: Implemented parallel processing of experience fetching for improved performance
- **Smart Caching**: Added intelligent caching to avoid redundant API calls when data is already loaded

### Enhanced
- **User Experience**: Tree view now shows all available experiences immediately without requiring folder expansion
- **Performance**: Reduced the number of user-initiated API calls by pre-loading all folder contents
- **Error Handling**: Maintained robust error handling for the new recursive API fetching functionality
- **Loading States**: Improved loading state management for the enhanced data fetching process

### Technical
- **New Function**: Added `fetchExperiencesForAllNodes()` function for recursive experience loading
  - Uses `Promise.all()` for parallel API processing
  - Recursively processes all folder nodes in the tree structure
  - Filters experiences based on publication status and protection settings
- **API Integration**: Enhanced integration with `/ceros/v1/folder/{resourceId}/experiences` endpoint
  - Automatic experience fetching for all folders during initial tree load
  - Graceful error handling for individual folder API failures
  - Maintains existing API response format compatibility
- **Tree Data Structure**: Updated tree data structure to include pre-loaded experiences for all folders
  - Experiences are added as child nodes with `isExperience: true` flag
  - Preserves existing folder hierarchy and metadata
  - Supports mixed content (folders and experiences) in tree nodes
- **Click Handler Optimization**: Optimized `handleNodeClick` function to utilize cached data when available
  - Checks for existing embed codes before making API calls
  - Implements domain replacement for cached embed codes
  - Reduces redundant API requests through intelligent state management
- **Performance Improvements**: 
  - Initial load now fetches all content in parallel rather than on-demand
  - Eliminated need for sequential folder expansion clicks
  - Reduced total API call count through upfront batch processing

## [0.6.0]

### Fixed
- **Block State Persistence**: Fixed modal automatically opening on previously configured blocks - now properly checks for existing embed codes

## [0.5.0]

### Fixed
- **Experience Name Display**: Added support for blocks configured before experienceName attribute was introduced
- **Block Attribute Schema**: Added missing `experienceName` attribute to block.json for proper persistence

### Enhanced
- **Modal Logic**: Improved modal opening logic to respect existing block configuration
- **Backward Compatibility**: Enhanced display logic to handle legacy blocks without experienceName
- **Admin URL Detection**: Fixed "Go to Ceros Settings" link to work with all WordPress configurations including subdirectory installations (e.g., `https://markup.staged.cc/wp/`)

## [0.4.0]

### Fixed
- **Cross-Platform Compatibility**: Enhanced URL generation to handle WordPress installations in subdirectories, custom admin paths, and various hosting environments

### Enhanced
- **Server-Side URL Generation**: Added server-side admin URL generation using WordPress's `admin_url()` function for maximum reliability
- **Fallback URL Detection**: Implemented comprehensive fallback methods for URL detection when server data isn't available
- **Block Editor Data**: Added localized script data specifically for block editor context

## [0.3.0]

### Added
- **Comprehensive API Key Validation**: Added upfront API key checking before making any external API calls
- **New REST Endpoint**: Added `/ceros/v1/api-key-status` endpoint for efficient API key configuration checking
- **403 Forbidden Error Handling**: Specific error detection and user-friendly messaging for invalid API keys
- **Enhanced Error Display**: Prominent error UI in block editor with clear action steps
- **Frontend Error Handling**: API key validation and error display on published pages
- **Helper Function**: Created `ceros_check_forbidden_response()` for consistent 403 error handling across all REST endpoints
- **Proof of Concept API Key**: Added visible API key in settings page and documentation for testing purposes

### Enhanced
- **Error Message Extraction**: Improved error parsing logic to handle multiple error response formats
- **Block Editor Experience**: Clear, actionable error messages instead of generic "unknown error occurred"
- **REST API Responses**: All Ceros API endpoints now return specific error messages for different failure scenarios
- **User Guidance**: Added direct links to Ceros settings page from error messages
- **Error Detection**: Enhanced pattern matching for various API key related error messages

### Improved
- **Performance**: No unnecessary API calls when API key is missing or invalid
- **User Experience**: Immediate feedback when API key issues are detected
- **Error Consistency**: Standardized error handling across all API interactions
- **Debug Support**: Added comprehensive error logging for troubleshooting

### Fixed
- **Error Display Bug**: Fixed issue where 403 Forbidden errors showed as "Unknown error occurred"
- **Error Structure Parsing**: Corrected error object property access for proper error message extraction
- **API Key Detection**: Fixed scenarios where empty or null API keys weren't properly caught

### Security
- **Input Validation**: Enhanced API key configuration checking
- **Error Information**: Sanitized error messages to prevent information disclosure

## [0.2.0]

### Added
- **Block Editor Integration**: WordPress Gutenberg block for embedding Ceros experiences
- **Folder Tree Browser**: Interactive tree view for browsing Ceros content structure  
- **Experience Selection**: Click-to-select functionality for individual Ceros experiences
- **Embed Options**: Choice between "Full height" and "Scrollable" embed variants
- **Live Preview**: Real-time preview of embed codes in the WordPress block editor
- **Server-side Rendering**: Dynamic PHP rendering for optimal front-end performance

### Enhanced
- **API Integration**: Complete integration with Ceros REST API endpoints
- **Domain Replacement**: Automatic domain handling for embed codes
- **Responsive Design**: Embed codes adapt to different screen sizes
- **Cache Busting**: Automatic CSS cache invalidation for development

## [0.1.0]

### Added
- **Initial Plugin Structure**: Basic WordPress plugin framework
- **Ceros API Client**: PHP class for communicating with Ceros API
- **Settings Page**: WordPress admin interface for API key configuration
- **REST API Endpoints**: Custom WordPress REST endpoints for Ceros API integration
- **Basic Error Handling**: Initial error handling and user feedback system

### Infrastructure  
- **Build System**: Webpack build configuration for JavaScript and CSS compilation
- **Development Tools**: npm scripts for development and production builds
- **Plugin Architecture**: Modular structure with separate concerns for API, rendering, and UI

---

## Development Notes

### API Endpoints
- `/ceros/v1/api-key-status` - Check API key configuration status
- `/ceros/v1/current-account` - Get current Ceros account information  
- `/ceros/v1/folder-tree/{accountResourceID}` - Retrieve folder structure
- `/ceros/v1/folder/{resourceId}/experiences` - Get experiences in a folder
- `/ceros/v1/experiences/{resourceId}/embed-codes` - Fetch embed codes for an experience

### Error Handling Improvements
- **Missing API Key**: "Ceros API key is not set. Please add it in the Ceros settings first."
- **Invalid API Key**: "The API call was forbidden, which usually means your API key is invalid. Please confirm that your API key is correct."
- **Network Errors**: Specific error messages from API responses
- **Configuration Issues**: Clear guidance on resolving setup problems

### Performance Optimizations
- Upfront API key validation prevents unnecessary network requests
- Efficient error detection reduces API call overhead
- Cached embed code responses for faster subsequent loads
- Lightweight API key status check endpoint

---

## Support

For technical support or feature requests, please contact the development team.

## License

This plugin is proprietary software developed for WordPress integration with Ceros.com.