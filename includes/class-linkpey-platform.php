<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LinkPey_Platform {

	private static $valid_slugs = array(
		'telegram',
		'eita',
		'soroush',
		'rubika',
		'bale',
		'igap',
	);

	private static $aliases = array(
		'telegram' => 'telegram',
		'eita'     => 'eita',
		'eitaa'    => 'eita',
		'eit'      => 'eita',
		'soroush'  => 'soroush',
		'rubika'   => 'rubika',
		'bale'     => 'bale',
		'igap'     => 'igap',
	);

	public static function normalize( $value ) {
		$key = strtolower( trim( (string) $value ) );

		if ( isset( self::$aliases[ $key ] ) ) {
			return self::$aliases[ $key ];
		}

		return $key;
	}

	public static function is_valid( $slug ) {
		return in_array( self::normalize( $slug ), self::$valid_slugs, true );
	}

	public static function normalize_channel( $channel ) {
		if ( ! is_array( $channel ) ) {
			return $channel;
		}

		if ( isset( $channel['platform'] ) ) {
			$channel['platform'] = self::normalize( $channel['platform'] );
		}

		return $channel;
	}

	public static function normalize_channels( $channels ) {
		if ( ! is_array( $channels ) ) {
			return array();
		}

		return array_map( array( __CLASS__, 'normalize_channel' ), $channels );
	}
}
