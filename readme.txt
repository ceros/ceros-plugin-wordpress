=== Ceros ===
Contributors:      copiadigital
Tags:              ceros, experiences, embed, blocks, api
Tested up to:      6.9
Stable tag:        0.31.0
Requires at least: 6.7
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Integrate Ceros interactive experiences into your WordPress site with ease. Embed Ceros content using blocks, shortcodes, or programmatically.

== Description ==

The Ceros plugin seamlessly integrates Ceros interactive experiences into your WordPress website. With this plugin, you can easily embed Ceros content using WordPress blocks, shortcodes, or programmatically through PHP functions.

**Key Features:**

* **WordPress Block Integration** - Add Ceros experiences using the dedicated Ceros block
* **REST API Integration** - Connect to Ceros API to fetch experiences, folders, and embed codes
* **Admin Settings Panel** - Configure your Ceros API credentials through WordPress admin
* **Flexible Embedding** - Use blocks, shortcodes, or PHP functions to embed content
* **Folder Tree Navigation** - Browse and select experiences from your Ceros account
* **Responsive Embed Codes** - Automatically get responsive embed codes for your experiences
* **Error Handling** - Comprehensive error handling and user feedback
* **Security** - Proper sanitization and validation of all data

**Perfect for:**
* Marketing teams wanting to embed interactive content
* Content creators using Ceros for interactive experiences
* Developers building custom integrations
* Agencies managing multiple Ceros experiences

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ceros` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to **Settings > Ceros** to configure your API credentials
4. Add your Ceros API key and account resource ID
5. Start using the Ceros block in your posts and pages

== Configuration ==

### API Setup

1. **Get Your Ceros API Key:**
   * Log into your Ceros account
   * Navigate to your account settings
   * Generate or copy your API key

2. **Find Your Account Resource ID:**
   * This is typically found in your Ceros account settings
   * It's a unique identifier for your account

3. **Configure in WordPress:**
   * Go to **Settings > Ceros** in your WordPress admin
   * Enter your API key and account resource ID
   * Save the settings

### Using the Plugin

**Block Editor:**
1. Add a new block in the WordPress editor
2. Search for "Ceros" and select the Ceros block
3. Choose an experience from your account
4. The experience will be embedded in your content

**Shortcode (if available):**
```
[ceros_experience id="your-experience-id"]
```

== Frequently Asked Questions ==

= How do I get my Ceros API key? =

Log into your Ceros account and navigate to your account settings. Look for API credentials or developer settings to generate or find your API key.

= What is an account resource ID? =

The account resource ID is a unique identifier for your Ceros account. You can find this in your Ceros account settings or by contacting Ceros support.

= Can I embed multiple experiences on the same page? =

Yes! You can add multiple Ceros blocks to a single page or post, each displaying a different experience.

= Is the plugin compatible with page builders? =

The plugin works with the WordPress block editor. For other page builders, you may need to use the shortcode or PHP function methods.

= How do I update the plugin? =

The plugin includes automatic update functionality. Updates will be available through the WordPress admin plugins screen.

= What if my experiences don't load? =

Check that your API key and account resource ID are correctly configured in the Ceros settings. Also ensure your Ceros experiences are published and accessible.

= Can I customize the appearance of embedded experiences? =

The appearance is controlled by the Ceros experience itself. The plugin provides the embed code as designed in Ceros.

== Screenshots ==

1. Ceros block in the WordPress editor showing experience selection
2. Ceros settings page with API configuration options
3. Embedded Ceros experience on a WordPress page
4. Folder tree navigation showing available experiences

== Changelog ==

= 0.1.0 =
* Initial release
* WordPress block integration
* Ceros API integration
* Admin settings panel
* Folder tree navigation
* Experience embedding functionality
* REST API endpoints
* Error handling and validation
* Plugin update checker integration

== Upgrade Notice ==

= 0.1.0 =
Initial release of the Ceros plugin for WordPress.

== Support ==

For support, please visit our [GitHub repository](https://github.com/copiadigital/ceros) or contact us through our website.

== Development ==

This plugin is actively maintained and developed. Contributions are welcome! Please see our GitHub repository for development guidelines and issue reporting.

== Credits ==

* Built with WordPress block editor APIs
* Integrates with Ceros REST API
* Uses WordPress Plugin Update Checker for automatic updates
