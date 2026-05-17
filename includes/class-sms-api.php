<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles SMS dispatch via configured provider.
 *
 * Providers:
 *   - persian_wc_sms : Uses "Persian WooCommerce SMS" plugin (no extra config).
 *   - ippanel        : Direct integration with ippanel.com REST API.
 */
class Wpup_Sms_Api {

	private $provider;

	public function __construct() {
		$this->provider = get_option( 'wpup_sms_provider', 'ippanel' );
	}

	/**
	 * Send OTP to the given mobile.
	 *
	 * @param string $mobile
	 * @param string $otp
	 * @return true|WP_Error
	 */
	public function send( $mobile, $otp ) {
		// Allow external override.
		$intercepted = apply_filters( 'wpup_send_sms', null, $mobile, $otp, $this->provider );
		if ( null !== $intercepted ) {
			return $intercepted;
		}

		if ( 'persian_wc_sms' === $this->provider ) {
			return $this->send_via_persian_wc_sms( $mobile, $otp );
		}

		return $this->send_via_ippanel( $mobile, $otp );
	}

	// =========================================================================
	// ippanel  (https://apidoc.ippanel.com/)
	// =========================================================================

	/**
	 * If a pattern code is configured, sends via pattern endpoint;
	 * otherwise falls back to the plain "webservice/single" endpoint.
	 */
	private function send_via_ippanel( $mobile, $otp ) {
		$api_key  = trim( (string) get_option( 'wpup_ippanel_api_key', '' ) );
		$sender   = trim( (string) get_option( 'wpup_ippanel_sender', '' ) );
		$pattern  = trim( (string) get_option( 'wpup_ippanel_pattern_code', '' ) );
		$var_name = trim( (string) get_option( 'wpup_ippanel_pattern_var', 'verification-code' ) );

		if ( '' === $api_key ) {
			return new WP_Error( 'no_api_key', __( 'کلید API سرویس ippanel تنظیم نشده است.', 'wpup-login' ) );
		}

		$recipient = $this->to_intl_number( $mobile );

		if ( '' !== $pattern ) {
			$url  = 'https://api2.ippanel.com/api/v1/sms/pattern/normal/send';
			$body = array(
				'code'      => $pattern,
				'sender'    => $sender,
				'recipient' => $recipient,
				'variable'  => array( $var_name => $otp ),
			);
		} else {
			if ( '' === $sender ) {
				return new WP_Error( 'no_sender', __( 'شماره فرستنده ippanel تنظیم نشده است.', 'wpup-login' ) );
			}
			$url  = 'https://api2.ippanel.com/api/v1/sms/send/webservice/single';
			$body = array(
				'recipient' => array( $recipient ),
				'sender'    => $sender,
				'message'   => sprintf( __( 'کد ورود شما: %s', 'wpup-login' ), $otp ),
			);
		}

		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'apikey'       => $api_key,
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$raw     = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $decoded['meta']['message'] )
				? $decoded['meta']['message']
				: ( isset( $decoded['message'] ) ? $decoded['message'] : __( 'خطا در ارسال پیامک از ippanel.', 'wpup-login' ) );
			return new WP_Error( 'ippanel_error', $msg );
		}

		// ippanel returns meta.status === "ok" on success
		if ( isset( $decoded['meta']['status'] ) && 'ok' !== strtolower( $decoded['meta']['status'] ) ) {
			$msg = isset( $decoded['meta']['message'] ) ? $decoded['meta']['message'] : __( 'پاسخ نامعتبر از ippanel.', 'wpup-login' );
			return new WP_Error( 'ippanel_error', $msg );
		}

		return true;
	}

	// =========================================================================
	// Persian WooCommerce SMS plugin
	// =========================================================================

	/**
	 * Tries several common entry points exposed by Persian WC SMS plugins.
	 * Falls back to action hook 'pwsms_send_sms' so users can wire their own.
	 */
	private function send_via_persian_wc_sms( $mobile, $otp ) {
		$message = sprintf( __( 'کد ورود شما: %s', 'wpup-login' ), $otp );

		// 1) PW_SMS_Notifier (Persian WooCommerce SMS by mihanwp)
		if ( class_exists( 'PW_SMS_Notifier' ) && method_exists( 'PW_SMS_Notifier', 'send' ) ) {
			$result = call_user_func( array( 'PW_SMS_Notifier', 'send' ), $mobile, $message );
			return $this->wrap_pw_result( $result );
		}

		// 2) PWSMS class
		if ( class_exists( 'PWSMS' ) && method_exists( 'PWSMS', 'send_sms' ) ) {
			$result = call_user_func( array( 'PWSMS', 'send_sms' ), $mobile, $message );
			return $this->wrap_pw_result( $result );
		}

		// 3) Common procedural function
		if ( function_exists( 'pw_sms_send' ) ) {
			$result = pw_sms_send( $mobile, $message );
			return $this->wrap_pw_result( $result );
		}
		if ( function_exists( 'wcpsms_send_sms' ) ) {
			$result = wcpsms_send_sms( $mobile, $message );
			return $this->wrap_pw_result( $result );
		}

		// 4) Fire an action so users can integrate any plugin manually:
		//    add_action( 'wpup_send_via_persian_wc_sms', function( $mobile, $message, $otp ) { ... }, 10, 3 );
		if ( has_action( 'wpup_send_via_persian_wc_sms' ) ) {
			do_action( 'wpup_send_via_persian_wc_sms', $mobile, $message, $otp );
			return true;
		}

		return new WP_Error(
			'persian_sms_unavailable',
			__( 'افزونه پیامک فارسی ووکامرس یافت نشد یا اینترفیس سازگار ندارد. لطفاً ippanel را انتخاب کنید یا از قلاب wpup_send_via_persian_wc_sms استفاده کنید.', 'wpup-login' )
		);
	}

	private function wrap_pw_result( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			return new WP_Error( 'pw_sms_failed', __( 'ارسال پیامک ناموفق بود.', 'wpup-login' ) );
		}
		return true;
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Convert 09xxxxxxxxx → 989xxxxxxxxx (international format for ippanel).
	 */
	private function to_intl_number( $mobile ) {
		$mobile = preg_replace( '/\D/', '', (string) $mobile );
		if ( strlen( $mobile ) === 11 && strpos( $mobile, '0' ) === 0 ) {
			return '98' . substr( $mobile, 1 );
		}
		return $mobile;
	}
}
