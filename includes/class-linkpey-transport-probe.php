<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LinkPey_Transport_Probe {

	/**
	 * @return array<string, array{label:string,description:string}>
	 */
	public static function method_catalog() {
		return array(
			'plugin_client'      => array(
				'label'       => __( 'پلاگین (پیش‌فرض + cURL پشتیبان)', 'linkpey' ),
				'description' => __( 'همان مسیر واقعی ارسال پست؛ در صورت خطای wp_remote از cURL استفاده می‌کند.', 'linkpey' ),
			),
			'wp_remote'          => array(
				'label'       => __( 'WordPress HTTP (wp_remote)', 'linkpey' ),
				'description' => __( 'فقط HTTP API وردپress؛ تحت تأثیر pre_http_request و افزونه‌های امنیتی.', 'linkpey' ),
			),
			'wp_remote_no_ssl'   => array(
				'label'       => __( 'WordPress HTTP (بدون بررسی SSL)', 'linkpey' ),
				'description' => __( 'فقط برای تشخیص خطای گواهی SSL روی هاست.', 'linkpey' ),
			),
			'curl'               => array(
				'label'       => __( 'PHP cURL مستقیم', 'linkpey' ),
				'description' => __( 'بدون فیلتر pre_http_request وردpress.', 'linkpey' ),
			),
			'fopen'              => array(
				'label'       => __( 'file_get_contents', 'linkpey' ),
				'description' => __( 'نیاز به allow_url_fopen؛ بدون pre_http_request.', 'linkpey' ),
			),
		);
	}

	/**
	 * @param string $token_override Token from settings field.
	 * @param string $method_id      Method key or "all".
	 * @return array<string, mixed>
	 */
	public static function run( $token_override, $method_id ) {
		$token = trim( (string) $token_override );
		if ( '' === $token ) {
			$token = LinkPey::get_api_token();
		}

		$url = trailingslashit( LinkPey::get_api_base_url() ) . 'channels/';

		if ( '' === $token ) {
			return array(
				'error' => __( 'توکن API خالی است.', 'linkpey' ),
			);
		}

		$catalog = self::method_catalog();
		$ids     = 'all' === $method_id ? array_keys( $catalog ) : array( $method_id );
		$results = array();

		foreach ( $ids as $id ) {
			if ( ! isset( $catalog[ $id ] ) ) {
				continue;
			}
			$results[] = self::run_one( $id, $catalog[ $id ], $url, $token );
		}

		return array(
			'request_url'    => $url,
			'results'        => $results,
			'recommendation' => self::build_recommendation( $results ),
		);
	}

	/**
	 * @param array{label:string,description:string} $meta Method meta.
	 * @return array<string, mixed>
	 */
	private static function run_one( $id, $meta, $url, $token ) {
		$started = microtime( true );
		$row     = array(
			'method'      => $id,
			'label'       => $meta['label'],
			'description' => $meta['description'],
			'available'   => true,
			'elapsed_ms'  => 0,
			'http_status' => 0,
			'body_length' => 0,
			'channel_count' => null,
			'valid_api_list' => false,
			'error'       => '',
			'body_preview' => '',
		);

		switch ( $id ) {
			case 'plugin_client':
				$client   = new LinkPey_Api_Client( $token );
				$response = $client->test_connection();
				$row['elapsed_ms'] = (int) round( ( microtime( true ) - $started ) * 1000 );
				if ( is_wp_error( $response ) ) {
					$row['error'] = $response->get_error_message();
					$data         = $response->get_error_data();
					if ( is_array( $data ) && ! empty( $data['technical'] ) ) {
						$row['error'] .= ' (' . $data['technical'] . ')';
					}
					break;
				}
				$channels = isset( $response['channels'] ) ? $response['channels'] : array();
				$debug    = isset( $response['debug'] ) ? $response['debug'] : array();
				$row['http_status']   = isset( $debug['http_status'] ) ? (int) $debug['http_status'] : 200;
				$row['body_length']   = isset( $debug['body_length'] ) ? (int) $debug['body_length'] : 0;
				$row['channel_count'] = count( $channels );
				$row['valid_api_list'] = true;
				$row['transport_used'] = isset( $debug['transport'] ) ? (string) $debug['transport'] : '';
				break;

			case 'wp_remote':
				$probe = self::probe_wp_remote( $url, $token, true );
				$row   = array_merge( $row, $probe );
				break;

			case 'wp_remote_no_ssl':
				$probe = self::probe_wp_remote( $url, $token, false );
				$row   = array_merge( $row, $probe );
				break;

			case 'curl':
				$probe = self::probe_curl( $url, $token );
				$row   = array_merge( $row, $probe );
				break;

			case 'fopen':
				$probe = self::probe_fopen( $url, $token );
				$row   = array_merge( $row, $probe );
				break;
		}

		if ( 0 === $row['elapsed_ms'] ) {
			$row['elapsed_ms'] = (int) round( ( microtime( true ) - $started ) * 1000 );
		}

		$row['ok'] = ( 200 === (int) $row['http_status'] && $row['valid_api_list'] && null !== $row['channel_count'] );

		return $row;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function probe_wp_remote( $url, $token, $sslverify ) {
		$args = array(
			'method'      => 'GET',
			'timeout'     => 30,
			'sslverify'   => $sslverify,
			'redirection' => 0,
			'headers'     => self::request_headers( $token, 'WP-Remote-Probe' ),
		);

		$started  = microtime( true );
		$response = wp_remote_request( $url, $args );
		$elapsed  = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return array(
				'elapsed_ms'  => $elapsed,
				'error'       => $response->get_error_code() . ': ' . $response->get_error_message(),
				'http_status' => 0,
			);
		}

		return self::summarize_http_body(
			(int) wp_remote_retrieve_response_code( $response ),
			(string) wp_remote_retrieve_body( $response ),
			$elapsed
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function probe_curl( $url, $token ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return array(
				'available' => false,
				'error'     => __( 'افزونه cURL روی PHP فعال نیست.', 'linkpey' ),
			);
		}

		$headers = self::request_headers( $token, 'cURL-Probe' );
		$lines   = array();
		foreach ( $headers as $key => $value ) {
			$lines[] = $key . ': ' . $value;
		}

		$ch = curl_init( $url );
		if ( false === $ch ) {
			return array( 'error' => 'curl_init failed' );
		}

		curl_setopt_array(
			$ch,
			array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_HTTPHEADER     => $lines,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2,
			)
		);

		$started = microtime( true );
		$body    = curl_exec( $ch );
		$errno   = curl_errno( $ch );
		$errstr  = curl_error( $ch );
		$status  = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );
		$elapsed = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( 0 !== $errno ) {
			return array(
				'elapsed_ms'  => $elapsed,
				'http_status' => $status,
				'error'       => 'curl ' . $errno . ': ' . $errstr,
			);
		}

		return self::summarize_http_body( $status, is_string( $body ) ? $body : '', $elapsed );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function probe_fopen( $url, $token ) {
		if ( ! ini_get( 'allow_url_fopen' ) ) {
			return array(
				'available' => false,
				'error'     => __( 'allow_url_fopen در PHP غیرفعال است.', 'linkpey' ),
			);
		}

		$headers = self::request_headers( $token, 'Fopen-Probe' );
		$header_lines = array();
		foreach ( $headers as $key => $value ) {
			$header_lines[] = $key . ': ' . $value;
		}

		$context = stream_context_create(
			array(
				'http' => array(
					'method'        => 'GET',
					'timeout'       => 30,
					'header'        => implode( "\r\n", $header_lines ),
					'ignore_errors' => true,
				),
				'ssl'  => array(
					'verify_peer'      => true,
					'verify_peer_name' => true,
				),
			)
		);

		$started = microtime( true );
		$body    = @file_get_contents( $url, false, $context );
		$elapsed = (int) round( ( microtime( true ) - $started ) * 1000 );

		$status = 0;
		if ( isset( $http_response_header[0] ) && preg_match( '/\s(\d{3})\s/', $http_response_header[0], $m ) ) {
			$status = (int) $m[1];
		}

		if ( false === $body ) {
			return array(
				'elapsed_ms'  => $elapsed,
				'http_status' => $status,
				'error'       => __( 'file_get_contents ناموفق بود.', 'linkpey' ),
			);
		}

		return self::summarize_http_body( $status, (string) $body, $elapsed );
	}

	/**
	 * @return array<string, string>
	 */
	private static function request_headers( $token, $probe_name ) {
		return array(
			'API-Token'             => $token,
			'Accept'                => 'application/json',
			'X-LinkPey-Plugin-Test' => '1',
			'User-Agent'            => 'LinkPey-' . $probe_name . '/' . LINKPEY_VERSION,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function summarize_http_body( $status, $body, $elapsed ) {
		$parsed = self::parse_channel_list( $body );

		return array(
			'elapsed_ms'       => $elapsed,
			'http_status'      => $status,
			'body_length'      => strlen( $body ),
			'body_preview'     => substr( $body, 0, 200 ),
			'channel_count'    => $parsed['channel_count'],
			'valid_api_list'   => $parsed['valid_api_list'],
			'json_error'       => $parsed['json_error'],
		);
	}

	/**
	 * @return array{channel_count:int|null,valid_api_list:bool,json_error:string}
	 */
	private static function parse_channel_list( $body ) {
		$data = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return array(
				'channel_count'  => null,
				'valid_api_list' => false,
				'json_error'     => JSON_ERROR_NONE === json_last_error() ? 'not_array' : json_last_error_msg(),
			);
		}

		if ( array_values( $data ) !== $data ) {
			return array(
				'channel_count'  => null,
				'valid_api_list' => false,
				'json_error'     => 'expected_list',
			);
		}

		foreach ( $data as $item ) {
			if ( ! is_array( $item ) || ! array_key_exists( 'entity_id', $item ) || ! array_key_exists( 'platform', $item ) ) {
				return array(
					'channel_count'  => count( $data ),
					'valid_api_list' => false,
					'json_error'     => 'invalid_channel_shape',
				);
			}
		}

		return array(
			'channel_count'  => count( $data ),
			'valid_api_list' => true,
			'json_error'     => '',
		);
	}

	/**
	 * @param list<array<string,mixed>> $results Probe rows.
	 */
	private static function build_recommendation( array $results ) {
		$with_channels = array();
		$reachable     = array();

		foreach ( $results as $row ) {
			if ( empty( $row['valid_api_list'] ) || 200 !== (int) $row['http_status'] ) {
				continue;
			}
			$reachable[] = $row['label'];
			if ( isset( $row['channel_count'] ) && (int) $row['channel_count'] > 0 ) {
				$with_channels[] = $row['label'];
			}
		}

		if ( ! empty( $with_channels ) ) {
			return sprintf(
				/* translators: %s: comma-separated method labels */
				__( 'برای دریافت کانال، این روش‌ها جواب دادند: %s. پلاگین در ارسال واقعی از «پیش‌فرض + cURL پشتیبان» استفاده می‌کند.', 'linkpey' ),
				implode( '، ', $with_channels )
			);
		}

		if ( ! empty( $reachable ) ) {
			return sprintf(
				/* translators: %s: comma-separated method labels */
				__( 'API reachable است ولی ۰ کانال: %s. توکن و «فعال برای API» کانال‌ها را در پنل چک کنید.', 'linkpey' ),
				implode( '، ', $reachable )
			);
		}

		return __( 'هیچ روشی پاسخ معتبر از channels/ نگرفت — outbound هاست یا فیلتر وردpress را با پشتیبانی هاست بررسی کنید.', 'linkpey' );
	}
}
