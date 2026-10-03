<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LinkPey_Ajax {

	public static function init() {
		add_action( 'wp_ajax_linkpey_test_connection', array( __CLASS__, 'test_connection' ) );
		add_action( 'wp_ajax_linkpey_run_diagnostic', array( __CLASS__, 'run_diagnostic' ) );
		add_action( 'wp_ajax_linkpey_transport_probe', array( __CLASS__, 'transport_probe' ) );
		add_action( 'wp_ajax_linkpey_get_channels', array( __CLASS__, 'get_channels' ) );
		add_action( 'wp_ajax_linkpey_preview_message', array( __CLASS__, 'preview_message' ) );
		add_action( 'wp_ajax_linkpey_send_post', array( __CLASS__, 'send_post' ) );
	}

	public static function test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'linkpey' ) ), 403 );
		}

		check_ajax_referer( 'linkpey_test_connection', 'nonce' );

		$token_input = filter_input( INPUT_POST, 'api_token', FILTER_UNSAFE_RAW );
		$token_input = is_string( $token_input ) ? trim( wp_unslash( $token_input ) ) : '';

		if ( '' !== $token_input ) {
			$client = new LinkPey_Api_Client( $token_input );
		} else {
			$client = new LinkPey_Api_Client();
		}

		if ( ! $client->has_token() ) {
			wp_send_json_error(
				array(
					'message' => __( 'ابتدا API Token را وارد کنید یا ذخیره کنید.', 'linkpey' ),
					'hint'    => __( 'توکن را در کادر بالا وارد کرده و «ذخیره تنظیمات» یا «تست اتصال» را بزنید.', 'linkpey' ),
				)
			);
		}

		$response = $client->test_connection();

		if ( is_wp_error( $response ) ) {
			$error = LinkPey_Api_Client::format_error_for_admin( $response );
			$error_data = $response->get_error_data();
			wp_send_json_error(
				array(
					'message'   => $error['message'],
					'hint'      => $error['hint'],
					'technical' => $error['technical'],
					'api_url'   => is_array( $error_data ) && ! empty( $error_data['url'] ) ? $error_data['url'] : $client->get_api_base_url() . 'channels/',
				)
			);
		}

		$channels = isset( $response['channels'] ) ? $response['channels'] : array();
		$debug    = isset( $response['debug'] ) ? $response['debug'] : array();
		$count    = count( $channels );

		$payload = array(
			'message' => $count > 0
				? __( 'اتصال با API لینک‌پی برقرار است.', 'linkpey' )
				: __( 'پاسخ API دریافت شد اما ۰ کانال برگشت.', 'linkpey' ),
			'count'   => $count,
			'debug'   => $debug,
		);

		if ( 0 === $count ) {
			$payload['warning'] = true;
			$payload['hint']    = __(
				'۱) توکن از همان حساب پنل لینک‌پی باشد. ۲) کانال‌ها برای API فعال باشند. ۳) اگر از رایانه با curl کانال می‌بینید ولی اینجا ۰ است، «تشخیص از سرور» را بزنید. ۴) افزونه را فقط با ZIP رسمی آپدیت کنید (نسخه wordpress.org قدیمی است).',
				'linkpey'
			);
		}

		wp_send_json_success( $payload );
	}

	public static function run_diagnostic() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'linkpey' ) ), 403 );
		}

		check_ajax_referer( 'linkpey_run_diagnostic', 'nonce' );

		$token_input = filter_input( INPUT_POST, 'api_token', FILTER_UNSAFE_RAW );
		$token_input = is_string( $token_input ) ? trim( wp_unslash( $token_input ) ) : '';

		$report = LinkPey_Diagnostic::run( $token_input );

		wp_send_json_success( array( 'report' => $report ) );
	}

	public static function transport_probe() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'linkpey' ) ), 403 );
		}

		check_ajax_referer( 'linkpey_transport_probe', 'nonce' );

		$token_input = filter_input( INPUT_POST, 'api_token', FILTER_UNSAFE_RAW );
		$token_input = is_string( $token_input ) ? trim( wp_unslash( $token_input ) ) : '';

		$method = filter_input( INPUT_POST, 'transport', FILTER_UNSAFE_RAW );
		$method = is_string( $method ) ? sanitize_key( wp_unslash( $method ) ) : 'all';
		if ( '' === $method ) {
			$method = 'all';
		}

		$report = LinkPey_Transport_Probe::run( $token_input, $method );
		if ( isset( $report['error'] ) ) {
			wp_send_json_error( array( 'message' => $report['error'] ) );
		}

		wp_send_json_success( array( 'report' => $report ) );
	}

	public static function get_channels() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'linkpey' ) ), 403 );
		}

		check_ajax_referer( 'linkpey_get_channels', 'nonce' );

		$force  = (bool) filter_input( INPUT_POST, 'force_refresh', FILTER_VALIDATE_BOOLEAN );
		$client = new LinkPey_Api_Client();
		$channels = $client->get_channels( $force );

		if ( is_wp_error( $channels ) ) {
			wp_send_json_error(
				array( 'message' => LinkPey_Api_Client::map_error_message( $channels ) )
			);
		}

		$last_selected = get_user_meta( get_current_user_id(), 'linkpey_last_selected_channels', true );
		if ( ! is_array( $last_selected ) ) {
			$last_selected = array();
		}

		wp_send_json_success(
			array(
				'channels'      => $channels,
				'last_selected' => $last_selected,
			)
		);
	}

	public static function preview_message() {
		$post_id = filter_input( INPUT_POST, 'post_id', FILTER_VALIDATE_INT );
		$post_id = $post_id ? $post_id : 0;

		self::verify_post_access( $post_id, 'linkpey_preview_' );

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'مطلب یافت نشد.', 'linkpey' ) ) );
		}

		$options     = self::parse_options();
		$message     = LinkPey_Message_Builder::build( $post, $options );
		$image_count = ! empty( $options['include_images'] )
			? LinkPey_Message_Builder::count_images( $post )
			: 0;

		wp_send_json_success(
			array(
				'message'     => $message,
				'length'      => mb_strlen( $message, 'UTF-8' ),
				'image_count' => $image_count,
				'post_status' => $post->post_status,
			)
		);
	}

	public static function send_post() {
		$post_id = filter_input( INPUT_POST, 'post_id', FILTER_VALIDATE_INT );
		$post_id = $post_id ? $post_id : 0;

		self::verify_post_access( $post_id, 'linkpey_send_' );

		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ارسال ندارید.', 'linkpey' ) ), 403 );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'مطلب یافت نشد.', 'linkpey' ) ) );
		}

		$channels = self::parse_channels_request();
		if ( empty( $channels ) ) {
			wp_send_json_error( array( 'message' => __( 'حداقل یک کانال انتخاب کنید.', 'linkpey' ) ) );
		}

		$options = self::parse_options();
		$message = LinkPey_Message_Builder::build( $post, $options );
		$length  = mb_strlen( $message, 'UTF-8' );

		if ( '' === trim( $message ) && empty( $options['include_images'] ) ) {
			wp_send_json_error( array( 'message' => __( 'متن پیام خالی است.', 'linkpey' ) ) );
		}

		if ( $length > LINKPEY_MAX_MESSAGE_LENGTH ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: number of characters over the limit */
						__( 'متن %d کاراکتر بیش از حد مجاز (۴۰۰۰) است.', 'linkpey' ),
						$length - LINKPEY_MAX_MESSAGE_LENGTH
					),
				)
			);
		}

		$media_files = array();
		if ( ! empty( $options['include_images'] ) ) {
			$media_files = LinkPey_Message_Builder::collect_image_files( $post );
		}

		$client  = new LinkPey_Api_Client();
		$results = array();
		$success = 0;

		$seen = array();
		foreach ( $channels as $channel ) {
			$platform         = LinkPey_Platform::normalize( $channel['platform'] );
			$chat_id          = $channel['entity_id'];
			$title            = $channel['title'];
			$platform_display = $channel['platform_display_name'];

			if ( '' === $platform || '' === $chat_id ) {
				continue;
			}

			$dedupe_key = $platform . ':' . $chat_id;
			if ( isset( $seen[ $dedupe_key ] ) ) {
				continue;
			}
			$seen[ $dedupe_key ] = true;

			$response = $client->send_to_channel( $platform, $chat_id, $message, $media_files );
			if ( is_wp_error( $response ) ) {
				$error_detail = LinkPey_Api_Client::format_error_for_admin( $response );
				$fail_message = $error_detail['message'];
				if ( ! empty( $error_detail['hint'] ) ) {
					$fail_message .= ' ' . $error_detail['hint'];
				}
				$results[] = array(
					'channel'               => $title,
					'platform'              => $platform,
					'platform_display_name' => $platform_display,
					'success'               => false,
					'message'               => $fail_message,
				);
				continue;
			}

			$success++;
			$results[] = array(
				'channel'               => $title,
				'platform'              => $platform,
				'platform_display_name' => $platform_display,
				'success'               => true,
				'message'               => isset( $response['message'] ) ? sanitize_text_field( $response['message'] ) : __( 'ارسال شد', 'linkpey' ),
			);
		}

		if ( empty( $results ) ) {
			wp_send_json_error( array( 'message' => __( 'هیچ کانال معتبری برای ارسال انتخاب نشده است.', 'linkpey' ) ) );
		}

		$selected_keys = array();
		foreach ( $channels as $channel ) {
			if ( '' === $channel['platform'] || '' === $channel['entity_id'] ) {
				continue;
			}
			$selected_keys[] = $channel['platform'] . ':' . $channel['entity_id'];
		}
		update_user_meta( get_current_user_id(), 'linkpey_last_selected_channels', array_values( array_unique( $selected_keys ) ) );

		$history = get_post_meta( $post_id, '_linkpey_send_history', true );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$history[] = array(
			'time'    => current_time( 'mysql' ),
			'message' => $message,
			'success' => $success,
			'total'   => count( $results ),
			'results' => $results,
		);

		update_post_meta( $post_id, '_linkpey_send_history', array_slice( $history, -20 ) );

		wp_send_json_success(
			array(
				'results' => $results,
				'success' => $success,
				'total'   => count( $results ),
			)
		);
	}

	private static function verify_post_access( $post_id, $nonce_prefix ) {
		if ( $post_id > 0 ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'linkpey' ) ), 403 );
			}
			check_ajax_referer( $nonce_prefix . $post_id, 'nonce' );
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'linkpey' ) ), 403 );
		}

		check_ajax_referer( $nonce_prefix . '0', 'nonce' );
	}

	/**
	 * Parse selected channels from the AJAX request.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function parse_channels_request() {
		$raw = filter_input( INPUT_POST, 'channels', FILTER_UNSAFE_RAW );
		if ( null === $raw || ! is_string( $raw ) ) {
			return array();
		}

		$decoded = json_decode( wp_unslash( $raw ), true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$channels = array();
		foreach ( $decoded as $channel ) {
			if ( ! is_array( $channel ) ) {
				continue;
			}

			$platform = isset( $channel['platform'] ) ? sanitize_text_field( (string) $channel['platform'] ) : '';
			$entity_id = isset( $channel['entity_id'] ) ? sanitize_text_field( (string) $channel['entity_id'] ) : '';
			$title = isset( $channel['title'] ) ? sanitize_text_field( (string) $channel['title'] ) : $entity_id;
			$platform_display = isset( $channel['platform_display_name'] )
				? sanitize_text_field( (string) $channel['platform_display_name'] )
				: $platform;

			if ( '' === $platform || '' === $entity_id ) {
				continue;
			}

			$channels[] = array(
				'platform'              => $platform,
				'entity_id'             => $entity_id,
				'title'                 => $title,
				'platform_display_name' => $platform_display,
			);
		}

		return $channels;
	}

	/**
	 * Parse message option flags from the AJAX request.
	 *
	 * @return array<string, bool>
	 */
	private static function parse_options() {
		return array(
			'include_title'   => (bool) filter_input( INPUT_POST, 'include_title', FILTER_VALIDATE_BOOLEAN ),
			'include_excerpt' => (bool) filter_input( INPUT_POST, 'include_excerpt', FILTER_VALIDATE_BOOLEAN ),
			'include_content' => (bool) filter_input( INPUT_POST, 'include_content', FILTER_VALIDATE_BOOLEAN ),
			'include_link'    => (bool) filter_input( INPUT_POST, 'include_link', FILTER_VALIDATE_BOOLEAN ),
			'include_images'  => (bool) filter_input( INPUT_POST, 'include_images', FILTER_VALIDATE_BOOLEAN ),
		);
	}
}
