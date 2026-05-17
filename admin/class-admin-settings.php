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
