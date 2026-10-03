<?php
/**
 * Privacy policy suggested content.
 *
 * @package LinkPey
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LinkPey_Privacy {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_privacy_content' ) );
	}

	public static function register_privacy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			/* translators: %1$s: LinkPey website URL, %2$s: API endpoint URL */
			__(
				'<h2>LinkPey</h2>
<p>When you use the LinkPey plugin, selected post data may be transmitted to the LinkPey service in order to publish content to your connected social channels.</p>
<p><strong>Service provider:</strong> LinkPey (<a href="%1$s" target="_blank" rel="noopener noreferrer">%1$s</a>)</p>
<p><strong>Default API endpoint:</strong> %2$s</p>
<h3>Data sent to LinkPey</h3>
<ul>
<li>API token for authentication</li>
<li>Selected channel identifiers</li>
<li>Post text (title, excerpt, content, permalink when selected)</li>
<li>Images from the WordPress Media Library, encoded as base64</li>
</ul>
<h3>Data stored on your WordPress site</h3>
<ul>
<li>Encrypted API token</li>
<li>Cached channel list (temporary, 10 minutes)</li>
<li>Last selected channels per user</li>
<li>Send history on posts (last 20 entries)</li>
</ul>
<p>The plugin does not include analytics or tracking beyond what is required to communicate with LinkPey.</p>',
				'linkpey'
			),
			'https://linkpey.com',
			'https://linkpey.com/api/public/'
		);

		wp_add_privacy_policy_content(
			'LinkPey',
			wp_kses_post( $content )
		);
	}
}
