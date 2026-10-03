<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LinkPey {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		LinkPey_Settings::init();
		LinkPey_Metabox::init();
		LinkPey_Ajax::init();
		LinkPey_Privacy::init();
	}

	public static function get_api_base_url() {
		$url = get_option( 'linkpey_api_base_url', LINKPEY_DEFAULT_API_BASE );
		$url = trailingslashit( esc_url_raw( $url ) );
		return $url;
	}

	public static function get_api_token() {
		$encrypted = get_option( 'linkpey_api_token_enc', '' );
		if ( empty( $encrypted ) ) {
			return '';
		}
		return LinkPey_Crypto::decrypt( $encrypted );
	}

	public static function has_api_token() {
		return '' !== self::get_api_token();
	}

	public static function get_token_prefix() {
		return get_option( 'linkpey_api_token_prefix', '' );
	}

	public static function clear_channels_cache( $token = null ) {
		if ( null === $token ) {
			$token = self::get_api_token();
		}

		if ( '' === $token ) {
			return;
		}

		delete_transient( 'linkpey_channels_v2_' . md5( $token ) );
		delete_transient( 'linkpey_channels_' . md5( $token ) );
	}
}
