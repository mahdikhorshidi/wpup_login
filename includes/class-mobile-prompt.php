<?php
defined( 'ABSPATH' ) || exit;

/**
 * Shows a post-login modal asking users (who have no mobile in their profile)
 * to provide their phone number. Verified via OTP and stored in user meta.
 *
 * Dismissal lasts 30 minutes OR until the next login (whichever comes first).
 */
class Wpup_Mobile_Prompt {

	const DISMISS_META_KEY = 'wpup_mobile_prompt_dismissed_until';
	const DISMISS_SECONDS  = 1800; // 30 minutes

	public function __construct() {
		// Clear dismissal flag on every fresh login.
		add_action( 'wp_login', array( $this, 'clear_dismissal_on_login' ), 10, 2 );

		// Render prompt + enqueue assets on front-end for logged-in users.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer',          array( $this, 'render_prompt' ) );

		// AJAX endpoints.
		add_action( 'wp_ajax_wpup_prompt_send_otp', array( $this, 'ajax_send_otp' ) );
		add_action( 'wp_ajax_wpup_prompt_verify',   array( $this, 'ajax_verify' ) );
		add_action( 'wp_ajax_wpup_prompt_dismiss',  array( $this, 'ajax_dismiss' ) );
	}

	// ── Login hook ────────────────────────────────────────────────────────────

	public function clear_dismissal_on_login( $user_login, $user ) {
		if ( $user instanceof WP_User ) {
			delete_user_meta( $user->ID, self::DISMISS_META_KEY );
		}
	}

	// ── Conditions ────────────────────────────────────────────────────────────

	private function should_show() {
		if ( ! get_option( 'wpup_mobile_prompt_enable', 1 ) ) {
			return false;
		}
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$user_id = get_current_user_id();

		$mobile = get_user_meta( $user_id, 'mobile', true );
		if ( ! empty( $mobile ) ) {
			return false;
		}

		$dismissed_until = (int) get_user_meta( $user_id, self::DISMISS_META_KEY, true );
		if ( $dismissed_until > time() ) {
			return false;
		}

		return true;
	}

	// ── Assets / render ───────────────────────────────────────────────────────

	public function enqueue_assets() {
		if ( ! $this->should_show() ) {
			return;
		}
		wp_enqueue_style( 'wpup-login' ); // reuse front-end styles
		wp_enqueue_script(
			'wpup-mobile-prompt',
			WPUP_LOGIN_URL . 'assets/js/wpup-mobile-prompt.js',
			array( 'jquery' ),
			WPUP_LOGIN_VERSION,
			true
		);
		wp_localize_script( 'wpup-mobile-prompt', 'wpupPrompt', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wpup_prompt_nonce' ),
		) );
	}

	public function render_prompt() {
		if ( ! $this->should_show() ) {
			return;
		}
		?>
		<div class="wpup-prompt-overlay" id="wpup-prompt-overlay" dir="rtl">
			<div class="wpup-prompt-box" role="dialog" aria-labelledby="wpup-prompt-title">
				<button type="button" class="wpup-prompt-close" id="wpup-prompt-close" aria-label="<?php esc_attr_e( 'بستن', 'wpup-login' ); ?>">×</button>

				<h3 id="wpup-prompt-title"><?php esc_html_e( 'تکمیل پروفایل', 'wpup-login' ); ?></h3>
				<p class="wpup-prompt-desc">
					<?php esc_html_e( 'برای دسترسی بهتر به خدمات و دریافت اطلاع‌رسانی‌های سفارش‌ها، شماره موبایل خود را وارد و تأیید کنید.', 'wpup-login' ); ?>
				</p>

				<div class="wpup-notice" id="wpup-prompt-notice" style="display:none;"></div>

				<!-- Step 1: enter mobile -->
				<div id="wpup-prompt-step-mobile">
					<div class="wpup-field">
						<label for="wpup-prompt-mobile"><?php esc_html_e( 'شماره موبایل', 'wpup-login' ); ?></label>
						<input type="tel" id="wpup-prompt-mobile" placeholder="09xxxxxxxxx" maxlength="11" inputmode="numeric" />
					</div>
					<button type="button" class="wpup-btn wpup-btn--primary" id="wpup-prompt-send">
						<?php esc_html_e( 'ارسال کد تأیید', 'wpup-login' ); ?>
					</button>
				</div>

				<!-- Step 2: enter OTP -->
				<div id="wpup-prompt-step-otp" style="display:none;">
					<div class="wpup-field">
						<label for="wpup-prompt-otp"><?php esc_html_e( 'کد تأیید پیامک‌شده', 'wpup-login' ); ?></label>
						<input type="text" id="wpup-prompt-otp" placeholder="------" maxlength="6" inputmode="numeric" />
					</div>
					<button type="button" class="wpup-btn wpup-btn--primary" id="wpup-prompt-verify">
						<?php esc_html_e( 'تأیید و ذخیره', 'wpup-login' ); ?>
					</button>
					<button type="button" class="wpup-btn-link wpup-back" id="wpup-prompt-change-mobile">
						<?php esc_html_e( '← تغییر شماره', 'wpup-login' ); ?>
					</button>
				</div>

				<div class="wpup-prompt-actions">
					<button type="button" class="wpup-btn-link" id="wpup-prompt-dismiss">
						<?php esc_html_e( 'فعلاً نمی‌خواهم وارد کنم', 'wpup-login' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	// ── AJAX: send OTP ────────────────────────────────────────────────────────

	public function ajax_send_otp() {
		check_ajax_referer( 'wpup_prompt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'لطفاً وارد شوید.', 'wpup-login' ) ) );
		}

		$mobile = sanitize_text_field( isset( $_POST['mobile'] ) ? $_POST['mobile'] : '' );

		// Make sure mobile is not already used by another user.
		$existing = Wpup_Otp_Handler::user_exists_by_mobile( $mobile );
		if ( $existing && (int) $existing->ID !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'این شماره قبلاً برای حساب دیگری ثبت شده است.', 'wpup-login' ) ) );
		}

		$result = Wpup_Otp_Handler::generate_and_send( $mobile );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'کد ارسال شد.', 'wpup-login' ) ) );
	}

	// ── AJAX: verify OTP and save mobile ──────────────────────────────────────

	public function ajax_verify() {
		check_ajax_referer( 'wpup_prompt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'لطفاً وارد شوید.', 'wpup-login' ) ) );
		}

		$mobile = sanitize_text_field( isset( $_POST['mobile'] ) ? $_POST['mobile'] : '' );
		$otp    = sanitize_text_field( isset( $_POST['otp'] ) ? $_POST['otp'] : '' );

		$verified = Wpup_Otp_Handler::verify( $mobile, $otp );
		if ( is_wp_error( $verified ) ) {
			wp_send_json_error( array( 'message' => $verified->get_error_message() ) );
		}

		$user_id = get_current_user_id();
		update_user_meta( $user_id, 'mobile', Wpup_Otp_Handler::sanitize_mobile( $mobile ) );
		delete_user_meta( $user_id, self::DISMISS_META_KEY );

		wp_send_json_success( array( 'message' => __( 'شماره موبایل با موفقیت ثبت شد.', 'wpup-login' ) ) );
	}

	// ── AJAX: dismiss prompt ──────────────────────────────────────────────────

	public function ajax_dismiss() {
		check_ajax_referer( 'wpup_prompt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error();
		}

		update_user_meta(
			get_current_user_id(),
			self::DISMISS_META_KEY,
			time() + self::DISMISS_SECONDS
		);
		wp_send_json_success();
	}
}
