<?php
defined( 'ABSPATH' ) || exit;

/**
 * Replaces all WordPress and WooCommerce login forms on the front-end
 * with the Optiwise mobile login form.
 */
class Wpup_Form_Replacer {

	public function __construct() {
		// Enqueue assets on every front-end page.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// ── WooCommerce hooks ──────────────────────────────────────────────────
		// Replace the login/register block on the My Account page.
		add_filter( 'woocommerce_before_customer_login_form', '__return_false' );
		add_action( 'woocommerce_before_customer_login_form', [ $this, 'render_form' ], 5 );

		// Remove WooCommerce's own login / register forms entirely.
		remove_action( 'woocommerce_login_form_start', '__return_false' );
		add_filter( 'woocommerce_login_form',          [ $this, 'override_wc_form' ] );
		add_filter( 'woocommerce_register_form',       [ $this, 'override_wc_form' ] );

		// Checkout login prompt.
		add_filter( 'woocommerce_checkout_login_message', '__return_empty_string' );
		add_action( 'woocommerce_before_checkout_form', [ $this, 'maybe_render_checkout_login' ], 5 );

		// ── WordPress core login page ──────────────────────────────────────────
		add_filter( 'login_form_middle', [ $this, 'inject_into_wp_login' ], 10, 2 );
		add_action( 'login_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// wp_login_form() shortcode / widget replacement.
		add_filter( 'login_form_defaults', [ $this, 'capture_login_form_defaults' ] );
		add_shortcode( 'wpup_login', [ $this, 'shortcode_render' ] );

		// Override [woocommerce_my_account] shortcode login output via template.
		add_filter( 'wc_get_template', [ $this, 'override_wc_login_template' ], 10, 5 );
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	public function enqueue_assets(): void {
		wp_enqueue_style(
			'wpup-login',
			WPUP_LOGIN_URL . 'assets/css/wpup-login.css',
			[],
			WPUP_LOGIN_VERSION
		);

		wp_enqueue_script(
			'wpup-login',
			WPUP_LOGIN_URL . 'assets/js/wpup-login.js',
			[ 'jquery' ],
			WPUP_LOGIN_VERSION,
			true
		);

		wp_localize_script( 'wpup-login', 'wpupLogin', [
			'ajaxurl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'wpup_nonce' ),
			'redirect'     => $this->default_redirect(),
			'i18n'         => [
				'send_otp'       => __( 'ارسال کد تأیید', 'wpup-login' ),
				'resend_otp'     => __( 'ارسال مجدد کد', 'wpup-login' ),
				'verify'         => __( 'تأیید و ورود', 'wpup-login' ),
				'sending'        => __( 'در حال ارسال…', 'wpup-login' ),
				'verifying'      => __( 'در حال تأیید…', 'wpup-login' ),
				'otp_sent'       => __( 'کد تأیید ارسال شد.', 'wpup-login' ),
				'resend_in'      => __( 'ارسال مجدد تا %s ثانیه', 'wpup-login' ),
				'register_title' => __( 'تکمیل اطلاعات ثبت‌نام', 'wpup-login' ),
				'login_title'    => __( 'ورود با کد تأیید', 'wpup-login' ),
			],
		] );
	}

	// ── Form rendering ────────────────────────────────────────────────────────

	public function render_form( array $args = [] ): void {
		$redirect = $args['redirect'] ?? $this->default_redirect();
		include WPUP_LOGIN_PATH . 'templates/login-form.php';
	}

	public function shortcode_render( array $atts ): string {
		ob_start();
		$this->render_form( shortcode_atts( [ 'redirect' => '' ], $atts ) );
		return ob_get_clean();
	}

	// ── WooCommerce template override ────────────────────────────────────────

	/**
	 * Swap WooCommerce's login / myaccount/form-login.php with our template.
	 */
	public function override_wc_login_template( string $template, string $template_name, array $args, string $template_path, string $default_path ): string {
		$targets = [
			'myaccount/form-login.php',
			'global/form-login.php',
		];
		if ( in_array( $template_name, $targets, true ) ) {
			return WPUP_LOGIN_PATH . 'templates/login-form.php';
		}
		return $template;
	}

	public function override_wc_form( string $html ): string {
		// Return empty string; full form rendered via template override above.
		return '';
	}

	// ── Checkout login ────────────────────────────────────────────────────────

	public function maybe_render_checkout_login(): void {
		if ( is_user_logged_in() ) {
			return;
		}
		if ( 'yes' !== get_option( 'woocommerce_enable_checkout_login_reminder' ) ) {
			return;
		}
		echo '<div class="wpup-checkout-login-notice">';
		echo '<p>' . esc_html__( 'برای ورود به حساب کاربری یا ثبت‌نام از فرم زیر استفاده کنید:', 'wpup-login' ) . '</p>';
		$this->render_form();
		echo '</div>';
	}

	// ── WordPress core login page injection ──────────────────────────────────

	/**
	 * Prepend our form to wp-login.php so it appears above the standard form.
	 * We wrap both in a tabs UI via JS.
	 */
	public function inject_into_wp_login( string $content, array $args ): string {
		ob_start();
		echo '<div id="wpup-login-wp-injection">';
		$this->render_form( $args );
		echo '</div>';
		return ob_get_clean() . $content;
	}

	public function capture_login_form_defaults( array $defaults ): array {
		// Trigger asset loading when wp_login_form() is called in a theme.
		add_action( 'wp_footer', [ $this, 'render_form_via_footer' ] );
		return $defaults;
	}

	public function render_form_via_footer(): void {
		// No-op; assets already enqueued. Form rendered inline via template override.
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function default_redirect(): string {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			return wc_get_page_permalink( 'myaccount' );
		}
		return home_url();
	}
}
