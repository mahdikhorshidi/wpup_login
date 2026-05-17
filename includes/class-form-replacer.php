<?php
defined( 'ABSPATH' ) || exit;

/**
 * Replaces all WordPress and WooCommerce login forms on the front-end.
 *
 * Strategy (three layers):
 *  1. PHP hooks – intercept known action points in WC / WP and output our form.
 *  2. ob_start capture – wrap WC's native form rendering to discard it cleanly.
 *  3. JS injection – find any remaining native login forms in widgets / sidebars
 *     (e.g. WoodMart header, Login widget) and swap them client-side.
 */
class Wpup_Form_Replacer {

	private static $rendering = false; // prevent recursion

	public function __construct() {
		add_action( 'wp_enqueue_scripts',    array( $this, 'enqueue_assets' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Defer all our scripts so they never block rendering.
		add_filter( 'script_loader_tag', array( $this, 'defer_scripts' ), 10, 2 );

		// ── WooCommerce My Account ─────────────────────────────────────────────
		// Render our form BEFORE the WC form, then capture + discard WC's output.
		add_action( 'woocommerce_before_customer_login_form', array( $this, 'wc_render_and_start_capture' ), 1 );
		add_action( 'woocommerce_after_customer_login_form',  array( $this, 'wc_end_capture' ), 9999 );

		// ── WooCommerce Checkout ───────────────────────────────────────────────
		add_filter( 'woocommerce_checkout_login_message', '__return_empty_string' );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'maybe_render_checkout_login' ), 5 );

		// ── wp-login.php ───────────────────────────────────────────────────────
		add_action( 'login_form_top', array( $this, 'inject_on_wp_login_page' ) );

		// ── wp_login_form() called in theme/widget ─────────────────────────────
		// We can't replace its output cleanly in PHP; JS will handle it.

		// ── JS: print a hidden form template + injection script in footer ──────
		add_action( 'wp_footer', array( $this, 'print_js_template' ), 5 );

		// ── Shortcode ──────────────────────────────────────────────────────────
		add_shortcode( 'wpup_login', array( $this, 'shortcode' ) );
	}

	// =========================================================================
	// Assets
	// =========================================================================

	public function enqueue_assets() {
		// Skip on admin and feeds for perf.
		if ( is_admin() || is_feed() ) { return; }

		wp_enqueue_style(
			'wpup-login',
			WPUP_LOGIN_URL . 'assets/css/wpup-login.css',
			array(),
			WPUP_LOGIN_VERSION
		);

		wp_enqueue_script(
			'wpup-login',
			WPUP_LOGIN_URL . 'assets/js/wpup-login.js',
			array( 'jquery' ),
			WPUP_LOGIN_VERSION,
			true
		);

		wp_localize_script( 'wpup-login', 'wpupLoginCfg', array(
			'ajaxurl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wpup_nonce' ),
			'redirect' => $this->default_redirect(),
			'i18n'     => array(
				'send_otp'   => __( 'ارسال کد تأیید', 'wpup-login' ),
				'resend_otp' => __( 'ارسال مجدد کد', 'wpup-login' ),
				'verify'     => __( 'تأیید و ورود', 'wpup-login' ),
				'sending'    => __( 'در حال ارسال…', 'wpup-login' ),
				'verifying'  => __( 'در حال تأیید…', 'wpup-login' ),
				'otp_sent'   => __( 'کد تأیید ارسال شد.', 'wpup-login' ),
				'resend_in'  => __( 'ارسال مجدد تا %s ثانیه', 'wpup-login' ),
				'success'    => __( 'ورود موفق! در حال انتقال…', 'wpup-login' ),
				'net_error'  => __( 'خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.', 'wpup-login' ),
				'otp_len'    => __( 'کد تأیید باید ۶ رقم باشد.', 'wpup-login' ),
				'mobile_inv' => __( 'شماره موبایل وارد شده معتبر نیست (مثال: 09123456789)', 'wpup-login' ),
				'req_fname'  => __( 'نام الزامی است.', 'wpup-login' ),
				'req_lname'  => __( 'نام خانوادگی الزامی است.', 'wpup-login' ),
				'req_uname'  => __( 'نام کاربری الزامی است.', 'wpup-login' ),
				'req_pass'   => __( 'رمز عبور باید حداقل ۶ کاراکتر باشد.', 'wpup-login' ),
			),
		) );

		// JS that injects our form into sidebar widgets / theme login popups.
		wp_enqueue_script(
			'wpup-inject',
			WPUP_LOGIN_URL . 'assets/js/wpup-inject.js',
			array( 'wpup-login' ),
			WPUP_LOGIN_VERSION,
			true
		);
	}

	/**
	 * Add `defer` to our scripts so HTML parsing isn't blocked.
	 */
	public function defer_scripts( $tag, $handle ) {
		if ( in_array( $handle, array( 'wpup-login', 'wpup-inject', 'wpup-mobile-prompt' ), true ) ) {
			if ( false === strpos( $tag, 'defer' ) ) {
				$tag = str_replace( ' src=', ' defer src=', $tag );
			}
		}
		return $tag;
	}

	// =========================================================================
	// WooCommerce My Account: render + capture
	// =========================================================================

	public function wc_render_and_start_capture() {
		if ( is_user_logged_in() ) {
			return;
		}
		$this->render_form();
		// Capture WC's native form output so it does NOT appear on the page.
		ob_start();
	}

	public function wc_end_capture() {
		if ( is_user_logged_in() ) {
			return;
		}
		// Silently discard everything WC printed between the two action hooks.
		if ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
	}

	// =========================================================================
	// WooCommerce Checkout
	// =========================================================================

	public function maybe_render_checkout_login() {
		if ( is_user_logged_in() ) {
			return;
		}
		if ( 'yes' !== get_option( 'woocommerce_enable_checkout_login_reminder' ) ) {
			return;
		}
		echo '<div class="wpup-checkout-login-notice">';
		echo '<p>' . esc_html__( 'برای ورود یا ثبت‌نام از فرم زیر استفاده کنید:', 'wpup-login' ) . '</p>';
		$this->render_form();
		echo '</div>';
	}

	// =========================================================================
	// wp-login.php
	// =========================================================================

	public function inject_on_wp_login_page() {
		$this->render_form( array( 'context' => 'wp-login' ) );
	}

	// =========================================================================
	// Footer JS template (used by wpup-inject.js for sidebar/widget replacement)
	// =========================================================================

	public function print_js_template() {
		if ( is_user_logged_in() ) {
			return;
		}
		echo '<script type="text/html" id="wpup-login-tpl">';
		$this->render_form( array( 'context' => 'template' ) );
		echo '</script>';
	}

	// =========================================================================
	// Shortcode  [wpup_login redirect="..."]
	// =========================================================================

	public function shortcode( $atts ) {
		if ( is_user_logged_in() ) {
			return '';
		}
		$atts = shortcode_atts( array( 'redirect' => '' ), $atts );
		ob_start();
		$this->render_form( $atts );
		return ob_get_clean();
	}

	// =========================================================================
	// Core render
	// =========================================================================

	public function render_form( $args = array() ) {
		if ( self::$rendering ) {
			return; // guard against any accidental recursion
		}
		self::$rendering = true;

		$redirect = ! empty( $args['redirect'] ) ? esc_url( $args['redirect'] ) : '';
		$context  = isset( $args['context'] ) ? $args['context'] : 'inline';

		include WPUP_LOGIN_PATH . 'templates/login-form.php';

		self::$rendering = false;
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	private function default_redirect() {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			return wc_get_page_permalink( 'myaccount' );
		}
		return home_url();
	}
}
