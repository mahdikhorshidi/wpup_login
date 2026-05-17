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
	 * Sends via ippanel. Tries the newer edge.ippanel.com endpoint first
	 * (Authorization: AccessKey) and falls back to api2.ippanel.com (apikey).
	 *
	 * If a pattern code is set → pattern send. Otherwise → free-text send.
	 */
	private function send_via_ippanel( $mobile, $otp ) {
		$api_key  = trim( (string) get_option( 'wpup_ippanel_api_key', '' ) );
		$sender   = trim( (string) get_option( 'wpup_ippanel_sender', '' ) );
		$pattern  = trim( (string) get_option( 'wpup_ippanel_pattern_code', '' ) );
		$var_name = trim( (string) get_option( 'wpup_ippanel_pattern_var', 'verification-code' ) );

		if ( '' === $api_key ) {
			return new WP_Error( 'no_api_key', __( 'کلید API سرویس ippanel تنظیم نشده است.', 'wpup-login' ) );
		}

		$recipient_intl  = $this->to_intl_number( $mobile );        // 98912...
		$recipient_local = preg_replace( '/\D/', '', (string) $mobile ); // 0912...
		$message_text    = sprintf( __( 'کد ورود شما: %s', 'wpup-login' ), $otp );

		// Build a list of attempts. Different ippanel API versions expect
		// different endpoints, auth schemes, and field names. We try in order
		// of newest → oldest until one returns OK.
		$attempts = array();

		if ( '' !== $pattern ) {
			// ── (1) Newest "edge" pattern endpoint ───────────────────────────
			$attempts[] = array(
				'label'   => 'edge/pattern',
				'url'     => 'https://edge.ippanel.com/v1/api/send/pattern',
				'auth'    => 'bearer',
				'body'    => array(
					'pattern_code' => $pattern,
					'originator'   => $sender,
					'recipient'    => $recipient_intl,
					'values'       => array( $var_name => (string) $otp ),
				),
			);
			// ── (2) Legacy api2.ippanel.com pattern endpoint ─────────────────
			$attempts[] = array(
				'label'   => 'api2/pattern',
				'url'     => 'https://api2.ippanel.com/api/v1/sms/pattern/normal/send',
				'auth'    => 'apikey',
				'body'    => array(
					'code'      => $pattern,
					'sender'    => $sender,
					'recipient' => $recipient_intl,
					'variable'  => array( $var_name => (string) $otp ),
				),
			);
			// ── (3) Oldest rest.ippanel.com pattern endpoint ─────────────────
			$attempts[] = array(
				'label'   => 'rest/patterns',
				'url'     => 'http://rest.ippanel.com/v1/messages/patterns/send',
				'auth'    => 'apikey',
				'body'    => array(
					'pattern_code' => $pattern,
					'originator'   => $sender,
					'recipient'    => $recipient_local,
					'values'       => array( $var_name => (string) $otp ),
				),
			);
		} else {
			if ( '' === $sender ) {
				return new WP_Error( 'no_sender', __( 'شماره فرستنده ippanel تنظیم نشده است.', 'wpup-login' ) );
			}
			$attempts[] = array(
				'label' => 'edge/send',
				'url'   => 'https://edge.ippanel.com/v1/api/send',
				'auth'  => 'bearer',
				'body'  => array(
					'originator' => $sender,
					'recipients' => array( $recipient_intl ),
					'message'    => $message_text,
				),
			);
			$attempts[] = array(
				'label' => 'api2/single',
				'url'   => 'https://api2.ippanel.com/api/v1/sms/send/webservice/single',
				'auth'  => 'apikey',
				'body'  => array(
					'recipient' => array( $recipient_intl ),
					'sender'    => $sender,
					'message'   => $message_text,
				),
			);
		}

		$last_error = '';

		foreach ( $attempts as $attempt ) {
			$headers = array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' );
			if ( 'bearer' === $attempt['auth'] ) {
				$headers['Authorization'] = 'AccessKey ' . $api_key;
			} else {
				$headers['apikey'] = $api_key;
			}

			$response = wp_remote_post( $attempt['url'], array(
				'headers' => $headers,
				'body'    => wp_json_encode( $attempt['body'] ),
				'timeout' => 15,
			) );

			if ( is_wp_error( $response ) ) {
				$last_error = sprintf( '[%s] %s', $attempt['label'], $response->get_error_message() );
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$raw  = wp_remote_retrieve_body( $response );
			$dec  = json_decode( $raw, true );

			if ( $code >= 200 && $code < 300 ) {
				// Validate body where possible.
				if ( is_array( $dec ) ) {
					$status_ok = true;
					if ( isset( $dec['meta']['status'] ) && 'ok' !== strtolower( (string) $dec['meta']['status'] ) ) {
						$status_ok = false;
					}
					if ( isset( $dec['status'] ) && in_array( strtolower( (string) $dec['status'] ), array( 'error', 'failed' ), true ) ) {
						$status_ok = false;
					}
					if ( $status_ok ) {
						return true;
					}
					$last_error = sprintf(
						'[%s] HTTP %d – %s',
						$attempt['label'],
						$code,
						$this->extract_ippanel_message( $dec, $raw )
					);
					continue;
				}
				return true; // 2xx with non-JSON body — assume success.
			}

			$last_error = sprintf(
				'[%s] HTTP %d – %s',
				$attempt['label'],
				$code,
				$this->extract_ippanel_message( $dec, $raw )
			);
		}

		return new WP_Error(
			'ippanel_error',
			sprintf( __( 'خطا در ارسال پیامک از ippanel. جزئیات: %s', 'wpup-login' ), $last_error )
		);
	}

	private function extract_ippanel_message( $decoded, $raw ) {
		if ( is_array( $decoded ) ) {
			foreach ( array(
				array( 'meta', 'message' ),
				array( 'message' ),
				array( 'error_message' ),
				array( 'errorMessage' ),
				array( 'data', 'message' ),
			) as $path ) {
				$val = $decoded;
				foreach ( $path as $k ) {
					if ( ! is_array( $val ) || ! isset( $val[ $k ] ) ) { $val = null; break; }
					$val = $val[ $k ];
				}
				if ( is_string( $val ) && '' !== $val ) { return $val; }
			}
		}
		// Fall back to first 200 chars of raw body.
		$raw = is_string( $raw ) ? trim( $raw ) : '';
		return '' === $raw ? __( 'پاسخ خالی از سرور', 'wpup-login' ) : mb_substr( $raw, 0, 200 );
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
