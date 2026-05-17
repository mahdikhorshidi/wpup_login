<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin settings page under Settings > Optiwise Login.
 */
class Wpup_Admin_Settings {

	const OPTION_GROUP = 'wpup_login_options';
	const PAGE_SLUG    = 'wpup-login';

	public function __construct() {
		add_action( 'admin_menu',    [ $this, 'add_menu' ] );
		add_action( 'admin_init',    [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function add_menu(): void {
		add_options_page(
			__( 'آپتی‌وایز لاگین', 'wpup-login' ),
			__( 'آپتی‌وایز لاگین', 'wpup-login' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'wpup-admin',
			WPUP_LOGIN_URL . 'assets/css/wpup-admin.css',
			[],
			WPUP_LOGIN_VERSION
		);
	}

	public function register_settings(): void {
		register_setting( self::OPTION_GROUP, 'wpup_sms_api_key', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		] );

		register_setting( self::OPTION_GROUP, 'wpup_sms_template', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		] );

		register_setting( self::OPTION_GROUP, 'wpup_otp_expiry', [
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 120,
		] );

		// ── Section: SMS Provider ──────────────────────────────────────────────
		add_settings_section(
			'wpup_sms_section',
			__( 'تنظیمات سرویس پیامک', 'wpup-login' ),
			[ $this, 'section_sms_desc' ],
			self::PAGE_SLUG
		);

		add_settings_field(
			'wpup_sms_api_key',
			__( 'کلید API کاوه‌نگار', 'wpup-login' ),
			[ $this, 'field_api_key' ],
			self::PAGE_SLUG,
			'wpup_sms_section'
		);

		add_settings_field(
			'wpup_sms_template',
			__( 'نام قالب پیامک (Lookup)', 'wpup-login' ),
			[ $this, 'field_template' ],
			self::PAGE_SLUG,
			'wpup_sms_section'
		);

		add_settings_field(
			'wpup_otp_expiry',
			__( 'مدت اعتبار کد OTP (ثانیه)', 'wpup-login' ),
			[ $this, 'field_expiry' ],
			self::PAGE_SLUG,
			'wpup_sms_section'
		);
	}

	// ── Field renderers ───────────────────────────────────────────────────────

	public function section_sms_desc(): void {
		echo '<p>' . esc_html__( 'برای ارسال کد تأیید از سرویس کاوه‌نگار استفاده می‌شود. کلید API و نام قالب پیامک خود را وارد کنید.', 'wpup-login' ) . '</p>';
	}

	public function field_api_key(): void {
		$value = get_option( 'wpup_sms_api_key', '' );
		printf(
			'<input type="text" name="wpup_sms_api_key" id="wpup_sms_api_key" value="%s" class="regular-text" autocomplete="off" />
			<p class="description">%s</p>',
			esc_attr( $value ),
			esc_html__( 'کلید API را از پنل کاوه‌نگار دریافت کنید.', 'wpup-login' )
		);
	}

	public function field_template(): void {
		$value = get_option( 'wpup_sms_template', '' );
		printf(
			'<input type="text" name="wpup_sms_template" id="wpup_sms_template" value="%s" class="regular-text" />
			<p class="description">%s</p>',
			esc_attr( $value ),
			esc_html__( 'اگر از روش Lookup استفاده می‌کنید، نام قالب را وارد کنید؛ در غیر این صورت خالی بگذارید.', 'wpup-login' )
		);
	}

	public function field_expiry(): void {
		$value = (int) get_option( 'wpup_otp_expiry', 120 );
		printf(
			'<input type="number" name="wpup_otp_expiry" id="wpup_otp_expiry" value="%d" class="small-text" min="60" max="600" step="30" />
			<p class="description">%s</p>',
			$value,
			esc_html__( 'پیشنهاد: ۱۲۰ ثانیه. حداقل ۶۰، حداکثر ۶۰۰ ثانیه.', 'wpup-login' )
		);
	}

	// ── Page ──────────────────────────────────────────────────────────────────

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap wpup-admin-wrap">
			<h1><?php esc_html_e( 'آپتی‌وایز لاگین', 'wpup-login' ); ?></h1>
			<div class="wpup-admin-header">
				<p><?php esc_html_e( 'افزونه ورود با موبایل برای وردپرس و ووکامرس', 'wpup-login' ); ?></p>
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
			<div class="wpup-admin-shortcode-info">
				<h2><?php esc_html_e( 'کدهای کوتاه (Shortcode)', 'wpup-login' ); ?></h2>
				<p><?php esc_html_e( 'برای نمایش فرم در هر صفحه‌ای از کد زیر استفاده کنید:', 'wpup-login' ); ?></p>
				<code>[wpup_login]</code>
				<p><?php esc_html_e( 'با تعیین صفحه بازگشت:', 'wpup-login' ); ?></p>
				<code>[wpup_login redirect="https://example.com/my-account"]</code>
			</div>
		</div>
		<?php
	}
}
