<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LinkPey_Crypto {

	private static function encryption_key() {
		if ( defined( 'AUTH_KEY' ) && defined( 'SECURE_AUTH_KEY' ) ) {
			return hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY, true );
		}
		return hash( 'sha256', 'linkpey-fallback-key', true );
	}

	public static function encrypt( $plain_text ) {
		if ( '' === $plain_text ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $plain_text );
		}

		$iv = random_bytes( 16 );
		$ciphertext = openssl_encrypt( $plain_text, 'AES-256-CBC', self::encryption_key(), OPENSSL_RAW_DATA, $iv );
		if ( false === $ciphertext ) {
			return '';
		}

		return base64_encode( $iv . $ciphertext );
	}

	public static function decrypt( $encrypted ) {
		if ( '' === $encrypted ) {
			return '';
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			$decoded = base64_decode( $encrypted, true );
			return false === $decoded ? '' : $decoded;
		}

		$data = base64_decode( $encrypted, true );
		if ( false === $data || strlen( $data ) < 17 ) {
			return '';
		}

		$iv         = substr( $data, 0, 16 );
		$ciphertext = substr( $data, 16 );
		$plain      = openssl_decrypt( $ciphertext, 'AES-256-CBC', self::encryption_key(), OPENSSL_RAW_DATA, $iv );

		return false === $plain ? '' : $plain;
	}
}
