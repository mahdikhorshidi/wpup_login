<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin settings page under Settings > Optiwise Login.
 */
class Wpup_Admin_Settings {

	const OPTION_GROUP = 'wpup_login_options';
	const PAGE_SLUG    = 'wpup-login';

	public function __construct() {
		add_action( 'admin_menu',           array( $this, 'add_menu' ) );
		add_action( 'admin_init',           array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wpup_test_sms', array( $this, 'ajax_test_sms' ) );
	}

	public function ajax_test_sms() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'wpup-login' ) ) );
		}
		check_ajax_referer( 'wpup_test_sms', 'nonce' );

		$mobile = sanitize_text_field( isset( $_POST['mobile'] ) ? $_POST['mobile'] : '' );
		$mobile = Wpup_Otp_Handler::sanitize_mobile( $mobile );

		if ( ! Wpup_Otp_Handler::is_valid_mobile( $mobile ) ) {
			wp_send_json_error( array( 'message' => __( 'شماره موبایل معتبر نیست.', 'wpup-login' ) ) );
		}

		$sms    = new Wpup_Sms_Api();
		$result = $sms->send( $mobile, (string) wp_rand( 100000, 999999 ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'پیامک با موفقیت ارسال شد. صندوق ورودی موبایل خود را چک کنید.', 'wpup-login' ) ) );
	}

	public function add_menu() {
		add_options_page(
			__( 'آپتی‌وایز لاگین', 'wpup-login' ),
			__( 'آپتی‌وایز لاگین', 'wpup-login' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wpup-admin', WPUP_LOGIN_URL . 'assets/css/wpup-admin.css', array(), WPUP_LOGIN_VERSION );
		wp_enqueue_script( 'wpup-admin', WPUP_LOGIN_URL . 'assets/js/wpup-admin.js', array( 'jquery' ), WPUP_LOGIN_VERSION, true );
	}

	public function register_settings() {
		$options = array(
			'wpup_sms_provider'         => 'sanitize_text_field',
			'wpup_ippanel_api_key'      => 'sanitize_text_field',
			'wpup_ippanel_sender'       => 'sanitize_text_field',
			'wpup_ippanel_pattern_code' => 'sanitize_text_field',
			'wpup_ippanel_pattern_var'  => 'sanitize_text_field',
			'wpup_otp_expiry'           => 'absint',
			'wpup_mobile_prompt_enable' => 'absint',
		);
		foreach ( $options as $opt => $cb ) {
			register_setting( self::OPTION_GROUP, $opt, array( 'sanitize_callback' => $cb ) );
		}

		// ── Section: Provider ─────────────────────────────────────────────────
		add_settings_section(
			'wpup_provider_section',
			__( 'سرویس‌دهنده پیامک', 'wpup-login' ),
			array( $this, 'section_provider_desc' ),
			self::PAGE_SLUG
		);
		add_settings_field( 'wpup_sms_provider', __( 'انتخاب سرویس‌دهنده', 'wpup-login' ), array( $this, 'field_provider' ), self::PAGE_SLUG, 'wpup_provider_section' );

		// ── Section: ippanel ──────────────────────────────────────────────────
		add_settings_section(
			'wpup_ippanel_section',
			__( 'تنظیمات ippanel', 'wpup-login' ),
			array( $this, 'section_ippanel_desc' ),
			self::PAGE_SLUG
		);
		add_settings_field( 'wpup_ippanel_api_key',      __( 'کلید API (apikey)', 'wpup-login' ),          array( $this, 'field_ippanel_api_key' ),      self::PAGE_SLUG, 'wpup_ippanel_section' );
		add_settings_field( 'wpup_ippanel_sender',       __( 'شماره فرستنده', 'wpup-login' ),               array( $this, 'field_ippanel_sender' ),       self::PAGE_SLUG, 'wpup_ippanel_section' );
		add_settings_field( 'wpup_ippanel_pattern_code', __( 'کد الگوی پیامک (Pattern Code)', 'wpup-login' ), array( $this, 'field_ippanel_pattern' ),    self::PAGE_SLUG, 'wpup_ippanel_section' );
		add_settings_field( 'wpup_ippanel_pattern_var',  __( 'نام متغیر در الگو', 'wpup-login' ),            array( $this, 'field_ippanel_pattern_var' ),  self::PAGE_SLUG, 'wpup_ippanel_section' );

		// ── Section: General ──────────────────────────────────────────────────
		add_settings_section(
			'wpup_general_section',
			__( 'تنظیمات عمومی', 'wpup-login' ),
			'__return_false',
			self::PAGE_SLUG
		);
		add_settings_field( 'wpup_otp_expiry',           __( 'مدت اعتبار کد OTP (ثانیه)', 'wpup-login' ),  array( $this, 'field_expiry' ),         self::PAGE_SLUG, 'wpup_general_section' );
		add_settings_field( 'wpup_mobile_prompt_enable', __( 'درخواست موبایل پس از ورود', 'wpup-login' ), array( $this, 'field_mobile_prompt' ),  self::PAGE_SLUG, 'wpup_general_section' );
	}

	// ── Section descriptions ──────────────────────────────────────────────────

	public function section_provider_desc() {
		echo '<p>' . esc_html__( 'مشخص کنید پیامک‌های OTP از کدام سرویس ارسال شوند.', 'wpup-login' ) . '</p>';
	}

	public function section_ippanel_desc() {
		echo '<p>' . esc_html__( 'این بخش فقط زمانی استفاده می‌شود که سرویس‌دهنده ippanel انتخاب شده باشد.', 'wpup-login' ) . '</p>';
	}

	// ── Fields ────────────────────────────────────────────────────────────────

	public function field_provider() {
		$value = get_option( 'wpup_sms_provider', 'ippanel' );
		?>
		<select name="wpup_sms_provider" id="wpup_sms_provider">
			<option value="ippanel"        <?php selected( $value, 'ippanel' ); ?>>
				<?php esc_html_e( 'ippanel (کلید API مستقیم)', 'wpup-login' ); ?>
			</option>
			<option value="persian_wc_sms" <?php selected( $value, 'persian_wc_sms' ); ?>>
				<?php esc_html_e( 'افزونه پیامک فارسی ووکامرس', 'wpup-login' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'در حالت «افزونه پیامک فارسی ووکامرس» تنظیمات از همان افزونه استفاده می‌شود و نیاز به وارد کردن کلید نیست.', 'wpup-login' ); ?>
		</p>
		<?php
	}

	public function field_ippanel_api_key() {
		printf(
			'<input type="text" name="wpup_ippanel_api_key" value="%s" class="regular-text" autocomplete="off" />',
			esc_attr( get_option( 'wpup_ippanel_api_key', '' ) )
		);
	}

	public function field_ippanel_sender() {
		printf(
			'<input type="text" name="wpup_ippanel_sender" value="%s" class="regular-text" placeholder="+983000505" />
			<p class="description">%s</p>',
			esc_attr( get_option( 'wpup_ippanel_sender', '' ) ),
			esc_html__( 'شماره خط اختصاصی شما در ippanel.', 'wpup-login' )
		);
	}

	public function field_ippanel_pattern() {
		printf(
			'<input type="text" name="wpup_ippanel_pattern_code" value="%s" class="regular-text" />
			<p class="description">%s</p>',
			esc_attr( get_option( 'wpup_ippanel_pattern_code', '' ) ),
			esc_html__( 'اگر می‌خواهید از قالب آماده استفاده کنید، کد الگو را وارد کنید (پیشنهاد می‌شود). در غیر این صورت خالی بگذارید.', 'wpup-login' )
		);
	}

	public function field_ippanel_pattern_var() {
		printf(
			'<input type="text" name="wpup_ippanel_pattern_var" value="%s" class="regular-text" placeholder="verification-code" />
			<p class="description">%s</p>',
			esc_attr( get_option( 'wpup_ippanel_pattern_var', 'verification-code' ) ),
			esc_html__( 'نام متغیر در الگوی شما که مقدار OTP را می‌گیرد. مثلاً: verification-code', 'wpup-login' )
		);
	}

	public function field_expiry() {
		$value = (int) get_option( 'wpup_otp_expiry', 120 );
		printf(
			'<input type="number" name="wpup_otp_expiry" value="%d" class="small-text" min="60" max="600" step="30" />
			<p class="description">%s</p>',
			$value,
			esc_html__( 'پیشنهاد: ۱۲۰ ثانیه.', 'wpup-login' )
		);
	}

	public function field_mobile_prompt() {
		$value = (int) get_option( 'wpup_mobile_prompt_enable', 1 );
		?>
		<label>
			<input type="checkbox" name="wpup_mobile_prompt_enable" value="1" <?php checked( $value, 1 ); ?> />
			<?php esc_html_e( 'نمایش باکس دریافت شماره موبایل به کاربرانی که پس از ورود شماره موبایل در پروفایلشان ندارند.', 'wpup-login' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'اگر کاربر «نمی‌خواهم وارد کنم» را بزند، پیام تا لاگین بعدی یا ۳۰ دقیقه آینده دیگر نمایش داده نمی‌شود.', 'wpup-login' ); ?>
		</p>
		<?php
	}

	// ── Page ──────────────────────────────────────────────────────────────────

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap wpup-admin-wrap">
			<h1><?php esc_html_e( 'آپتی‌وایز لاگین', 'wpup-login' ); ?></h1>
			<div class="wpup-admin-header">
				<p><?php esc_html_e( 'افزونه ورود با موبایل برای وردپرس و ووکامرس.', 'wpup-login' ); ?></p>
			</div>

			<?php settings_errors( self::OPTION_GROUP ); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( __( 'ذخیره تنظیمات', 'wpup-login' ) );
				?>
			</form>

			<hr />
			<div class="wpup-admin-test-box">
				<h2><?php esc_html_e( 'تست اتصال به سرویس پیامک', 'wpup-login' ); ?></h2>
				<p><?php esc_html_e( 'برای اطمینان از صحت تنظیمات، یک پیامک تست به شماره دلخواه ارسال کنید. در صورت خطا، پیام دقیق سرویس‌دهنده نمایش داده می‌شود.', 'wpup-login' ); ?></p>
				<div class="wpup-test-row">
					<input type="tel" id="wpup-test-mobile" placeholder="09xxxxxxxxx" maxlength="11" class="regular-text" />
					<button type="button" class="button button-secondary" id="wpup-test-send">
						<?php esc_html_e( 'ارسال پیامک تست', 'wpup-login' ); ?>
					</button>
				</div>
				<div id="wpup-test-result" style="display:none;"></div>
				<script>
				( function( $ ) {
					var nonce = '<?php echo esc_js( wp_create_nonce( 'wpup_test_sms' ) ); ?>';
					$( '#wpup-test-send' ).on( 'click', function() {
						var $btn = $( this );
						var $res = $( '#wpup-test-result' );
						var mobile = $( '#wpup-test-mobile' ).val().trim();
						$res.hide();
						$btn.prop( 'disabled', true ).text( 'در حال ارسال…' );
						$.post( ajaxurl, {
							action: 'wpup_test_sms', nonce: nonce, mobile: mobile,
						} ).done( function( r ) {
							var cls = r.success ? 'notice-success' : 'notice-error';
							$res.removeClass( 'notice-success notice-error' )
								.addClass( 'notice ' + cls )
								.text( r.data.message ).show();
						} ).fail( function() {
							$res.addClass( 'notice notice-error' ).text( 'خطا در ارتباط با سرور.' ).show();
						} ).always( function() {
							$btn.prop( 'disabled', false ).text( 'ارسال پیامک تست' );
						} );
					} );
				} )( jQuery );
				</script>
			</div>

			<hr />
			<div class="wpup-admin-shortcode-info">
				<h2><?php esc_html_e( 'کدهای کوتاه', 'wpup-login' ); ?></h2>
				<p><?php esc_html_e( 'برای نمایش فرم در هر صفحه:', 'wpup-login' ); ?></p>
				<code>[wpup_login]</code>
				<p><?php esc_html_e( 'با تعیین صفحه بازگشت:', 'wpup-login' ); ?></p>
				<code>[wpup_login redirect="https://example.com/my-account"]</code>
			</div>
		</div>
		<?php
	}
}
