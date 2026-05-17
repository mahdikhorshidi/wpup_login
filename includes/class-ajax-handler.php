<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles all AJAX endpoints for the mobile login flow.
 */
class Wpup_Ajax_Handler {

	public function __construct() {
		add_action( 'wp_ajax_nopriv_wpup_send_otp',    [ $this, 'send_otp' ] );
		add_action( 'wp_ajax_nopriv_wpup_verify_login', [ $this, 'verify_login' ] );

		// Logged-in users should not reach login, but keep for safety.
		add_action( 'wp_ajax_wpup_send_otp',    [ $this, 'send_otp' ] );
		add_action( 'wp_ajax_wpup_verify_login', [ $this, 'verify_login' ] );
	}

	// -------------------------------------------------------------------------
	// Step 1: Send OTP
	// -------------------------------------------------------------------------

	public function send_otp(): void {
		check_ajax_referer( 'wpup_nonce', 'nonce' );

		$mobile = sanitize_text_field( $_POST['mobile'] ?? '' );

		$result = Wpup_Otp_Handler::generate_and_send( $mobile );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		$user_exists = null !== Wpup_Otp_Handler::user_exists_by_mobile( $mobile );

		wp_send_json_success( [
			'user_exists' => $user_exists,
			'message'     => __( 'کد تأیید ارسال شد.', 'wpup-login' ),
		] );
	}

	// -------------------------------------------------------------------------
	// Step 2: Verify OTP → login or register
	// -------------------------------------------------------------------------

	public function verify_login(): void {
		check_ajax_referer( 'wpup_nonce', 'nonce' );

		$mobile   = sanitize_text_field( $_POST['mobile'] ?? '' );
		$otp      = sanitize_text_field( $_POST['otp'] ?? '' );
		$redirect = esc_url_raw( $_POST['redirect'] ?? '' );

		$verified = Wpup_Otp_Handler::verify( $mobile, $otp );

		if ( is_wp_error( $verified ) ) {
			wp_send_json_error( [ 'message' => $verified->get_error_message() ] );
		}

		$existing_user = Wpup_Otp_Handler::user_exists_by_mobile( $mobile );

		if ( $existing_user ) {
			$this->login_user( $existing_user, $redirect );
		} else {
			$this->register_and_login( $mobile, $redirect );
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	private function login_user( WP_User $user, string $redirect ): void {
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );

		wp_send_json_success( [
			'redirect' => $this->resolve_redirect( $redirect ),
		] );
	}

	private function register_and_login( string $mobile, string $redirect ): void {
		$first_name = sanitize_text_field( $_POST['first_name'] ?? '' );
		$last_name  = sanitize_text_field( $_POST['last_name'] ?? '' );
		$username   = sanitize_user( $_POST['username'] ?? '' );
		$password   = $_POST['password'] ?? '';
		$email      = sanitize_email( $_POST['email'] ?? '' );

		// Validation
		if ( empty( $first_name ) ) {
			wp_send_json_error( [ 'message' => __( 'نام الزامی است.', 'wpup-login' ) ] );
		}
		if ( empty( $last_name ) ) {
			wp_send_json_error( [ 'message' => __( 'نام خانوادگی الزامی است.', 'wpup-login' ) ] );
		}
		if ( empty( $username ) ) {
			wp_send_json_error( [ 'message' => __( 'نام کاربری الزامی است.', 'wpup-login' ) ] );
		}
		if ( username_exists( $username ) ) {
			wp_send_json_error( [ 'message' => __( 'این نام کاربری قبلاً استفاده شده است.', 'wpup-login' ) ] );
		}
		if ( ! empty( $email ) && email_exists( $email ) ) {
			wp_send_json_error( [ 'message' => __( 'این ایمیل قبلاً ثبت شده است.', 'wpup-login' ) ] );
		}
		if ( empty( $password ) || strlen( $password ) < 6 ) {
			wp_send_json_error( [ 'message' => __( 'رمز عبور باید حداقل ۶ کاراکتر باشد.', 'wpup-login' ) ] );
		}

		// Use mobile as email fallback if none provided.
		if ( empty( $email ) ) {
			$email = $mobile . '@mobile.local';
		}

		$user_id = wp_insert_user( [
			'user_login'   => $username,
			'user_pass'    => $password,
			'user_email'   => $email,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => $first_name . ' ' . $last_name,
			'role'         => 'customer',
		] );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( [ 'message' => $user_id->get_error_message() ] );
		}

		update_user_meta( $user_id, 'mobile', Wpup_Otp_Handler::sanitize_mobile( $mobile ) );

		$user = get_user_by( 'id', $user_id );
		do_action( 'user_register', $user_id );
		do_action( 'woocommerce_created_customer', $user_id, [], true );

		$this->login_user( $user, $redirect );
	}

	private function resolve_redirect( string $redirect ): string {
		if ( ! empty( $redirect ) ) {
			return $redirect;
		}
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			return wc_get_page_permalink( 'myaccount' );
		}
		return admin_url();
	}
}
