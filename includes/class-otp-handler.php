<?php
defined( 'ABSPATH' ) || exit;

/**
 * Generates, stores, and verifies OTP codes.
 * Codes are stored as transients keyed by sanitised mobile number.
 */
class Wpup_Otp_Handler {

	const TRANSIENT_PREFIX = 'wpup_otp_';

	public static function expiry_seconds() {
		return (int) get_option( 'wpup_otp_expiry', 120 );
	}

	/**
	 * Generate and send a new OTP for the given mobile.
	 *
	 * @param string $mobile
	 * @return true|WP_Error
	 */
	public static function generate_and_send( $mobile ) {
		$mobile = self::sanitize_mobile( $mobile );

		if ( ! self::is_valid_mobile( $mobile ) ) {
			return new WP_Error( 'invalid_mobile', __( 'شماره موبایل وارد شده معتبر نیست.', 'wpup-login' ) );
		}

		// Rate-limit: block if previous request was < 60s ago.
		$sent_at = get_transient( self::TRANSIENT_PREFIX . 'sent_at_' . $mobile );
		if ( false !== $sent_at && ( time() - (int) $sent_at ) < 60 ) {
			return new WP_Error( 'rate_limit', __( 'لطفاً ۶۰ ثانیه صبر کنید و دوباره تلاش کنید.', 'wpup-login' ) );
		}

		$otp = (string) wp_rand( 100000, 999999 );

		set_transient( self::TRANSIENT_PREFIX . $mobile, wp_hash( $otp ), self::expiry_seconds() );
		set_transient( self::TRANSIENT_PREFIX . 'sent_at_' . $mobile, time(), 60 );

		$sms    = new Wpup_Sms_Api();
		$result = $sms->send( $mobile, $otp );

		if ( is_wp_error( $result ) ) {
			delete_transient( self::TRANSIENT_PREFIX . $mobile );
			delete_transient( self::TRANSIENT_PREFIX . 'sent_at_' . $mobile );
			return $result;
		}

		return true;
	}

	/**
	 * Verify an OTP for a mobile number.
	 *
	 * @param string $mobile
	 * @param string $otp
	 * @return true|WP_Error
	 */
	public static function verify( $mobile, $otp ) {
		$mobile = self::sanitize_mobile( $mobile );
		$stored = get_transient( self::TRANSIENT_PREFIX . $mobile );

		if ( false === $stored ) {
			return new WP_Error( 'otp_expired', __( 'کد تأیید منقضی شده است. لطفاً دوباره درخواست کنید.', 'wpup-login' ) );
		}

		if ( ! hash_equals( $stored, wp_hash( $otp ) ) ) {
			return new WP_Error( 'otp_invalid', __( 'کد تأیید وارد شده صحیح نیست.', 'wpup-login' ) );
		}

		delete_transient( self::TRANSIENT_PREFIX . $mobile );
		delete_transient( self::TRANSIENT_PREFIX . 'sent_at_' . $mobile );

		return true;
	}

	/**
	 * Find user by mobile meta. Returns WP_User|null.
	 */
	public static function user_exists_by_mobile( $mobile ) {
		$mobile = self::sanitize_mobile( $mobile );

		$users = get_users( array(
			'meta_key'   => 'mobile',
			'meta_value' => $mobile,
			'number'     => 1,
		) );

		return ! empty( $users ) ? $users[0] : null;
	}

	public static function sanitize_mobile( $mobile ) {
		$mobile = preg_replace( '/\D/', '', (string) $mobile );
		// Normalise 98... → 09...
		if ( strlen( $mobile ) === 12 && strpos( $mobile, '98' ) === 0 ) {
			$mobile = '0' . substr( $mobile, 2 );
		}
		return $mobile;
	}

	public static function is_valid_mobile( $mobile ) {
		return (bool) preg_match( '/^09[0-9]{9}$/', $mobile );
	}
}
