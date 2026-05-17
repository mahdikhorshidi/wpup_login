<?php
/**
 * Login / register form template.
 *
 * Variables:
 *   $redirect  string  URL to redirect after login.
 *   $context   string  'inline'|'wp-login'|'template'
 */
defined( 'ABSPATH' ) || exit;

$redirect = ! empty( $redirect ) ? esc_url( $redirect ) : '';
$context  = ! empty( $context ) ? $context : 'inline';

// On the dedicated wp-login.php page the password tab is redundant (it IS the page).
$show_password_tab = ( 'wp-login' !== $context );
?>

<div class="wpup-login-wrapper" dir="rtl">

	<!-- ── Tabs ──────────────────────────────────────────────────────────── -->
	<div class="wpup-tabs" role="tablist">
		<button class="wpup-tab wpup-tab--active js-wpup-tab"
		        role="tab" aria-selected="true"
		        data-target="wpup-panel-mobile"
		        type="button">
			<?php esc_html_e( 'ورود با موبایل', 'wpup-login' ); ?>
		</button>
		<?php if ( $show_password_tab ) : ?>
		<button class="wpup-tab js-wpup-tab"
		        role="tab" aria-selected="false"
		        data-target="wpup-panel-password"
		        type="button">
			<?php esc_html_e( 'ورود با رمز عبور', 'wpup-login' ); ?>
		</button>
		<?php endif; ?>
	</div>

	<!-- ── Panel 1: Mobile OTP ───────────────────────────────────────────── -->
	<div class="wpup-tab-panel wpup-tab-panel--active js-wpup-panel"
	     data-panel="wpup-panel-mobile" role="tabpanel">

		<div class="wpup-notice js-wpup-notice" style="display:none;" aria-live="polite"></div>

		<!-- Step A: enter mobile -->
		<div class="js-wpup-step-mobile">
			<h3 class="wpup-panel-title"><?php esc_html_e( 'ورود / ثبت‌نام با موبایل', 'wpup-login' ); ?></h3>
			<p class="wpup-panel-desc"><?php esc_html_e( 'شماره موبایل خود را وارد کنید تا کد تأیید ارسال شود.', 'wpup-login' ); ?></p>

			<div class="wpup-field">
				<label for="wpup-mobile"><?php esc_html_e( 'شماره موبایل', 'wpup-login' ); ?></label>
				<input type="tel"
				       id="wpup-mobile"
				       class="js-wpup-mobile"
				       placeholder="09xxxxxxxxx"
				       maxlength="11"
				       inputmode="numeric"
				       autocomplete="tel" />
			</div>

			<button type="button" class="wpup-btn wpup-btn--primary js-wpup-send-otp">
				<?php esc_html_e( 'ارسال کد تأیید', 'wpup-login' ); ?>
			</button>
		</div>

		<!-- Step B: existing user – OTP only -->
		<div class="js-wpup-step-otp-login" style="display:none;">
			<h3 class="wpup-panel-title"><?php esc_html_e( 'ورود با کد تأیید', 'wpup-login' ); ?></h3>
			<p class="wpup-panel-desc">
				<?php esc_html_e( 'کد ارسال شده به', 'wpup-login' ); ?>
				<strong class="js-wpup-mobile-disp"></strong>
				<?php esc_html_e( 'را وارد کنید.', 'wpup-login' ); ?>
			</p>

			<div class="wpup-field">
				<label for="wpup-otp"><?php esc_html_e( 'کد تأیید', 'wpup-login' ); ?></label>
				<input type="text"
				       id="wpup-otp"
				       class="js-wpup-otp"
				       placeholder="------"
				       maxlength="6"
				       inputmode="numeric"
				       autocomplete="one-time-code" />
			</div>

			<button type="button" class="wpup-btn wpup-btn--primary js-wpup-verify-login">
				<?php esc_html_e( 'تأیید و ورود', 'wpup-login' ); ?>
			</button>

			<div class="wpup-resend">
				<span class="js-wpup-countdown"></span>
				<button type="button" class="wpup-btn-link js-wpup-resend" style="display:none;">
					<?php esc_html_e( 'ارسال مجدد کد', 'wpup-login' ); ?>
				</button>
			</div>

			<button type="button" class="wpup-btn-link wpup-back js-wpup-back-login">
				<?php esc_html_e( '← تغییر شماره', 'wpup-login' ); ?>
			</button>
		</div>

		<!-- Step C: new user – registration fields + OTP -->
		<div class="js-wpup-step-otp-reg" style="display:none;">
			<h3 class="wpup-panel-title"><?php esc_html_e( 'ثبت‌نام و ورود', 'wpup-login' ); ?></h3>
			<p class="wpup-panel-desc">
				<?php esc_html_e( 'اطلاعات خود را تکمیل کنید. کد تأیید به', 'wpup-login' ); ?>
				<strong class="js-wpup-mobile-disp-reg"></strong>
				<?php esc_html_e( 'ارسال شد.', 'wpup-login' ); ?>
			</p>

			<div class="wpup-field-row">
				<div class="wpup-field">
					<label for="wpup-first-name"><?php esc_html_e( 'نام', 'wpup-login' ); ?> <span class="wpup-required">*</span></label>
					<input type="text" id="wpup-first-name" class="js-wpup-first-name" autocomplete="given-name" />
				</div>
				<div class="wpup-field">
					<label for="wpup-last-name"><?php esc_html_e( 'نام خانوادگی', 'wpup-login' ); ?> <span class="wpup-required">*</span></label>
					<input type="text" id="wpup-last-name" class="js-wpup-last-name" autocomplete="family-name" />
				</div>
			</div>

			<div class="wpup-field-row">
				<div class="wpup-field">
					<label for="wpup-username"><?php esc_html_e( 'نام کاربری', 'wpup-login' ); ?> <span class="wpup-required">*</span></label>
					<input type="text" id="wpup-username" class="js-wpup-username" autocomplete="username" />
				</div>
				<div class="wpup-field">
					<label for="wpup-email"><?php esc_html_e( 'ایمیل (اختیاری)', 'wpup-login' ); ?></label>
					<input type="email" id="wpup-email" class="js-wpup-email" autocomplete="email" />
				</div>
			</div>

			<div class="wpup-field">
				<label for="wpup-password"><?php esc_html_e( 'رمز عبور', 'wpup-login' ); ?> <span class="wpup-required">*</span></label>
				<div class="wpup-password-wrap">
					<input type="password" id="wpup-password" class="js-wpup-password" autocomplete="new-password" minlength="6" />
					<button type="button" class="wpup-toggle-pass js-wpup-toggle-pass" aria-label="<?php esc_attr_e( 'نمایش/پنهان', 'wpup-login' ); ?>">
						<span aria-hidden="true">👁</span>
					</button>
				</div>
			</div>

			<div class="wpup-field">
				<label for="wpup-otp-reg"><?php esc_html_e( 'کد تأیید', 'wpup-login' ); ?> <span class="wpup-required">*</span></label>
				<input type="text"
				       id="wpup-otp-reg"
				       class="js-wpup-otp-reg"
				       placeholder="------"
				       maxlength="6"
				       inputmode="numeric"
				       autocomplete="one-time-code" />
			</div>

			<button type="button" class="wpup-btn wpup-btn--primary js-wpup-verify-reg">
				<?php esc_html_e( 'ثبت‌نام و ورود', 'wpup-login' ); ?>
			</button>

			<div class="wpup-resend">
				<span class="js-wpup-countdown-reg"></span>
				<button type="button" class="wpup-btn-link js-wpup-resend-reg" style="display:none;">
					<?php esc_html_e( 'ارسال مجدد کد', 'wpup-login' ); ?>
				</button>
			</div>

			<button type="button" class="wpup-btn-link wpup-back js-wpup-back-reg">
				<?php esc_html_e( '← تغییر شماره', 'wpup-login' ); ?>
			</button>
		</div>

		<input type="hidden" class="js-wpup-redirect" value="<?php echo esc_attr( $redirect ); ?>" />
	</div>

	<!-- ── Panel 2: Standard WP / WooCommerce password form ─────────────── -->
	<?php if ( $show_password_tab ) : ?>
	<div class="wpup-tab-panel js-wpup-panel"
	     data-panel="wpup-panel-password"
	     role="tabpanel"
	     style="display:none;">
		<?php
		// Render plain WP login form (not WC, which might trigger loops).
		wp_login_form( array(
			'redirect'       => $redirect,
			'label_username' => __( 'نام کاربری یا ایمیل', 'wpup-login' ),
			'label_password' => __( 'رمز عبور', 'wpup-login' ),
			'label_log_in'   => __( 'ورود', 'wpup-login' ),
			'remember'       => true,
		) );
		?>
		<p class="wpup-forgot-password">
			<a href="<?php echo esc_url( wp_lostpassword_url( $redirect ) ); ?>">
				<?php esc_html_e( 'رمز عبور خود را فراموش کرده‌اید؟', 'wpup-login' ); ?>
			</a>
		</p>
	</div>
	<?php endif; ?>

</div>
