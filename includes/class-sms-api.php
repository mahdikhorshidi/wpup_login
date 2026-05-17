<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles SMS dispatch via configured provider.
 * Supports Kavenegar by default; other providers can hook in via wpup_send_sms filter.
 */
class Wpup_Sms_Api {

	private string $api_key;
	private string $template;

	public function __construct() {
		$this->api_key  = get_option( 'wpup_sms_api_key', '' );
		$this->template = get_option( 'wpup_sms_template', '' );
	}

	/**
	 * Send OTP to the given mobile number.
	 *
	 * @param string $mobile  Phone number (e.g. 09123456789)
	 * @param string $otp     The OTP code to send
	 * @return true|WP_Error
	 */
	public function send( string $mobile, string $otp ) {
		// Allow third-party providers to intercept completely.
		$intercepted = apply_filters( 'wpup_send_sms', null, $mobile, $otp, $this->api_key, $this->template );
		if ( null !== $intercepted ) {
			return $intercepted;
		}

		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'کلید API پیامک تنظیم نشده است.', 'wpup-login' ) );
		}

		return $this->send_kavenegar( $mobile, $otp );
	}

	/**
	 * Kavenegar lookup (pattern-based) send.
	 */
	private function send_kavenegar( string $mobile, string $otp ) {
		if ( empty( $this->template ) ) {
			// Plain text fallback
			$url = sprintf(
				'https://api.kavenegar.com/v1/%s/sms/send.json',
				rawurlencode( $this->api_key )
			);
			$body = [
				'receptor' => $mobile,
				'message'  => sprintf( __( 'کد ورود شما: %s', 'wpup-login' ), $otp ),
			];
		} else {
			$url = sprintf(
				'https://api.kavenegar.com/v1/%s/verify/lookup.json',
				rawurlencode( $this->api_key )
			);
			$body = [
				'receptor' => $mobile,
				'token'    => $otp,
				'template' => $this->template,
			];
		}

		$response = wp_remote_post( $url, [
			'body'    => $body,
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			$body_raw = wp_remote_retrieve_body( $response );
			$decoded  = json_decode( $body_raw, true );
			$message  = $decoded['return']['message'] ?? __( 'خطا در ارسال پیامک.', 'wpup-login' );
			return new WP_Error( 'sms_error', $message );
		}

		return true;
	}
}
