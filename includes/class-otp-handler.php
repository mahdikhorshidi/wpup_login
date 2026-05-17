<?php
defined( 'ABSPATH' ) || exit;

class Wpup_Otp_Handler {

	const TRANSIENT_PREFIX = 'wpup_otp_';
	const IP_PREFIX        = 'wpup_ip_';
	const ATTEMPT_PREFIX   = 'wpup_att_';
	const MAX_ATTEMPTS     = 5;     // wrong OTPs allowed per code
	const IP_HOURLY_LIMIT  = 10;    // OTP requests per IP per hour
	const RESEND_COOLDOWN  = 60;    // seconds between sends for same mobile

	public static function expiry_seconds() {
		$v = (int) get_option( 'wpup_otp_expiry', 120 );
		return ( $v < 60 || $v > 600 ) ? 120 : $v;
	}

	/**
	 * Generate and send a new OTP.
	 *
	 * @param string $mobile
	 * @param string $ip      Visitor IP for rate limiting
	 * @return true|WP_Error
	 */
	public static function generate_and_send( $mobile, $ip = '' ) {
		$mobile = self::sanitize_mobile( $mobile );

		if ( ! self::is_valid_mobile( $mobile ) ) {
			return new WP_Error( 'invalid_mobile', __( 'شماره موبایل وارد شده معتبر نیست.', 'wpup-login' ) );
		}

		// ── Per-IP rate limit ────────────────────────────────────────────────
		if ( $ip ) {
			$ip_key   = self::IP_PREFIX . md5( $ip );
			$ip_count = (int) get_transient( $ip_key );
			if ( $ip_count >= self::IP_HOURLY_LIMIT ) {
				return new WP_Error(
					'ip_rate_limit',
					__( 'تعداد درخواست‌ها از این آی‌پی بیش از حد مجاز است. لطفاً بعداً تلاش کنید.', 'wpup-login' )
				);
			}
		}

		// ── Per-mobile cooldown ──────────────────────────────────────────────
		$sent_at = get_transient( self::TRANSIENT_PREFIX . 'sent_at_' . $mobile );
		if ( false !== $sent_at && ( time() - (int) $sent_at ) < self::RESEND_COOLDOWN ) {
			return new WP_Error(
				'rate_limit',
				sprintf( __( 'لطفاً %d ثانیه صبر کنید و دوباره تلاش کنید.', 'wpup-login' ), self::RESEND_COOLDOWN - ( time() - (int) $sent_at ) )
			);
		}

		$otp = (string) wp_rand( 100000, 999999 );

		set_transient( self::TRANSIENT_PREFIX . $mobile, wp_hash( $otp ), self::expiry_seconds() );
		set_transient( self::TRANSIENT_PREFIX . 'sent_at_' . $mobile, time(), self::RESEND_COOLDOWN );
		delete_transient( self::ATTEMPT_PREFIX . $mobile ); // reset attempts on new send

		$sms    = new Wpup_Sms_Api();
		$result = $sms->send( $mobile, $otp );

		if ( is_wp_error( $result ) ) {
			delete_transient( self::TRANSIENT_PREFIX . $mobile );
			delete_transient( self::TRANSIENT_PREFIX . 'sent_at_' . $mobile );
			return $result;
		}

		// Increment IP counter (only after successful send)
		if ( $ip ) {
			$ip_key = self::IP_PREFIX . md5( $ip );
			set_transient( $ip_key, (int) get_transient( $ip_key ) + 1, HOUR_IN_SECONDS );
		}

		return true;
	}

	/**
	 * Verify an OTP with brute-force protection.
	 */
	public static function verify( $mobile, $otp ) {
		$mobile = self::sanitize_mobile( $mobile );
		$stored = get_transient( self::TRANSIENT_PREFIX . $mobile );

		if ( false === $stored ) {
			return new WP_Error( 'otp_expired', __( 'کد تأیید منقضی شده است. لطفاً دوباره درخواست کنید.', 'wpup-login' ) );
		}

		// Lock if too many wrong attempts.
		$att_key = self::ATTEMPT_PREFIX . $mobile;
		$attempts = (int) get_transient( $att_key );
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			delete_transient( self::TRANSIENT_PREFIX . $mobile );
			delete_transient( $att_key );
			return new WP_Error( 'otp_locked', __( 'تعداد تلاش‌های نادرست بیش از حد. لطفاً کد جدید درخواست کنید.', 'wpup-login' ) );
		}

		if ( ! is_string( $otp ) || ! hash_equals( $stored, wp_hash( $otp ) ) ) {
			set_transient( $att_key, $attempts + 1, self::expiry_seconds() );
			return new WP_Error( 'otp_invalid', __( 'کد تأیید وارد شده صحیح نیست.', 'wpup-login' ) );
		}

		delete_transient( self::TRANSIENT_PREFIX . $mobile );
		delete_transient( self::TRANSIENT_PREFIX . 'sent_at_' . $mobile );
		delete_transient( $att_key );

		return true;
	}

	public static function user_exists_by_mobile( $mobile ) {
		$mobile = self::sanitize_mobile( $mobile );
		$users = get_users( array(
			'meta_key'   => 'mobile',
			'meta_value' => $mobile,
			'number'     => 1,
			'fields'     => array( 'ID', 'user_login', 'user_email' ),
		) );
		if ( empty( $users ) ) { return null; }
		return get_user_by( 'id', $users[0]->ID );
	}

	public static function sanitize_mobile( $mobile ) {
		$mobile = preg_replace( '/\D/', '', (string) $mobile );
		if ( strlen( $mobile ) === 12 && strpos( $mobile, '98' ) === 0 ) {
			$mobile = '0' . substr( $mobile, 2 );
		}
		return $mobile;
	}

	public static function is_valid_mobile( $mobile ) {
		return (bool) preg_match( '/^09[0-9]{9}$/', $mobile );
	}

	/**
	 * Get real client IP, respecting common proxies.
	 */
	public static function get_client_ip() {
		$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
		foreach ( $candidates as $k ) {
			if ( empty( $_SERVER[ $k ] ) ) { continue; }
			$ip = trim( explode( ',', $_SERVER[ $k ] )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) { return $ip; }
		}
		return '';
	}
}
