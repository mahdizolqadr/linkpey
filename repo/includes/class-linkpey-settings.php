<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LinkPey_Settings {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function register_menu() {
		add_options_page(
			__( 'تنظیمات لینک‌پی', 'linkpey' ),
			__( 'تنظیمات لینک‌پی', 'linkpey' ),
			'manage_options',
			'linkpey-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'linkpey_settings_group',
			'linkpey_api_base_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_api_base_url' ),
				'default'           => LINKPEY_DEFAULT_API_BASE,
			)
		);

		register_setting(
			'linkpey_settings_group',
			'linkpey_api_token_input',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_token_input' ),
			)
		);
	}

	public static function sanitize_api_base_url( $value ) {
		$value = esc_url_raw( trim( $value ) );
		if ( '' === $value ) {
			return LINKPEY_DEFAULT_API_BASE;
		}
		return trailingslashit( $value );
	}

	public static function sanitize_token_input( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$old_token = LinkPey::get_api_token();
		if ( '' !== $old_token ) {
			LinkPey::clear_channels_cache( $old_token );
		}

		update_option( 'linkpey_api_token_enc', LinkPey_Crypto::encrypt( $value ) );
		update_option( 'linkpey_api_token_prefix', substr( $value, 0, 12 ) . '…' );
		LinkPey::clear_channels_cache( $value );

		return '';
	}

	public static function enqueue_assets( $hook ) {
		if ( 'settings_page_linkpey-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'linkpey-admin',
			LINKPEY_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			LINKPEY_VERSION
		);

		wp_enqueue_script(
			'linkpey-settings',
			LINKPEY_PLUGIN_URL . 'assets/js/settings.js',
			array( 'jquery' ),
			LINKPEY_VERSION,
			true
		);

		wp_localize_script(
			'linkpey-settings',
			'linkpeySettings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'linkpey_test_connection' ),
				'diagNonce' => wp_create_nonce( 'linkpey_run_diagnostic' ),
				'transportNonce' => wp_create_nonce( 'linkpey_transport_probe' ),
				'i18n'    => array(
					'testing'     => __( 'در حال تست اتصال…', 'linkpey' ),
					'success'     => __( 'اتصال برقرار است.', 'linkpey' ),
					'failed'      => __( 'اتصال برقرار نیست.', 'linkpey' ),
					'channels'    => __( 'کانال', 'linkpey' ),
					'showToken'   => __( 'نمایش', 'linkpey' ),
					'hideToken'   => __( 'مخفی', 'linkpey' ),
					'noToken'     => __( 'توکن API خالی است.', 'linkpey' ),
					'noTokenHint' => __( 'توکن را در کادر بالا وارد کنید.', 'linkpey' ),
					'ajaxFailed'  => __( 'درخواست AJAX از مرورگر به وردپرس انجام نشد. افزونه امنیتی یا خطای JavaScript را بررسی کنید.', 'linkpey' ),
					'diagnosticRunning' => __( 'در حال اجرای تشخیص از سرور وردپرس…', 'linkpey' ),
					'diagnosticTitle'   => __( 'گزارش تشخیص (از سرور هاست)', 'linkpey' ),
					'transportRunning'  => __( 'در حال تست روش ارسال…', 'linkpey' ),
					'transportPick'     => __( 'یک روش را انتخاب کنید یا «همه» را بزنید.', 'linkpey' ),
				),
			)
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$api_base        = LinkPey::get_api_base_url();
		$has_token       = LinkPey::has_api_token();
		$token_value     = $has_token ? LinkPey::get_api_token() : '';
		$settings_saved  = isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'];
		$clear_flag      = isset( $_GET['linkpey_cleared'] ) && '1' === $_GET['linkpey_cleared'];

		if ( isset( $_POST['linkpey_clear_token'] ) && check_admin_referer( 'linkpey_clear_token' ) ) {
			LinkPey::clear_channels_cache();
			delete_option( 'linkpey_api_token_enc' );
			delete_option( 'linkpey_api_token_prefix' );
			wp_safe_redirect( admin_url( 'options-general.php?page=linkpey-settings&linkpey_cleared=1' ) );
			exit;
		}

		?>
		<div class="wrap linkpey-wrap" dir="rtl">
			<h1><?php esc_html_e( 'تنظیمات لینک‌پی', 'linkpey' ); ?></h1>

			<?php if ( $settings_saved && $has_token ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'تنظیمات ذخیره شد. توکن API فعال است.', 'linkpey' ); ?></p></div>
			<?php elseif ( $settings_saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'تنظیمات ذخیره شد.', 'linkpey' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $clear_flag ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'توکن API حذف شد.', 'linkpey' ); ?></p></div>
			<?php endif; ?>

			<div class="linkpey-card">
				<p class="linkpey-status">
					<strong><?php esc_html_e( 'وضعیت اتصال:', 'linkpey' ); ?></strong>
					<span class="linkpey-badge <?php echo $has_token ? 'is-connected' : 'is-disconnected'; ?>">
						<?php echo $has_token ? esc_html__( 'توکن ذخیره شده', 'linkpey' ) : esc_html__( 'توکن تنظیم نشده', 'linkpey' ); ?>
					</span>
				</p>

				<form method="post" action="options.php">
					<?php settings_fields( 'linkpey_settings_group' ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="linkpey_api_base_url"><?php esc_html_e( 'آدرس API', 'linkpey' ); ?></label></th>
							<td>
								<input type="url" class="regular-text ltr" id="linkpey_api_base_url" name="linkpey_api_base_url" value="<?php echo esc_attr( $api_base ); ?>" />
								<p class="description"><?php esc_html_e( 'معمولاً نیازی به تغییر نیست.', 'linkpey' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="linkpey_api_token_input"><?php esc_html_e( 'API Token', 'linkpey' ); ?></label></th>
							<td>
								<input type="password" class="regular-text ltr" id="linkpey_api_token_input" name="linkpey_api_token_input" value="<?php echo esc_attr( $token_value ); ?>" autocomplete="off" placeholder="<?php echo esc_attr__( 'lp_...', 'linkpey' ); ?>" />
								<button type="button" class="button linkpey-toggle-token" aria-label="<?php esc_attr_e( 'نمایش توکن', 'linkpey' ); ?>"><?php esc_html_e( 'نمایش', 'linkpey' ); ?></button>
								<?php if ( $has_token ) : ?>
									<p class="description"><?php esc_html_e( 'توکن ذخیره شده و در همین کادر نمایش داده می‌شود. برای تغییر، توکن جدید وارد کنید و ذخیره کنید.', 'linkpey' ); ?></p>
								<?php endif; ?>
								<p class="description">
									<?php
									printf(
										/* translators: %s: LinkPey panel URL */
										esc_html__( 'توکن را از %s دریافت کنید.', 'linkpey' ),
										'<a href="https://linkpey.com" target="_blank" rel="noopener noreferrer">' . esc_html__( 'پنل لینک‌پی', 'linkpey' ) . '</a>'
									);
									?>
								</p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'ذخیره تنظیمات', 'linkpey' ) ); ?>
				</form>

				<div class="linkpey-actions-row">
					<button type="button" class="button button-secondary" id="linkpey-test-connection">
						<?php esc_html_e( 'تست اتصال', 'linkpey' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="linkpey-run-diagnostic">
						<?php esc_html_e( 'تشخیص از سرور (پیشرفته)', 'linkpey' ); ?>
					</button>
					<span id="linkpey-test-result" aria-live="polite"></span>
				</div>
				<p class="description"><?php esc_html_e( 'اگر «تست اتصال» سبز شد ولی در لینک‌پی لاگی نمی‌بینید، «تشخیص از سرور» را بزنید و گزارش را برای پشتیبانی بفرستید.', 'linkpey' ); ?></p>

				<div class="linkpey-transport-section">
					<h2 class="linkpey-subheading"><?php esc_html_e( 'تست روش‌های ارسال درخواست (از سرور هاست)', 'linkpey' ); ?></h2>
					<p class="description"><?php esc_html_e( 'هر دکمه همان توکن و آدرس API را با یک روش متفاوت به channels/ می‌زند تا ببینید کدام مسیر از هاست شما کار می‌کند.', 'linkpey' ); ?></p>
					<div class="linkpey-transport-buttons">
						<?php foreach ( LinkPey_Transport_Probe::method_catalog() as $method_id => $method_meta ) : ?>
							<button type="button" class="button button-secondary linkpey-transport-btn" data-transport="<?php echo esc_attr( $method_id ); ?>" title="<?php echo esc_attr( $method_meta['description'] ); ?>">
								<?php echo esc_html( $method_meta['label'] ); ?>
							</button>
						<?php endforeach; ?>
						<button type="button" class="button button-primary linkpey-transport-btn" data-transport="all">
							<?php esc_html_e( 'تست همه روش‌ها', 'linkpey' ); ?>
						</button>
					</div>
					<div id="linkpey-transport-wrap" class="linkpey-transport-wrap" hidden>
						<pre id="linkpey-transport-output" class="linkpey-diagnostic-output ltr" aria-live="polite"></pre>
					</div>
				</div>

				<div id="linkpey-diagnostic-wrap" class="linkpey-diagnostic-wrap" hidden>
					<strong><?php esc_html_e( 'گزارش تشخیص', 'linkpey' ); ?></strong>
					<pre id="linkpey-diagnostic-output" class="linkpey-diagnostic-output ltr" aria-live="polite"></pre>
				</div>

				<?php if ( $has_token ) : ?>
					<form method="post" class="linkpey-clear-form">
						<?php wp_nonce_field( 'linkpey_clear_token' ); ?>
						<button type="submit" name="linkpey_clear_token" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'توکن حذف شود؟', 'linkpey' ) ); ?>');">
							<?php esc_html_e( 'حذف توکن', 'linkpey' ); ?>
						</button>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
