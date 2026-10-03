<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LinkPey_Metabox {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_metabox' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function register_metabox() {
		$post_types = apply_filters( 'linkpey_post_types', array( 'post' ) );
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'linkpey-social-share',
				__( 'انتقال به شبکه‌های اجتماعی', 'linkpey' ),
				array( __CLASS__, 'render_metabox' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	public static function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'linkpey-admin',
			LINKPEY_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			LINKPEY_VERSION
		);

		wp_enqueue_script(
			'linkpey-admin',
			LINKPEY_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			LINKPEY_VERSION,
			true
		);

		global $post;

		$post_id = ( $post instanceof WP_Post ) ? (int) $post->ID : 0;
		wp_localize_script(
			'linkpey-admin',
			'linkpeyAdmin',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'postId'     => $post_id,
				'maxLength'  => LINKPEY_MAX_MESSAGE_LENGTH,
				'maxAlbum'   => LINKPEY_MAX_ALBUM_ITEMS,
				'hasToken'   => LinkPey::has_api_token(),
				'nonces'     => array(
					'preview'  => wp_create_nonce( 'linkpey_preview_' . $post_id ),
					'channels' => wp_create_nonce( 'linkpey_get_channels' ),
					'send'     => wp_create_nonce( 'linkpey_send_' . $post_id ),
				),
				'userId'     => get_current_user_id(),
				'i18n'       => array(
					'overLimit'      => __( 'متن بیش از ۴۰۰۰ کاراکتر است.', 'linkpey' ),
					'sending'        => __( 'در حال ارسال…', 'linkpey' ),
					/* translators: 1: number of channels, 2: number of platforms */
					'sendingTo'      => __( 'در حال ارسال به %1$d کانال در %2$d پلتفرم…', 'linkpey' ),
					'selectChannel'  => __( 'حداقل یک کانال انتخاب کنید.', 'linkpey' ),
					/* translators: 1: number of channels, 2: number of platforms */
					'confirmSend'    => __( 'ارسال به %1$d کانال در %2$d پلتفرم مختلف انجام شود؟', 'linkpey' ),
					'emptyMessage'   => __( 'متن پیام خالی است.', 'linkpey' ),
					'noChannels'     => __( 'کانالی برای API فعال نشده است.', 'linkpey' ),
					'noToken'        => __( 'ابتدا API Token را در تنظیمات لینک‌پی وارد کنید.', 'linkpey' ),
					'draftLink'      => __( 'لینک پس از انتشار مطلب اضافه می‌شود.', 'linkpey' ),
					'albumSingle'    => __( 'تک‌عکس با caption ارسال می‌شود.', 'linkpey' ),
					'albumMultiple'  => __( 'به‌صورت آلبوم ارسال می‌شود.', 'linkpey' ),
					'chars'          => __( 'کاراکتر', 'linkpey' ),
					/* translators: 1: selected channel count, 2: total channel count */
					'selectedCount'  => __( '%1$d از %2$d کانال انتخاب شده', 'linkpey' ),
					'selectPlatform' => __( 'انتخاب همه این پلتفرم', 'linkpey' ),
				),
			)
		);
	}

	public static function render_metabox( WP_Post $post ) {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		$can_publish = current_user_can( 'publish_posts' );
		$history     = get_post_meta( $post->ID, '_linkpey_send_history', true );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		?>
		<div class="linkpey-metabox" dir="rtl">
			<?php if ( ! LinkPey::has_api_token() ) : ?>
				<p class="linkpey-notice warning">
					<?php
					printf(
						wp_kses(
							/* translators: %s: settings page link */
							__( 'ابتدا <a href="%s">API Token</a> را تنظیم کنید.', 'linkpey' ),
							array( 'a' => array( 'href' => array() ) )
						),
						esc_url( admin_url( 'options-general.php?page=linkpey-settings' ) )
					);
					?>
				</p>
			<?php endif; ?>

			<fieldset class="linkpey-fieldset">
				<legend><?php esc_html_e( 'محتوای پیام', 'linkpey' ); ?></legend>
				<label><input type="checkbox" class="linkpey-option" data-option="include_title" checked /> <?php esc_html_e( 'عنوان', 'linkpey' ); ?></label>
				<label><input type="checkbox" class="linkpey-option" data-option="include_excerpt" /> <?php esc_html_e( 'خلاصه', 'linkpey' ); ?></label>
				<label><input type="checkbox" class="linkpey-option" data-option="include_content" /> <?php esc_html_e( 'محتوای کامل', 'linkpey' ); ?></label>
				<label><input type="checkbox" class="linkpey-option" data-option="include_link" checked /> <?php esc_html_e( 'لینک مطلب', 'linkpey' ); ?></label>
				<label><input type="checkbox" class="linkpey-option" data-option="include_images" /> <?php esc_html_e( 'تصاویر', 'linkpey' ); ?></label>
			</fieldset>

			<div class="linkpey-preview-wrap">
				<strong><?php esc_html_e( 'پیش‌نمایش', 'linkpey' ); ?></strong>
				<pre class="linkpey-preview" id="linkpey-preview" aria-live="polite"></pre>
				<div class="linkpey-counter" id="linkpey-counter" aria-live="polite"></div>
				<div class="linkpey-image-info" id="linkpey-image-info" aria-live="polite"></div>
			</div>

			<div class="linkpey-channels-wrap">
				<div class="linkpey-channels-header">
					<strong><?php esc_html_e( 'کانال‌ها', 'linkpey' ); ?></strong>
					<button type="button" class="button button-small" id="linkpey-refresh-channels"><?php esc_html_e( 'بروزرسانی', 'linkpey' ); ?></button>
				</div>
				<p class="linkpey-muted"><?php esc_html_e( 'می‌توانید چند کانال از پلتفرم‌های مختلف را هم‌زمان انتخاب کنید.', 'linkpey' ); ?></p>
				<div class="linkpey-channel-toolbar">
					<button type="button" class="button button-small" id="linkpey-select-all"><?php esc_html_e( 'انتخاب همه', 'linkpey' ); ?></button>
					<button type="button" class="button button-small" id="linkpey-deselect-all"><?php esc_html_e( 'حذف انتخاب', 'linkpey' ); ?></button>
					<span id="linkpey-selected-count" class="linkpey-selected-count" aria-live="polite"></span>
				</div>
				<div id="linkpey-channels-list" class="linkpey-channels-list" aria-live="polite">
					<p class="linkpey-muted"><?php esc_html_e( 'در حال بارگذاری…', 'linkpey' ); ?></p>
				</div>
			</div>

			<?php if ( 'publish' !== $post->post_status ) : ?>
				<p class="linkpey-notice info"><?php esc_html_e( 'لینک مطلب پس از انتشار اضافه می‌شود.', 'linkpey' ); ?></p>
			<?php endif; ?>

			<button type="button" class="button button-primary button-large linkpey-send-btn" id="linkpey-send-btn" <?php disabled( ! $can_publish || ! LinkPey::has_api_token() ); ?>>
				<?php esc_html_e( 'ارسال به کانال‌های انتخاب‌شده', 'linkpey' ); ?>
			</button>

			<div id="linkpey-send-results" class="linkpey-send-results" aria-live="polite"></div>

			<?php if ( ! empty( $history ) ) : ?>
				<div class="linkpey-history">
					<strong><?php esc_html_e( 'آخرین ارسال', 'linkpey' ); ?></strong>
					<ul>
						<?php foreach ( array_slice( array_reverse( $history ), 0, 3 ) as $entry ) : ?>
							<li>
								<?php
								echo esc_html(
									sprintf(
										'%s — %s — %d/%d موفق',
										isset( $entry['time'] ) ? $entry['time'] : '',
										isset( $entry['message'] ) ? wp_trim_words( $entry['message'], 8, '…' ) : '',
										isset( $entry['success'] ) ? (int) $entry['success'] : 0,
										isset( $entry['total'] ) ? (int) $entry['total'] : 0
									)
								);
								?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
