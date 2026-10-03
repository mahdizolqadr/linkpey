<?php
/**
 * Uninstall LinkPey.
 *
 * @package LinkPey
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'linkpey_api_base_url' );
delete_option( 'linkpey_api_token_enc' );
delete_option( 'linkpey_api_token_prefix' );

global $wpdb;

// Bulk transient cleanup on uninstall; WordPress has no wildcard transient API.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_linkpey_channels_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_linkpey_channels_' ) . '%',
		$wpdb->esc_like( '_transient_linkpey_prefer_curl_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_linkpey_prefer_curl_' ) . '%'
	)
);

delete_metadata( 'user', 0, 'linkpey_last_selected_channels', '', true );
delete_metadata( 'post', 0, '_linkpey_send_history', '', true );
