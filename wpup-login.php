<?php
/**
 * Plugin Name: Optiwise Login
 * Plugin URI:  https://optiwise.ir
 * Description: ورود و عضویت با شماره موبایل از طریق OTP پیامکی برای وردپرس و ووکامرس
 * Version:     1.1.0
 * Author:      Optiwise
 * Author URI:  https://optiwise.ir
 * Text Domain: wpup-login
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'WPUP_LOGIN_VERSION', '1.1.0' );
define( 'WPUP_LOGIN_FILE', __FILE__ );
define( 'WPUP_LOGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPUP_LOGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPUP_LOGIN_PATH . 'includes/class-sms-api.php';
require_once WPUP_LOGIN_PATH . 'includes/class-otp-handler.php';
require_once WPUP_LOGIN_PATH . 'includes/class-form-replacer.php';
require_once WPUP_LOGIN_PATH . 'includes/class-ajax-handler.php';
require_once WPUP_LOGIN_PATH . 'includes/class-mobile-prompt.php';
require_once WPUP_LOGIN_PATH . 'admin/class-admin-settings.php';

final class Wpup_Login {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'wpup-login', false, dirname( plugin_basename( WPUP_LOGIN_FILE ) ) . '/languages' );
	}

	public function init() {
		new Wpup_Admin_Settings();
		new Wpup_Form_Replacer();
		new Wpup_Ajax_Handler();
		new Wpup_Mobile_Prompt();
	}
}

Wpup_Login::instance();
