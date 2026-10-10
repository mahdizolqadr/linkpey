=== LinkPey ===
Contributors: sokotebipayan
Tags: social media, telegram, publishing, share, messaging
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish WordPress posts to your social channels (Telegram, Eitaa, Soroush, Rubika, Bale, Igap) through the LinkPey service.

== Description ==

LinkPey helps WordPress site owners send post content from the editor to social channels they manage in their [LinkPey](https://linkpey.com) account.

This plugin is designed for Persian-speaking publishers and supports RTL admin UI.

= Features =

* Connect WordPress to LinkPey with an API token
* Test API connection from the settings page
* Post editor metabox for one-click publishing
* Choose message content: title, excerpt, full content, permalink, images
* Live message preview and character counter (4000 characters)
* Select multiple channels across different platforms
* Send images from the WordPress Media Library as base64 uploads
* Per-post send history in the editor

= Requirements =

* A [LinkPey](https://linkpey.com) account
* An API token from the LinkPey Developer panel
* At least one channel enabled for public API access in LinkPey

= Supported platforms =

* Telegram
* Eitaa
* Soroush
* Rubika
* Bale
* Igap

== External services ==

This plugin connects to the LinkPey Public API to list channels and send messages.

**Service:** LinkPey
**Website:** https://linkpey.com
**API endpoint (default):** https://linkpey.com/api/public/
**Terms of use:** https://linkpey.com
**Privacy policy:** https://linkpey.com

= What data is sent =

When you configure the plugin and publish a post, the following data may be sent to LinkPey:

* API token (for authentication)
* Selected channel identifiers (`platform`, `chat_id`)
* Post text built from selected fields (title, excerpt, content, permalink)
* Images from the WordPress Media Library, uploaded as base64-encoded files

= What is stored locally =

* Encrypted API token in the WordPress database
* Cached channel list (10 minutes)
* Last selected channels per user
* Send history per post (last 20 entries)

No tracking, analytics, or unsolicited phone-home behavior is included in this plugin.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/linkpey`, or install through the WordPress plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Settings → LinkPey Settings**.
4. Enter your LinkPey API token and save.
5. Click **Test Connection** to verify the setup.
6. Edit a post and use the **Share to Social Networks** metabox to publish.

== Frequently Asked Questions ==

= Do I need a LinkPey account? =

Yes. This plugin requires an active LinkPey account and API token. It does not work without the external LinkPey service.

= Where do I get an API token? =

Log in to your LinkPey panel, open the Developer section, and generate an API token.

= Which channels appear in WordPress? =

Only channels that you have enabled for public API access in the LinkPey Developer panel.

= Can I send to multiple channels at once? =

Yes. You can select multiple channels, including channels on different platforms, and send in one action.

= Are images downloaded from my site by LinkPey servers? =

No. The plugin reads images from your WordPress uploads directory and sends them as base64-encoded files in the API request. LinkPey does not fetch arbitrary URLs from your site.

= Is my API token exposed to visitors? =

No. The token is stored encrypted in the database and is only used in server-side requests. It is never exposed to frontend JavaScript.

= Does this work on draft posts? =

You can preview messages on drafts. The post permalink is only included after the post is published.

== Screenshots ==

1. LinkPey settings page with API token and connection test
2. Post editor metabox with channel selection and preview
3. Send results after publishing to multiple channels

== Changelog ==

= 1.1.5 =
* Reliable API access on hosts where wp_remote fails: automatic PHP cURL fallback (including fake empty [] responses)
* Remember cURL transport for one week after first successful fallback (faster channel load and send)
* Admin transport probe buttons (wp_remote vs cURL vs file_get_contents) and server-side diagnostic tools
* Fix false “connection OK with 0 channels” from older releases; stricter JSON validation

= 1.1.4 =
* Settings: separate buttons to test wp_remote, cURL, file_get_contents, and plugin default path

= 1.1.3 =
* cURL fallback when WordPress HTTP API is blocked or returns empty (no LinkPey server change)
* Clear warning when API returns 0 channels; removed X-LinkPey-Api header requirement

= 1.1.2 =
* Diagnostic: list pre_http_request callbacks, mu-plugins, direct cURL probe vs wp_remote_request

= 1.1.1 =
* Admin diagnostic report (no SSH needed) for false-positive connection tests on hosting

= 1.1.0 =
* Fix false-positive connection test when HTTP 200 non-JSON/empty responses were treated as success
* Require LinkPey API response signature (X-LinkPey-Api header) for linkpey.com endpoints
* Show HTTP debug details on successful connection test

= 1.0.9 =
* Keep saved API token visible in the settings field after save
* Test connection uses token from the input field and shows detailed outbound/network error hints
* Clearer messages when the WordPress server cannot reach LinkPey API

= 1.0.8 =
* Plugin Check compliance: i18n placeholders, nonce/input handling, coding standards

= 1.0.7 =
* Fix Contributors username and align plugin/readme version headers

= 1.0.6 =
* Update Tested up to: 7.1 for WordPress.org compatibility check

= 1.0.5 =
* Fix plugin header: remove duplicate Author URI for WordPress.org compliance

= 1.0.4 =
* WordPress.org submission readiness: readme, privacy policy, uninstall cleanup
* Improved plugin metadata and documentation

= 1.0.3 =
* Send images as base64 media_files instead of remote URLs (security improvement)
* Read images only from WordPress Media Library uploads

= 1.0.2 =
* Restrict image handling to local WordPress uploads

= 1.0.1 =
* Normalize platform slugs before API requests (e.g. Eita → eita)

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.1.5 =
Fixes false connection tests on many hosts; uses PHP cURL when WordPress HTTP API is blocked. Update strongly recommended if you see 0 channels.

= 1.0.8 =
Plugin Check and coding standards fixes for WordPress.org review.
