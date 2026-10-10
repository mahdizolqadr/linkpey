<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LinkPey_Diagnostic {

	/**
	 * Run outbound API diagnostic from the WordPress server (admin-only).
	 *
	 * @param string $token_override Optional token from settings field.
	 * @return array<string, mixed>
	 */
	public static function run( $token_override = '' ) {
		$api_base = LinkPey::get_api_base_url();
		$url      = trailingslashit( $api_base ) . 'channels/';
		$token    = '' !== trim( (string) $token_override ) ? trim( (string) $token_override ) : LinkPey::get_api_token();

		$report = array(
			'plugin_version'              => defined( 'LINKPEY_VERSION' ) ? LINKPEY_VERSION : 'unknown',
			'site_url'                    => site_url(),
			'active_theme'                => wp_get_theme()->get( 'Name' ),
			'api_base'                    => $api_base,
			'request_url'                 => $url,
			'token_present'               => '' !== $token,
			'token_prefix'                => '' !== $token ? substr( $token, 0, 12 ) . '…' : '',
			'php_curl'                    => function_exists( 'curl_init' ),
			'allow_url_fopen'             => (bool) ini_get( 'allow_url_fopen' ),
			'wp_http_block_external'      => defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL,
			'pre_http_request_callbacks'  => self::list_hook_callbacks( 'pre_http_request' ),
			'mu_plugins'                  => array_keys( get_mu_plugins() ),
		);

		if ( '' === $token ) {
			$report['error'] = __( 'توکن API خالی است.', 'linkpey' );
			return $report;
		}

		$headers = array(
			'API-Token'             => $token,
			'Accept'                => 'application/json',
			'X-LinkPey-Plugin-Test' => '1',
			'User-Agent'            => 'LinkPey-Diagnostic/' . LINKPEY_VERSION,
		);

		$args = array(
			'method'      => 'GET',
			'timeout'     => 30,
			'sslverify'   => true,
			'redirection' => 0,
			'headers'     => $headers,
		);

		$started  = microtime( true );
		$response = wp_remote_request( $url, $args );
		$elapsed  = (int) round( ( microtime( true ) - $started ) * 1000 );

		$report['elapsed_ms'] = $elapsed;
		$report['direct_curl'] = self::direct_curl_probe( $url, $headers );

		if ( is_wp_error( $response ) ) {
			$report['transport_error']   = $response->get_error_code();
			$report['transport_message'] = $response->get_error_message();
			$report['conclusion']        = __( 'درخواست از سرور وردپرس به لینک‌پی ارسال نشد (خطای شبکه/هاست).', 'linkpey' );
			self::append_curl_vs_wp_hint( $report );
			return $report;
		}

		$code         = (int) wp_remote_retrieve_response_code( $response );
		$body         = (string) wp_remote_retrieve_body( $response );
		$content_type = (string) wp_remote_retrieve_header( $response, 'content-type' );
		$api_header   = (string) wp_remote_retrieve_header( $response, 'x-linkpey-api' );

		$report['http_status']   = $code;
		$report['content_type']  = $content_type;
		$report['x_linkpey_api'] = $api_header;
		$report['body_length']   = strlen( $body );
		$report['body_preview']  = substr( $body, 0, 500 );

		$data                    = json_decode( $body, true );
		$json_error              = JSON_ERROR_NONE === json_last_error() ? '' : json_last_error_msg();
		$report['json_error']    = $json_error;
		$report['json_is_array'] = is_array( $data );
		$report['channel_count'] = is_array( $data ) && array_values( $data ) === $data ? count( $data ) : null;

		$host                  = wp_parse_url( $url, PHP_URL_HOST );
		$expect_linkpey_header = is_string( $host ) && preg_match( '/(?:^|\.)linkpey\.com$/i', $host );

		if ( 0 === $code ) {
			$report['conclusion'] = __( 'کد HTTP صفر — درخواست وردپرس به شبکه نرسیده یا توسط pre_http_request قطع/جعل شده. لیست pre_http_request_callbacks و direct_curl را ببینید.', 'linkpey' );
			self::append_curl_vs_wp_hint( $report );
		} elseif ( 200 !== $code ) {
			$report['conclusion'] = __( 'پاسخ HTTP غیر 200 — درخواست به API رسیده ولی خطا برگشته.', 'linkpey' );
		} elseif ( '' === trim( $body ) ) {
			$report['conclusion'] = __( 'پاسخ خالی — معمولاً درخواست واقعی به لینک‌پی نرسیده یا مسیر API اشتباه است.', 'linkpey' );
		} elseif ( '' !== $json_error ) {
			$report['conclusion'] = __( 'پاسخ JSON نیست (احتمالاً HTML/خطای هاست) — به لینک‌پی API نرسیده.', 'linkpey' );
		} elseif ( $expect_linkpey_header && '1' !== $api_header ) {
			$report['conclusion'] = __( 'هدر X-LinkPey-Api نیست — این پاسخ از API واقعی لینک‌پی نیست (فیلتر/آدرس اشتباه).', 'linkpey' );
		} elseif ( 0 === (int) $report['channel_count'] && trim( $body ) === '[]' ) {
			$report['conclusion'] = __( 'JSON خالی [] — اگر در پنل لینک‌پی کانال دارید، این پاسخ مشکوک است.', 'linkpey' );
		} else {
			$report['conclusion'] = __( 'درخواست از سرور وردپرس به API لینک‌پی رسید و پاسخ معتبر است.', 'linkpey' );
		}

		return $report;
	}

	/**
	 * @return list<array{priority:int,name:string}>
	 */
	private static function list_hook_callbacks( $tag ) {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $tag ] ) || ! is_a( $wp_filter[ $tag ], 'WP_Hook' ) ) {
			return array();
		}

		$list = array();
		foreach ( $wp_filter[ $tag ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $cb ) {
				$list[] = array(
					'priority' => (int) $priority,
					'name'     => self::callback_name( $cb['function'] ),
				);
			}
		}

		return $list;
	}

	/**
	 * @param callable|mixed $callback Hook callback.
	 */
	private static function callback_name( $callback ) {
		if ( is_string( $callback ) ) {
			return $callback;
		}
		if ( is_array( $callback ) && isset( $callback[1] ) ) {
			if ( is_object( $callback[0] ) ) {
				return get_class( $callback[0] ) . '->' . $callback[1];
			}
			return (string) $callback[0] . '->' . $callback[1];
		}
		if ( $callback instanceof Closure ) {
			return 'Closure';
		}
		return 'unknown';
	}

	/**
	 * Direct cURL probe (bypasses WordPress pre_http_request filters).
	 *
	 * @param string               $url     Request URL.
	 * @param array<string,string> $headers Headers.
	 * @return array<string, mixed>
	 */
	private static function direct_curl_probe( $url, $headers ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return array(
				'available' => false,
				'error'     => 'curl extension missing',
			);
		}

		$header_lines = array();
		foreach ( $headers as $key => $value ) {
			$header_lines[] = $key . ': ' . $value;
		}

		$ch = curl_init( $url );
		if ( false === $ch ) {
			return array(
				'available' => true,
				'error'     => 'curl_init failed',
			);
		}

		curl_setopt_array(
			$ch,
			array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_HTTPHEADER     => $header_lines,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2,
			)
		);

		$started  = microtime( true );
		$body     = curl_exec( $ch );
		$errno    = curl_errno( $ch );
		$errstr   = curl_error( $ch );
		$status   = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$elapsed  = (int) round( ( microtime( true ) - $started ) * 1000 );
		curl_close( $ch );

		$result = array(
			'available'   => true,
			'elapsed_ms'  => $elapsed,
			'http_status' => $status,
			'curl_errno'  => $errno,
			'curl_error'  => $errstr,
			'body_length' => is_string( $body ) ? strlen( $body ) : 0,
		);

		if ( is_string( $body ) && '' !== $body ) {
			$data = json_decode( $body, true );
			if ( is_array( $data ) && array_values( $data ) === $data ) {
				$result['channel_count'] = count( $data );
			}
		}

		return $result;
	}

	/**
	 * @param array<string, mixed> $report Report (by reference).
	 */
	private static function append_curl_vs_wp_hint( array &$report ) {
		$curl = isset( $report['direct_curl'] ) && is_array( $report['direct_curl'] ) ? $report['direct_curl'] : array();
		if ( ! empty( $curl['http_status'] ) && 200 === (int) $curl['http_status'] ) {
			$report['hint'] = __( 'cURL مستقیم به لینک‌پی OK است ولی wp_remote_request نه — معمولاً یک افزونه/قالب روی pre_http_request یا محدودیت HTTP API وردپرس.', 'linkpey' );
		} elseif ( ! empty( $curl['curl_errno'] ) ) {
			$report['hint'] = __( 'cURL هم به لینک‌پی وصل نشد — فایروال/DNS/outbound هاست را با پشتیبانی چک کنید.', 'linkpey' );
		}
	}
}
