<?php
/**
 * Plugin Name:       LinkPey
 * Plugin URI:        https://linkpey.com
 * Description:       Publish WordPress posts to your social channels through the LinkPey API.
 * Version:           1.1.5
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Mahdi Zolqadr
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       linkpey
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LINKPEY_VERSION', '1.1.5' );
define( 'LINKPEY_PLUGIN_FILE', __FILE__ );
define( 'LINKPEY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LINKPEY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LINKPEY_MAX_MESSAGE_LENGTH', 4000 );
define( 'LINKPEY_MAX_ALBUM_ITEMS', 10 );
define( 'LINKPEY_DEFAULT_API_BASE', 'https://linkpey.com/api/public/' );

require_once LINKPEY_PLUGIN_DIR . 'includes/class-linkpey-crypto.php';
require_once LINKPEY_PLUGIN_DIR . 'includes/class-linkpey-platform.php';
require_once LINKPEY_PLUGIN_DIR . 'includes/class-linkpey-api-client.php';
require_once LINKPEY_PLUGIN_DIR . 'includes/class-linkpey-message-builder.php';
require_once LINKPEY_PLUGIN_DIR . 'includes/class-linkpey-settings.php';
require_once LINKPEY_PLUGIN_DIR . 'includes/class-linkpey-metabox.php';
require_once LINKPEY_PLUGIN_DIR . 'includes/class-linkpey-ajax.php';
require_once LINKPEY_PLUGIN_DIR . 'includes/class-linkpey-privacy.php';
require_once LINKPEY_PLUGIN_DIR . 'includes/class-linkpey-diagnostic.php';
require_once LINKPEY_PLUGIN_DIR . 'includes/class-linkpey-transport-probe.php';
require_once LINKPEY_PLUGIN_DIR . 'includes/class-linkpey.php';

function linkpey() {
	return LinkPey::instance();
}

linkpey();
