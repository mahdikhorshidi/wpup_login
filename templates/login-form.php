<?php
/**
 * Front-end login/register form template.
 *
 * Variables available from the caller:
 *   $redirect  – URL to redirect to after successful login.
 */
defined( 'ABSPATH' ) || exit;

$redirect = ! empty( $redirect ) ? esc_url( $redirect ) : '';

// Determine if we're on the WP login page to hide/show the standard tab.
$show_wp_tab = apply_filters( 'wpup_show_wp_login_tab', true );
?>

<div class="wpup-login-wrapper" id="wpup-login-wrapper" dir="rtl">

	<!-- ── Tabs ──────────────────────────────────────────────────── -->
	<div class="wpup-tabs" role="tablist" aria-label="<?php esc_attr_e( 'انتخاب روش ورود', 'wpup-login' ); ?>">
		<button class="wpup-tab wpup-tab--active"
		        role="tab"
		        aria-selected="true"
		        aria-controls="wpup-panel-mobile"
		        id="wpup-tab-mobile"
		        type="button">
			<?php esc_html_e( 'ورود با موبایل', 'wpup-login' ); ?>
		</button>
		<?php if ( $show_wp_tab ) : ?>
		<button class="wpup-tab"
		        role="tab"
		        aria-selected="false"
		        aria-controls="wpup-panel-password"
		        id="wpup-tab-password"
		        type="button">
			<?php esc_html_e( 'ورود با رمز عبور', 'wpup-login' ); ?>
		</button>
		<?php endif; ?>
	</div>

	<!-- ── Panel 1: Mobile OTP ───────────────────────────────────── -->
	<div class="wpup-tab-panel wpup-tab-panel--active"
	     id="wpup-panel-mobile"
	     role="tabpanel"
	     aria-labelledby="wpup-tab-mobile">

		<div class="wpup-notice" id="wpup-notice" aria-live="polite" style="display:none;"></div>

		<!-- Step 1: Mobile entry -->
		<div id="wpup-step-mobile">
			<h3 class="wpup-panel-title"><?php esc_html_e( 'ورود / ثبت‌نام با موبایل', 'wpup-login' ); ?></h3>
			<p class="wpup-panel-desc"><?php esc_html_e( 'شماره موبایل خود را وارد کنید تا کد تأیید ارسال شود.', 'wpup-login' ); ?></p>

			<div class="wpup-field">
				<label for="wpup-mobile"><?php esc_html_e( 'شماره موبایل', 'wpup-login' ); ?></label>
				<input type="tel"
				       id="wpup-mobile"
				       name="wpup_mobile"
				       placeholder="09xxxxxxxxx"
				       maxlength="11"
				       inputmode="numeric"
				       autocomplete="tel"
				       required />
			</div>

			<button type="button" class="wpup-btn wpup-btn--primary" id="wpup-btn-send-otp">
				<?php esc_html_e( 'ارسال کد تأیید', 'wpup-login' ); ?>
			</button>
		</div>

		<!-- Step 2a: Existing user – only OTP -->
		<div id="wpup-step-otp-login" style="display:none;">
			<h3 class="wpup-panel-title" id="wpup-step-title"><?php esc_html_e( 'ورود با کد تأیید', 'wpup-login' ); ?></h3>
			<p class="wpup-panel-desc">
				<?php esc_html_e( 'کد ارسال شده به', 'wpup-login' ); ?>
				<strong id="wpup-mobile-display"></strong>
				<?php esc_html_e( 'را وارد کنید.', 'wpup-login' ); ?>
			</p>

			<div class="wpup-field">
				<label for="wpup-otp"><?php esc_html_e( 'کد تأیید', 'wpup-login' ); ?></label>
				<input type="text"
				       id="wpup-otp"
				       name="wpup_otp"
				       placeholder="------"
				       maxlength="6"
				       inputmode="numeric"
				       autocomplete="one-time-code" />
			</div>

			<button type="button" class="wpup-btn wpup-btn--primary" id="wpup-btn-verify-login">
				<?php esc_html_e( 'تأیید و ورود', 'wpup-login' ); ?>
			</button>

			<div class="wpup-resend">
				<span id="wpup-countdown"></span>
				<button type="button" class="wpup-btn-link" id="wpup-btn-resend" style="display:none;">
					<?php esc_html_e( 'ارسال مجدد کد', 'wpup-login' ); ?>
				</button>
			</div>

			<button type="button" class="wpup-btn-link wpup-back" id="wpup-btn-back-login">
				<?php esc_html_e( '← تغییر شماره', 'wpup-login' ); ?>
			</button>
		</div>

		<!-- Step 2b: New user – OTP + registration fields -->
		<div id="wpup-step-otp-register" style="display:none;">
			<h3 class="wpup-panel-title"><?php esc_html_e( 'ثبت‌نام و ورود', 'wpup-login' ); ?></h3>
			<p class="wpup-panel-desc">
				<?php esc_html_e( 'اطلاعات خود را تکمیل کنید. کد تأیید به', 'wpup-login' ); ?>
				<strong id="wpup-mobile-display-reg"></strong>
				<?php esc_html_e( 'ارسال شد.', 'wpup-login' ); ?>
			</p>

			<div class="wpup-field-row">
				<div class="wpup-field">
					<label for="wpup-first-name"><?php esc_html_e( 'نام', 'wpup-login' ); ?> <span class="wpup-required">*</span></label>
					<input type="text" id="wpup-first-name" name="wpup_first_name" autocomplete="given-name" required />
				</div>
				<div class="wpup-field">
					<label for="wpup-last-name"><?php esc_html_e( 'نام خانوادگی', 'wpup-login' ); ?> <span class="wpup-required">*</span></label>
					<input type="text" id="wpup-last-name" name="wpup_last_name" autocomplete="family-name" required />
				</div>
			</div>

			<div class="wpup-field-row">
				<div class="wpup-field">
					<label for="wpup-username"><?php esc_html_e( 'نام کاربری', 'wpup-login' ); ?> <span class="wpup-required">*</span></label>
					<input type="text" id="wpup-username" name="wpup_username" autocomplete="username" required />
				</div>
				<div class="wpup-field">
					<label for="wpup-email"><?php esc_html_e( 'ایمیل (اختیاری)', 'wpup-login' ); ?></label>
					<input type="email" id="wpup-email" name="wpup_email" autocomplete="email" />
				</div>
			</div>

			<div class="wpup-field">
				<label for="wpup-password"><?php esc_html_e( 'رمز عبور', 'wpup-login' ); ?> <span class="wpup-required">*</span></label>
				<div class="wpup-password-wrap">
					<input type="password" id="wpup-password" name="wpup_password" autocomplete="new-password" minlength="6" required />
					<button type="button" class="wpup-toggle-pass" aria-label="<?php esc_attr_e( 'نمایش/پنهان رمز', 'wpup-login' ); ?>">
						<span class="wpup-eye-icon">👁</span>
					</button>
				</div>
			</div>

			<div class="wpup-field">
				<label for="wpup-otp-reg"><?php esc_html_e( 'کد تأیید', 'wpup-login' ); ?> <span class="wpup-required">*</span></label>
				<input type="text"
				       id="wpup-otp-reg"
				       name="wpup_otp_reg"
				       placeholder="------"
				       maxlength="6"
				       inputmode="numeric"
				       autocomplete="one-time-code"
				       required />
			</div>

			<button type="button" class="wpup-btn wpup-btn--primary" id="wpup-btn-verify-register">
				<?php esc_html_e( 'ثبت‌نام و ورود', 'wpup-login' ); ?>
			</button>

			<div class="wpup-resend">
				<span id="wpup-countdown-reg"></span>
				<button type="button" class="wpup-btn-link" id="wpup-btn-resend-reg" style="display:none;">
					<?php esc_html_e( 'ارسال مجدد کد', 'wpup-login' ); ?>
				</button>
			</div>

			<button type="button" class="wpup-btn-link wpup-back" id="wpup-btn-back-register">
				<?php esc_html_e( '← تغییر شماره', 'wpup-login' ); ?>
			</button>
		</div>

		<!-- Hidden redirect input -->
		<input type="hidden" id="wpup-redirect" value="<?php echo esc_attr( $redirect ); ?>" />
	</div>

	<!-- ── Panel 2: Standard WP / WooCommerce password login ─────── -->
	<?php if ( $show_wp_tab ) : ?>
	<div class="wpup-tab-panel"
	     id="wpup-panel-password"
	     role="tabpanel"
	     aria-labelledby="wpup-tab-password"
	     style="display:none;">

		<?php
		if ( function_exists( 'woocommerce_login_form' ) ) {
			// Render WooCommerce login form; our filter is already removed for this panel.
			woocommerce_login_form( [ 'redirect' => $redirect ] );
		} else {
			// Plain WP login form.
			wp_login_form( [
				'redirect'       => $redirect,
				'form_id'        => 'wpup-wp-loginform',
				'label_username' => __( 'نام کاربری یا ایمیل', 'wpup-login' ),
				'label_password' => __( 'رمز عبور', 'wpup-login' ),
				'label_log_in'   => __( 'ورود', 'wpup-login' ),
			] );
		}
		?>

		<p class="wpup-forgot-password">
			<a href="<?php echo esc_url( wp_lostpassword_url( $redirect ) ); ?>">
				<?php esc_html_e( 'رمز عبور خود را فراموش کرده‌اید؟', 'wpup-login' ); ?>
			</a>
		</p>
	</div>
	<?php endif; ?>

</div>
