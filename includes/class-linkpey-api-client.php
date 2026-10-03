<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LinkPey_Api_Client {

	private $api_base;
	private $token;

	public function __construct( $token_override = null ) {
		$this->api_base = LinkPey::get_api_base_url();
		if ( null !== $token_override && is_string( $token_override ) ) {
			$this->token = trim( $token_override );
		} else {
			$this->token = LinkPey::get_api_token();
		}
	}

	public function has_token() {
		return '' !== $this->token;
	}

	public function test_connection() {
		$cache_key = 'linkpey_channels_v2_' . md5( $this->token );
		delete_transient( $cache_key );

		$result = $this->fetch_channels_from_api( true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'channels' => $result['channels'],
			'debug'    => $result['debug'],
		);
	}

	public function get_api_base_url() {
		return $this->api_base;
	}

	public function get_channels( $force_refresh = false ) {
		if ( ! $this->has_token() ) {
			return new WP_Error( 'linkpey_no_token', __( 'API Token تنظیم نشده است.', 'linkpey' ) );
		}

		$cache_key = 'linkpey_channels_v2_' . md5( $this->token );
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$result = $this->fetch_channels_from_api( false );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$channels = LinkPey_Platform::normalize_channels( $result['channels'] );
		set_transient( $cache_key, $channels, 10 * MINUTE_IN_SECONDS );
		return $channels;
	}

	public function send_to_channel( $platform, $chat_id, $message, $media_files = array() ) {
		if ( ! $this->has_token() ) {
			return new WP_Error( 'linkpey_no_token', __( 'API Token تنظیم نشده است.', 'linkpey' ) );
		}

		$body = array(
			'platform' => LinkPey_Platform::normalize( sanitize_text_field( $platform ) ),
			'chat_id'  => sanitize_text_field( $chat_id ),
			'message'  => $message,
		);

		if ( ! empty( $media_files ) ) {
			$body['media_files'] = array_values( $media_files );
		}

		return $this->request(
			'POST',
			'send_to_channel/',
			$body,
			! empty( $media_files ) ? 120 : 30,
			'json_object'
		);
	}

	private function fetch_channels_from_api( $is_connection_test ) {
		$url      = $this->api_base . 'channels/';
		$response = $this->perform_http_request( 'GET', $url, null, 30 );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$parsed = $this->parse_channels_http_response( $response, $url, $is_connection_test );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		return $parsed;
	}

	private function request( $method, $endpoint, $body = null, $timeout = 30, $response_kind = 'json_any' ) {
		$url      = $this->api_base . ltrim( $endpoint, '/' );
		$response = $this->perform_http_request( $method, $url, $body, $timeout );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->parse_generic_http_response( $response, $url, $response_kind );
	}

	private function perform_http_request( $method, $url, $body, $timeout ) {
		$headers = array(
			'API-Token'              => $this->token,
			'Content-Type'           => 'application/json',
			'Accept'                 => 'application/json',
			'X-LinkPey-Plugin-Test'  => '1',
			'User-Agent'             => 'LinkPey-WordPress/' . LINKPEY_VERSION,
		);

		if ( $this->should_prefer_curl_transport() ) {
			$curl = self::request_via_curl( $method, $url, $body, (int) $timeout, $headers );
			if ( null !== $curl && (int) wp_remote_retrieve_response_code( $curl ) >= 200 ) {
				return $curl;
			}
		}

		$args = array(
			'method'      => $method,
			'timeout'     => (int) $timeout,
			'sslverify'   => true,
			'redirection' => 0,
			'headers'     => $headers,
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			$curl = self::request_via_curl( $method, $url, $body, (int) $timeout, $headers );
			if ( null !== $curl ) {
				$this->mark_prefer_curl_transport();
				return $curl;
			}
			return self::map_transport_error( $response, $url );
		}

		$code       = (int) wp_remote_retrieve_response_code( $response );
		$raw_body   = (string) wp_remote_retrieve_body( $response );
		$trim_body  = trim( $raw_body );
		$is_get     = 'GET' === strtoupper( (string) $method );
		$suspicious = ( 0 === $code )
			|| ( 200 === $code && '' === $trim_body )
			|| ( $is_get && 200 === $code && '[]' === $trim_body );

		if ( $suspicious ) {
			$curl = self::request_via_curl( $method, $url, $body, (int) $timeout, $headers );
			if ( null !== $curl && (int) wp_remote_retrieve_response_code( $curl ) > 0 ) {
				$curl_body = trim( (string) wp_remote_retrieve_body( $curl ) );
				if ( '' !== $curl_body && ( ! $is_get || '[]' !== $curl_body || '[]' !== $trim_body ) ) {
					$this->mark_prefer_curl_transport();
					return $curl;
				}
			}
		}

		return $response;
	}

	private function prefer_curl_transient_key() {
		return 'linkpey_prefer_curl_' . md5( $this->token . '|' . $this->api_base );
	}

	private function should_prefer_curl_transport() {
		if ( ! function_exists( 'curl_init' ) || '' === $this->token ) {
			return false;
		}

		return (bool) get_transient( $this->prefer_curl_transient_key() );
	}

	private function mark_prefer_curl_transport() {
		set_transient( $this->prefer_curl_transient_key(), 1, WEEK_IN_SECONDS );
	}

	/**
	 * Direct cURL when WordPress HTTP API is blocked or returns empty (pre_http_request, host limits).
	 *
	 * @param string               $method  HTTP method.
	 * @param string               $url     URL.
	 * @param array<string,mixed>|null $body Request body.
	 * @param int                  $timeout Timeout seconds.
	 * @param array<string,string> $headers Headers.
	 * @return array<string,mixed>|null WordPress-style response or null.
	 */
	private static function request_via_curl( $method, $url, $body, $timeout, $headers ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return null;
		}

		$header_lines = array();
		foreach ( $headers as $key => $value ) {
			$header_lines[] = $key . ': ' . $value;
		}

		$ch = curl_init( $url );
		if ( false === $ch ) {
			return null;
		}

		$method = strtoupper( $method );
		$opts   = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => max( 5, $timeout ),
			CURLOPT_HTTPHEADER     => $header_lines,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_HEADER         => true,
			CURLOPT_CUSTOMREQUEST  => $method,
		);

		if ( null !== $body && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$opts[ CURLOPT_POSTFIELDS ] = wp_json_encode( $body );
		}

		curl_setopt_array( $ch, $opts );
		$raw         = curl_exec( $ch );
		$errno       = curl_errno( $ch );
		$status      = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$header_size = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		curl_close( $ch );

		if ( false === $raw || 0 !== $errno || $header_size <= 0 ) {
			return null;
		}

		$header_blob = substr( $raw, 0, $header_size );
		$body        = substr( $raw, $header_size );
		$parsed                               = self::parse_curl_response_headers( $header_blob );
		$parsed['x-linkpey-plugin-transport'] = 'curl';

		return array(
			'headers'  => $parsed,
			'body'     => $body,
			'response' => array(
				'code'    => $status,
				'message' => get_status_header_desc( $status ),
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function parse_curl_response_headers( $header_blob ) {
		$lines   = preg_split( "/\r\n|\n|\r/", $header_blob );
		$headers = array();
		if ( ! is_array( $lines ) ) {
			return $headers;
		}

		foreach ( $lines as $line ) {
			if ( false === strpos( $line, ':' ) ) {
				continue;
			}
			list( $name, $value ) = explode( ':', $line, 2 );
			$name                 = strtolower( trim( $name ) );
			$value                = trim( $value );
			if ( '' === $name ) {
				continue;
			}
			$headers[ $name ] = isset( $headers[ $name ] ) ? $headers[ $name ] . ', ' . $value : $value;
		}

		return $headers;
	}

	private function parse_channels_http_response( $response, $url, $is_connection_test ) {
		$status_code  = (int) wp_remote_retrieve_response_code( $response );
		$raw_body     = wp_remote_retrieve_body( $response );
		$content_type = (string) wp_remote_retrieve_header( $response, 'content-type' );
		$body_length  = strlen( $raw_body );

		if ( 200 !== $status_code ) {
			$data = json_decode( $raw_body, true );
			$error_message = is_array( $data ) && isset( $data['error'] )
				? $data['error']
				: sprintf(
					/* translators: %d: HTTP status code */
					__( 'API پاسخ HTTP %d برگرداند.', 'linkpey' ),
					$status_code
				);

			return new WP_Error(
				'linkpey_api_error',
				sanitize_text_field( $error_message ),
				array(
					'status_code' => $status_code,
					'url'         => $url,
					'hint'        => __( 'توکن API یا آدرس API را بررسی کنید.', 'linkpey' ),
				)
			);
		}

		if ( '' === trim( $raw_body ) ) {
			return new WP_Error(
				'linkpey_invalid_response',
				__( 'پاسخ خالی دریافت شد؛ درخواست به API لینک‌پی نرسیده است.', 'linkpey' ),
				array(
					'hint'       => __( 'آدرس API را بررسی کنید. بعضی افزونه‌های امنیتی یا فایروال‌ها درخواست را بدون ارسال واقعی «موفق» نشان می‌دهند.', 'linkpey' ),
					'technical'  => 'HTTP 200, empty body',
					'url'        => $url,
				)
			);
		}

		$data = json_decode( $raw_body, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			$snippet = wp_html_excerpt( wp_strip_all_tags( $raw_body ), 160, '…' );
			return new WP_Error(
				'linkpey_invalid_response',
				__( 'پاسخ JSON معتبر از API لینک‌پی دریافت نشد.', 'linkpey' ),
				array(
					'hint'      => __( 'احتمالاً آدرس API به صفحه HTML یا سایت دیگری (نه linkpey.com) اشاره می‌کند.', 'linkpey' ),
					'technical' => 'JSON: ' . json_last_error_msg() . ' | snippet: ' . $snippet,
					'url'       => $url,
				)
			);
		}

		if ( ! $this->is_list_array( $data ) ) {
			return new WP_Error(
				'linkpey_invalid_response',
				__( 'فرمت پاسخ channels/ با API لینک‌پی مطابقت ندارد.', 'linkpey' ),
				array(
					'hint'      => __( 'آدرس API باید به https://linkpey.com/api/public/ اشاره کند.', 'linkpey' ),
					'technical' => 'Expected JSON array for channels list',
					'url'       => $url,
				)
			);
		}

		foreach ( $data as $item ) {
			if ( ! is_array( $item ) ) {
				return new WP_Error(
					'linkpey_invalid_response',
					__( 'ساختار کانال‌های برگشتی نامعتبر است.', 'linkpey' ),
					array( 'url' => $url )
				);
			}
			if ( ! array_key_exists( 'entity_id', $item ) || ! array_key_exists( 'platform', $item ) ) {
				return new WP_Error(
					'linkpey_invalid_response',
					__( 'پاسخ دریافتی شبیه API لینک‌پی نیست.', 'linkpey' ),
					array(
						'hint' => __( 'آدرس API یا مسیر channels/ را در تنظیمات بررسی کنید.', 'linkpey' ),
						'url'  => $url,
					)
				);
			}
		}

		return array(
			'channels' => $data,
			'debug'    => array(
				'url'          => $url,
				'http_status'  => $status_code,
				'body_length'  => $body_length,
				'content_type' => $content_type,
				'transport'    => wp_remote_retrieve_header( $response, 'x-linkpey-plugin-transport' ) ?: 'wp_http',
				'is_test'      => $is_connection_test,
			),
		);
	}

	private function parse_generic_http_response( $response, $url, $response_kind ) {
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$data        = json_decode( $raw_body, true );

		if ( $status_code >= 400 ) {
			$error_message = is_array( $data ) && isset( $data['error'] ) ? $data['error'] : __( 'خطای ناشناخته از API.', 'linkpey' );
			return new WP_Error(
				'linkpey_api_error',
				sanitize_text_field( $error_message ),
				array(
					'status_code' => $status_code,
					'url'         => $url,
				)
			);
		}

		if ( 'json_object' === $response_kind ) {
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
				return new WP_Error(
					'linkpey_invalid_response',
					__( 'پاسخ JSON معتبر از API دریافت نشد.', 'linkpey' ),
					array( 'url' => $url )
				);
			}
			return $data;
		}

		if ( 'json_array' === $response_kind ) {
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) || ! $this->is_list_array( $data ) ) {
				return new WP_Error(
					'linkpey_invalid_response',
					__( 'پاسخ JSON معتبر از API دریافت نشد.', 'linkpey' ),
					array( 'url' => $url )
				);
			}
			return $data;
		}

		return is_array( $data ) ? $data : array();
	}

	private function is_list_array( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}

		return array_values( $value ) === $value;
	}

	private static function map_transport_error( WP_Error $error, $url ) {
		$host       = wp_parse_url( $url, PHP_URL_HOST );
		$host_label = $host ? $host : __( 'سرور لینک‌پی', 'linkpey' );
		$raw        = strtolower( $error->get_error_message() );
		$code       = $error->get_error_code();

		$hint         = '';
		$user_message = '';

		if (
			false !== strpos( $raw, 'could not resolve' )
			|| false !== strpos( $raw, 'getaddrinfo' )
			|| false !== strpos( $raw, 'name or service not known' )
		) {
			$user_message = sprintf(
				/* translators: %s: API hostname */
				__( 'نام دامنه %s از روی سرور وردپرس شما resolve نمی‌شود (مشکل DNS).', 'linkpey' ),
				$host_label
			);
			$hint = __( 'DNS سرور یا هاست را بررسی کنید، یا موقتاً از DNS عمومی (مثل 1.1.1.1) استفاده کنید.', 'linkpey' );
		} elseif (
			false !== strpos( $raw, 'timed out' )
			|| false !== strpos( $raw, 'timeout' )
			|| 'http_request_timeout' === $code
		) {
			$user_message = sprintf(
				/* translators: %s: API hostname */
				__( 'درخواست به %s در زمان مجاز پاسخ نگرفت.', 'linkpey' ),
				$host_label
			);
			$hint = __( 'ممکن است فایروال، محدودیت خروجی (egress) یا فیلترینگ شبکه، اتصال را قطع کرده باشد.', 'linkpey' );
		} elseif (
			false !== strpos( $raw, 'failed to connect' )
			|| false !== strpos( $raw, 'connection refused' )
			|| false !== strpos( $raw, 'curl error 7' )
			|| false !== strpos( $raw, 'curl error 28' )
		) {
			$user_message = sprintf(
				/* translators: %s: API hostname */
				__( 'سرور وردپرس شما اصلاً نتوانست به %s وصل شود؛ درخواست به لینک‌پی ارسال نشد.', 'linkpey' ),
				$host_label
			);
			$hint = __( 'احتمالاً هاست یا سرور، درخواست‌های خروجی به APIهای خارجی را مسدود کرده (فایروال، security plugin، یا سیاست hosting). از پشتیبانی هاست بخواهید دسترسی HTTPS خروجی به linkpey.com را باز کند.', 'linkpey' );
		} elseif (
			false !== strpos( $raw, 'ssl' )
			|| false !== strpos( $raw, 'certificate' )
		) {
			$user_message = __( 'خطای SSL هنگام اتصال به لینک‌پی.', 'linkpey' );
			$hint         = __( 'تاریخ/ساعت سرور، گواهی CA، یا پروکسی SSL را بررسی کنید.', 'linkpey' );
		} elseif ( false !== strpos( $raw, 'blocked' ) || false !== strpos( $raw, 'forbidden' ) ) {
			$user_message = __( 'درخواست خروجی از سرور شما مسدود شده است.', 'linkpey' );
			$hint         = __( 'افزونه امنیتی، WAF، یا تنظیمات سرور ممکن است wp_remote_request را محدود کرده باشد.', 'linkpey' );
		} else {
			$user_message = sprintf(
				/* translators: %s: API hostname */
				__( 'ارتباط با %s برقرار نشد و درخواست به API لینک‌پی نرسید.', 'linkpey' ),
				$host_label
			);
			$hint = __( 'لاگ PHP/وب‌سرور را بررسی کنید یا از پشتیبانی هاست بپرسید آیا outbound HTTPS مجاز است.', 'linkpey' );
		}

		return new WP_Error(
			'linkpey_http_error',
			$user_message,
			array(
				'hint'       => $hint,
				'technical'  => $error->get_error_message(),
				'url'        => $url,
				'error_code' => $code,
			)
		);
	}

	public static function format_error_for_admin( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return array(
				'message' => __( 'خطای نامشخص.', 'linkpey' ),
				'hint'    => '',
			);
		}

		$data = $error->get_error_data();
		$hint = is_array( $data ) && ! empty( $data['hint'] ) ? $data['hint'] : '';

		if ( 'linkpey_api_error' === $error->get_error_code() && ! $hint ) {
			$hint = __( 'توکن API یا آدرس API را در تنظیمات بررسی کنید.', 'linkpey' );
		}

		return array(
			'message'   => $error->get_error_message(),
			'hint'      => $hint,
			'technical' => is_array( $data ) && ! empty( $data['technical'] ) ? $data['technical'] : '',
		);
	}

	public static function map_error_message( $error ) {
		$formatted = self::format_error_for_admin( $error );
		return $formatted['message'];
	}
}
