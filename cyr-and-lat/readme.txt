=== Cyr to Lat Reloaded - Transliteration of Links and File Names ===
Contributors: themeisle
Tags: cyrillic to latin, cyr to lat, rus to lat, cyrillic, transliteration
Requires at least: 4.2
Tested up to: 6.9
Requires PHP: 5.2
Stable tag: 1.3.1
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Converts Cyrillic, Georgian, and Greek URLs and file names into readable Latin characters.

== Description ==

**Note:** This plugin is no longer being actively developed, and we recommend switching to [**Cyrlitera**](https://wordpress.org/plugins/cyrlitera/), a more advanced version with expanded features and customization options.

Transliteration is the process of converting characters from one writing system to another, such as converting Cyrillic symbols to Latin. Because most web software and URLs are designed around Latin characters, using Cyrillic or other non-Latin symbols in links or file names can lead to unreadable URLs, accessibility issues, and even broken links. Transliteration ensures your URLs and file names remain clean, readable, and compatible across all platforms.

This plugin automatically replaces Cyrillic, Georgian, Turkish, and other supported characters with Latin equivalents to create clean and readable URLs for posts, categories, taxonomies, products, and custom post types. It also fixes incorrect file names by removing unsafe characters and transliterating them during upload, helping prevent 404 errors and broken media links.

### Examples

**Cyrillic URL before transliteration:**

`https://example.com/%D0%BF%D1%80%D0%B8%D0%B2%D0%B5%D1%82-%D0%BC%D0%B8%D1%80`

**Same URL transliterated to Latin:**

`https://example.com/privet-mir`

**Incorrect file names before transliteration:**

`%D0%BC%D0%BE%D0%B5_image_290.jpg`
`A+nice+picture.png`

**Readable transliterated file names:**

`moe_image_290.jpg`
`a-nice-picture.png`

By using Latin-based file names and URLs, you avoid issues with encoding, broken links, and unreadable paths. This plugin performs the transliteration automatically each time a file is uploaded, ensuring your media library stays clean and consistent.

### Features

- Automatically transliterates URLs for posts, pages, categories, tags, and custom post types
- Preserves existing URL structure while making it readable
- Transliterates attachment file names
- Removes unsafe or problematic characters from file names
- Supports Russian, Belarusian, Ukrainian, Bulgarian, Georgian, Greek, Armenian, and Serbian character sets
- Compatible with Advanced Custom Fields, Asgaros Forum, and BuddyPress

### Advanced Transliteration Features

If you need more advanced transliteration features, consider upgrading to [Cyrlitera](https://wordpress.org/plugins/cyrlitera/).

Unlike Cyr to Lat Reloaded, Cyrlitera offers a user-friendly interface that gives you full control over how links are transliterated. It also allows you to roll back converted URLs, create automatic redirects from old URLs to new ones, and help eliminate broken links across your site.

== Screenshots ==

1. Example for posts
2. Example for files

== Installation ==

1. In your WordPress admin, go to **Plugins > Add New**
2. In the Search field, type **"Cyr to Lat Reloaded"**
3. Under "Cyr to Lat Reloaded" by Themeisle, click the **Install Now** link
4. Once the process is complete, click the **Activate Plugin** link

You're done! No configuration is needed.

== Frequently Asked Questions ==

= Can I define my own transliteration rules? =

This plugin uses a fixed set of transliteration rules. If you need custom character mappings or support for additional languages, we recommend using [Cyrlitera](https://wordpress.org/plugins/cyrlitera/), which allows you to define your own substitutions with full flexibility.

= Does this plugin work on multisite installations? =

Not at this time. The plugin works only on single-site installations.

= How do I redirect old URLs to their new transliterated versions? =

This plugin does not create redirects. If you need automatic redirects for transliterated URLs, we recommend switching to [Cyrlitera](https://wordpress.org/plugins/cyrlitera/), which includes built-in redirect handling for all updated slugs.

= Does this plugin modify links in comments, menus, or theme files? =

No. Only WordPress-generated slugs (URLs) and attachment file names are affected. Other hard-coded links remain as they are.

= Will this plugin change the text inside my posts? =

No. Only URLs (slugs) and file names are transliterated. The content inside your posts or pages remains unchanged.

== Changelog ==

#####   Version 1.3.1 (2026-01-13)

- We are retiring Cyr to Lat reloaded in favor of Cyrlitera plugin to ensure users have access to existing features plus additional capabilities
- Enhanced security




####   Version 1.3.0 (2025-11-06)

Cyr to Lat plugin has been acquired by Themeisle :tada:
We’re happy to announce that Themeisle is now the new owner of Cyr to Lat. This acquisition will help ensure the plugin’s continued development, better support, and exciting new updates in the future.
Your existing setup will continue to work as usual — no action is required on your part.



= 1.2.0 =
* Added: Compatible with Wordpress 5.0
* Added: Gutenberg support
* Added: Support Armenian symbols
* Added: Support Serbian symbols
* Added: Add ACF support
* Added: Add buddypress support
* Added: Add Asgaros forum support
* Fixed: Bug with Cyrillic links on frontend.

= 1.1.1 =
* Added: Greek symbols
* Added: Special symbols
* Added: Ability to rollback changes
* Fixed: Bug with transliteration of Ukrainian symbols

= 1.1.0 =
* Rename plugin, now the plugin has a name Webcraftic Cyr-And-Lat reloaded
* Updated character base
* Fixed compatibility issues
* Tested with the latest version of Wordpress

= 1.0.2 =
* Backward сompatibility with "old" russian slugs works in terms (tags and categories) too.

= 1.0.1 =
* Fixed minor bug

= 1.0 =
* Initial release
