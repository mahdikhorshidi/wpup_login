<?php
/**
 * Optiwise Login – form template.
 * Variables: $redirect (string), $context (string: 'inline'|'wp-login'|'template')
 */
defined( 'ABSPATH' ) || exit;

$redirect          = ! empty( $redirect ) ? esc_url( $redirect ) : '';
$context           = ! empty( $context ) ? $context : 'inline';
$show_password_tab = ( 'wp-login' !== $context );
$form_ts           = time(); // timestamp for replay protection

// Render an OTP input row of N digit boxes.
$render_otp_boxes = static function ( $base_class, $name ) {
	echo '<div class="wpup-otp-boxes" data-otp-target=".' . esc_attr( $base_class ) . '">';
	for ( $i = 0; $i < 6; $i++ ) {
		echo '<input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="' . ( $i === 0 ? 'one-time-code' : 'off' ) . '" aria-label="' . esc_attr__( 'رقم', 'wpup-login' ) . ' ' . ( $i + 1 ) . '" />';
	}
	echo '<input type="hidden" class="' . esc_attr( $base_class ) . '" />';
	echo '</div>';
};
?>
<div class="wpup-login-wrapper" dir="rtl" data-form-ts="<?php echo esc_attr( $form_ts ); ?>">

	<!-- Tabs -->
	<div class="wpup-tabs" role="tablist">
		<button type="button" class="wpup-tab wpup-tab--active js-wpup-tab"
		        role="tab" aria-selected="true" data-target="wpup-panel-mobile">
			<?php esc_html_e( 'ورود با موبایل', 'wpup-login' ); ?>
		</button>
		<?php if ( $show_password_tab ) : ?>
		<button type="button" class="wpup-tab js-wpup-tab"
		        role="tab" aria-selected="false" data-target="wpup-panel-password">
			<?php esc_html_e( 'ورود با رمز عبور', 'wpup-login' ); ?>
		</button>
		<?php endif; ?>
	</div>

	<!-- Panel 1: Mobile OTP -->
	<div class="wpup-tab-panel js-wpup-panel" data-panel="wpup-panel-mobile" role="tabpanel">

		<div class="wpup-notice js-wpup-notice" style="display:none;" role="alert" aria-live="polite"></div>

		<!-- Step A: enter mobile -->
		<div class="js-wpup-step-mobile">
			<h3 class="wpup-panel-title"><?php esc_html_e( 'ورود / ثبت‌نام با موبایل', 'wpup-login' ); ?></h3>
			<p class="wpup-panel-desc"><?php esc_html_e( 'شماره موبایل را وارد کنید تا کد تأیید برایتان ارسال شود.', 'wpup-login' ); ?></p>

			<div class="wpup-field">
				<label for="wpup-mobile"><?php esc_html_e( 'شماره موبایل', 'wpup-login' ); ?></label>
				<input type="tel" id="wpup-mobile" class="js-wpup-mobile"
				       placeholder="09xxxxxxxxx" maxlength="11" inputmode="numeric" autocomplete="tel" />
			</div>

			<!-- Honeypot: real users never fill this -->
			<div class="wpup-hp" aria-hidden="true">
				<label>شماره خانه را خالی بگذارید<input type="text" class="js-wpup-hp" tabindex="-1" autocomplete="off" /></label>
			</div>

			<button type="button" class="wpup-btn wpup-btn--primary js-wpup-send-otp">
				<span class="wpup-spinner" aria-hidden="true"></span>
				<span class="wpup-btn-label"><?php esc_html_e( 'ارسال کد تأیید', 'wpup-login' ); ?></span>
			</button>
		</div>

		<!-- Step B: existing user – OTP only -->
		<div class="js-wpup-step-otp-login" style="display:none;">
			<h3 class="wpup-panel-title"><?php esc_html_e( 'ورود با کد تأیید', 'wpup-login' ); ?></h3>
			<p class="wpup-panel-desc">
				<?php esc_html_e( 'کد ارسال‌شده به', 'wpup-login' ); ?>
				<strong class="js-wpup-mobile-disp"></strong>
				<?php esc_html_e( 'را وارد کنید.', 'wpup-login' ); ?>
			</p>

			<?php $render_otp_boxes( 'js-wpup-otp', 'otp' ); ?>

			<button type="button" class="wpup-btn wpup-btn--primary js-wpup-verify-login">
				<span class="wpup-spinner" aria-hidden="true"></span>
				<span class="wpup-btn-label"><?php esc_html_e( 'تأیید و ورود', 'wpup-login' ); ?></span>
			</button>

			<div class="wpup-resend">
				<span class="js-wpup-countdown"></span>
				<button type="button" class="wpup-btn-link js-wpup-resend" style="display:none;">
					<?php esc_html_e( 'ارسال مجدد کد', 'wpup-login' ); ?>
				</button>
			</div>

			<button type="button" class="wpup-btn-link wpup-back js-wpup-back-login">
				← <?php esc_html_e( 'تغییر شماره', 'wpup-login' ); ?>
			</button>
		</div>

		<!-- Step C: new user – registration + OTP -->
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
					<button type="button" class="wpup-toggle-pass js-wpup-toggle-pass" aria-label="<?php esc_attr_e( 'نمایش/پنهان', 'wpup-login' ); ?>">👁</button>
				</div>
			</div>

			<div class="wpup-field">
				<label><?php esc_html_e( 'کد تأیید', 'wpup-login' ); ?> <span class="wpup-required">*</span></label>
				<?php $render_otp_boxes( 'js-wpup-otp-reg', 'otp_reg' ); ?>
			</div>

			<button type="button" class="wpup-btn wpup-btn--primary js-wpup-verify-reg">
				<span class="wpup-spinner" aria-hidden="true"></span>
				<span class="wpup-btn-label"><?php esc_html_e( 'ثبت‌نام و ورود', 'wpup-login' ); ?></span>
			</button>

			<div class="wpup-resend">
				<span class="js-wpup-countdown-reg"></span>
				<button type="button" class="wpup-btn-link js-wpup-resend-reg" style="display:none;">
					<?php esc_html_e( 'ارسال مجدد کد', 'wpup-login' ); ?>
				</button>
			</div>

			<button type="button" class="wpup-btn-link wpup-back js-wpup-back-reg">
				← <?php esc_html_e( 'تغییر شماره', 'wpup-login' ); ?>
			</button>
		</div>

		<input type="hidden" class="js-wpup-redirect" value="<?php echo esc_attr( $redirect ); ?>" />
	</div>

	<!-- Panel 2: Standard WP/WC password form -->
	<?php if ( $show_password_tab ) : ?>
	<div class="wpup-tab-panel js-wpup-panel" data-panel="wpup-panel-password" role="tabpanel" style="display:none;">
		<?php
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
