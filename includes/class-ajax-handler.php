<?php
defined( 'ABSPATH' ) || exit;

class Wpup_Ajax_Handler {

	public function __construct() {
		add_action( 'wp_ajax_nopriv_wpup_send_otp',     array( $this, 'send_otp' ) );
		add_action( 'wp_ajax_nopriv_wpup_verify_login', array( $this, 'verify_login' ) );
		add_action( 'wp_ajax_wpup_send_otp',            array( $this, 'send_otp' ) );
		add_action( 'wp_ajax_wpup_verify_login',        array( $this, 'verify_login' ) );
	}

	// =========================================================================
	// Step 1: Send OTP
	// =========================================================================
	public function send_otp() {
		check_ajax_referer( 'wpup_nonce', 'nonce' );

		// Honeypot: bots fill all visible fields.
		if ( ! empty( $_POST['hp'] ) ) {
			wp_send_json_success( array( 'user_exists' => false, 'message' => __( 'کد تأیید ارسال شد.', 'wpup-login' ) ) );
		}

		// Anti time-trap: form must exist for ≥ 2s before submission.
		$form_ts = isset( $_POST['form_ts'] ) ? (int) $_POST['form_ts'] : 0;
		if ( $form_ts > 0 && ( time() - $form_ts ) < 2 ) {
			wp_send_json_error( array( 'message' => __( 'درخواست نامعتبر.', 'wpup-login' ) ), 400 );
		}

		$mobile = sanitize_text_field( isset( $_POST['mobile'] ) ? $_POST['mobile'] : '' );
		$ip     = Wpup_Otp_Handler::get_client_ip();

		$result = Wpup_Otp_Handler::generate_and_send( $mobile, $ip );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$user_exists = null !== Wpup_Otp_Handler::user_exists_by_mobile( $mobile );

		wp_send_json_success( array(
			'user_exists' => $user_exists,
			'message'     => __( 'کد تأیید ارسال شد.', 'wpup-login' ),
		) );
	}

	// =========================================================================
	// Step 2: Verify OTP → login or register
	// =========================================================================
	public function verify_login() {
		check_ajax_referer( 'wpup_nonce', 'nonce' );

		$mobile   = sanitize_text_field( isset( $_POST['mobile'] ) ? $_POST['mobile'] : '' );
		$otp      = sanitize_text_field( isset( $_POST['otp'] ) ? $_POST['otp'] : '' );
		$redirect = isset( $_POST['redirect'] ) ? esc_url_raw( $_POST['redirect'] ) : '';

		if ( ! preg_match( '/^\d{6}$/', $otp ) ) {
			wp_send_json_error( array( 'message' => __( 'کد تأیید باید ۶ رقم باشد.', 'wpup-login' ) ) );
		}

		$verified = Wpup_Otp_Handler::verify( $mobile, $otp );
		if ( is_wp_error( $verified ) ) {
			wp_send_json_error( array( 'message' => $verified->get_error_message() ) );
		}

		$existing = Wpup_Otp_Handler::user_exists_by_mobile( $mobile );
		if ( $existing ) {
			$this->login_user( $existing, $redirect );
		} else {
			$this->register_and_login( $mobile, $redirect );
		}
	}

	// =========================================================================
	// Helpers
	// =========================================================================
	private function login_user( WP_User $user, $redirect ) {
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );

		wp_send_json_success( array( 'redirect' => $this->resolve_redirect( $redirect, $user ) ) );
	}

	private function register_and_login( $mobile, $redirect ) {
		$first_name = sanitize_text_field( isset( $_POST['first_name'] ) ? $_POST['first_name'] : '' );
		$last_name  = sanitize_text_field( isset( $_POST['last_name'] )  ? $_POST['last_name']  : '' );
		$username   = sanitize_user( isset( $_POST['username'] ) ? $_POST['username'] : '', true );
		$password   = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';
		$email      = sanitize_email( isset( $_POST['email'] ) ? $_POST['email'] : '' );

		// Length caps to prevent abuse.
		$first_name = mb_substr( $first_name, 0, 50 );
		$last_name  = mb_substr( $last_name,  0, 50 );
		$username   = mb_substr( $username,   0, 30 );

		if ( '' === $first_name ) { wp_send_json_error( array( 'message' => __( 'نام الزامی است.', 'wpup-login' ) ) ); }
		if ( '' === $last_name )  { wp_send_json_error( array( 'message' => __( 'نام خانوادگی الزامی است.', 'wpup-login' ) ) ); }
		if ( '' === $username || strlen( $username ) < 3 ) {
			wp_send_json_error( array( 'message' => __( 'نام کاربری حداقل ۳ کاراکتر باشد.', 'wpup-login' ) ) );
		}
		if ( username_exists( $username ) ) {
			wp_send_json_error( array( 'message' => __( 'این نام کاربری قبلاً استفاده شده است.', 'wpup-login' ) ) );
		}
		if ( ! empty( $email ) && ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'فرمت ایمیل معتبر نیست.', 'wpup-login' ) ) );
		}
		if ( ! empty( $email ) && email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'این ایمیل قبلاً ثبت شده است.', 'wpup-login' ) ) );
		}
		if ( strlen( $password ) < 6 || strlen( $password ) > 128 ) {
			wp_send_json_error( array( 'message' => __( 'رمز عبور باید بین ۶ تا ۱۲۸ کاراکتر باشد.', 'wpup-login' ) ) );
		}

		if ( empty( $email ) ) {
			$email = $mobile . '@mobile.local';
		}

		$user_id = wp_insert_user( array(
			'user_login'   => $username,
			'user_pass'    => $password,
			'user_email'   => $email,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => $first_name . ' ' . $last_name,
			'role'         => apply_filters( 'wpup_default_role', 'customer' ),
		) );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		update_user_meta( $user_id, 'mobile', Wpup_Otp_Handler::sanitize_mobile( $mobile ) );
		update_user_meta( $user_id, 'billing_phone', Wpup_Otp_Handler::sanitize_mobile( $mobile ) );
		update_user_meta( $user_id, 'billing_first_name', $first_name );
		update_user_meta( $user_id, 'billing_last_name',  $last_name );

		$user = get_user_by( 'id', $user_id );
		do_action( 'user_register', $user_id );
		if ( function_exists( 'wc_create_new_customer_username' ) ) {
			do_action( 'woocommerce_created_customer', $user_id, array(), true );
		}

		$this->login_user( $user, $redirect );
	}

	private function resolve_redirect( $redirect, $user = null ) {
		if ( ! empty( $redirect ) && wp_validate_redirect( $redirect, '' ) ) {
			return $redirect;
		}
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			return wc_get_page_permalink( 'myaccount' );
		}
		return $user && user_can( $user, 'edit_posts' ) ? admin_url() : home_url();
	}
}
