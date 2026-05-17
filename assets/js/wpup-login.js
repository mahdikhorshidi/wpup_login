/* global wpupLogin, jQuery */
( function ( $ ) {
	'use strict';

	const cfg     = wpupLogin;
	const i18n    = cfg.i18n;

	// ── DOM refs ─────────────────────────────────────────────────────────────

	const $wrapper   = $( '#wpup-login-wrapper' );

	// Tabs
	const $tabs      = $wrapper.find( '.wpup-tab' );
	const $panels    = $wrapper.find( '.wpup-tab-panel' );

	// Mobile step
	const $stepMobile   = $( '#wpup-step-mobile' );
	const $mobileInput  = $( '#wpup-mobile' );
	const $btnSendOtp   = $( '#wpup-btn-send-otp' );

	// OTP login step (existing user)
	const $stepOtpLogin = $( '#wpup-step-otp-login' );
	const $otpInput     = $( '#wpup-otp' );
	const $btnVerify    = $( '#wpup-btn-verify-login' );
	const $mobileDisp   = $( '#wpup-mobile-display' );
	const $btnResend    = $( '#wpup-btn-resend' );
	const $countdown    = $( '#wpup-countdown' );
	const $btnBack      = $( '#wpup-btn-back-login' );

	// OTP register step (new user)
	const $stepOtpReg   = $( '#wpup-step-otp-register' );
	const $otpRegInput  = $( '#wpup-otp-reg' );
	const $btnVerifyReg = $( '#wpup-btn-verify-register' );
	const $mobileDispReg= $( '#wpup-mobile-display-reg' );
	const $btnResendReg = $( '#wpup-btn-resend-reg' );
	const $countdownReg = $( '#wpup-countdown-reg' );
	const $btnBackReg   = $( '#wpup-btn-back-register' );

	// Password toggle
	$wrapper.on( 'click', '.wpup-toggle-pass', function () {
		const $input = $( this ).prev( 'input' );
		$input.attr( 'type', $input.attr( 'type' ) === 'password' ? 'text' : 'password' );
	} );

	const $notice   = $( '#wpup-notice' );
	const $redirect = $( '#wpup-redirect' );

	let countdownTimer = null;
	let countdownTimerReg = null;

	// ── Tab switching ─────────────────────────────────────────────────────────

	$tabs.on( 'click', function () {
		const $tab   = $( this );
		const target = $tab.attr( 'aria-controls' );

		$tabs.removeClass( 'wpup-tab--active' ).attr( 'aria-selected', 'false' );
		$tab.addClass( 'wpup-tab--active' ).attr( 'aria-selected', 'true' );

		$panels.hide().attr( 'aria-hidden', 'true' );
		$( '#' + target ).show().attr( 'aria-hidden', 'false' );
	} );

	// ── Notice helper ─────────────────────────────────────────────────────────

	function showNotice( msg, type ) {
		$notice.text( msg )
		       .removeClass( 'wpup-notice--success wpup-notice--error' )
		       .addClass( 'wpup-notice--' + ( type || 'error' ) )
		       .slideDown( 200 );
	}

	function hideNotice() {
		$notice.slideUp( 100 );
	}

	// ── Countdown helper ──────────────────────────────────────────────────────

	function startCountdown( $countdownEl, $resendBtn, seconds, onExpire ) {
		clearCountdown( $countdownEl, $resendBtn );
		let remaining = seconds;

		$resendBtn.hide();
		updateCountdownText();

		countdownTimer = setInterval( function () {
			remaining--;
			if ( remaining <= 0 ) {
				clearCountdown( $countdownEl, $resendBtn );
				$resendBtn.show();
				if ( typeof onExpire === 'function' ) {
					onExpire();
				}
			} else {
				updateCountdownText();
			}
		}, 1000 );

		function updateCountdownText() {
			$countdownEl.text( i18n.resend_in.replace( '%s', remaining ) );
		}
	}

	function clearCountdown( $countdownEl, $resendBtn ) {
		if ( countdownTimer ) {
			clearInterval( countdownTimer );
			countdownTimer = null;
		}
		$countdownEl.text( '' );
	}

	// ── Step 1: Send OTP ──────────────────────────────────────────────────────

	$btnSendOtp.on( 'click', function () {
		sendOtp();
	} );

	$mobileInput.on( 'keypress', function ( e ) {
		if ( e.which === 13 ) {
			sendOtp();
		}
	} );

	function sendOtp() {
		hideNotice();
		const mobile = $mobileInput.val().trim();

		if ( ! /^09[0-9]{9}$/.test( mobile ) ) {
			showNotice( 'شماره موبایل وارد شده معتبر نیست (مثال: 09123456789)', 'error' );
			return;
		}

		$btnSendOtp.prop( 'disabled', true ).text( i18n.sending );

		$.post( cfg.ajaxurl, {
			action : 'wpup_send_otp',
			nonce  : cfg.nonce,
			mobile : mobile,
		} )
		.done( function ( res ) {
			if ( ! res.success ) {
				showNotice( res.data.message, 'error' );
				return;
			}

			showNotice( i18n.otp_sent, 'success' );
			$stepMobile.hide();

			if ( res.data.user_exists ) {
				// Existing user: just verify OTP.
				$mobileDisp.text( mobile );
				$stepOtpLogin.show();
				$otpInput.focus();
				startCountdown( $countdown, $btnResend, 60 );
			} else {
				// New user: register form.
				$mobileDispReg.text( mobile );
				$stepOtpReg.show();
				$otpRegInput.focus();
				startCountdown( $countdownReg, $btnResendReg, 60 );
			}
		} )
		.fail( function () {
			showNotice( 'خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.', 'error' );
		} )
		.always( function () {
			$btnSendOtp.prop( 'disabled', false ).text( i18n.send_otp );
		} );
	}

	// ── Back buttons ──────────────────────────────────────────────────────────

	$btnBack.on( 'click', function () {
		$stepOtpLogin.hide();
		$stepMobile.show();
		hideNotice();
		clearCountdown( $countdown, $btnResend );
		$mobileInput.focus();
	} );

	$btnBackReg.on( 'click', function () {
		$stepOtpReg.hide();
		$stepMobile.show();
		hideNotice();
		clearCountdown( $countdownReg, $btnResendReg );
		$mobileInput.focus();
	} );

	// ── Resend OTP ────────────────────────────────────────────────────────────

	function resendOtp( $countdownEl, $resendBtn ) {
		const mobile = $mobileInput.val().trim();
		$resendBtn.prop( 'disabled', true );

		$.post( cfg.ajaxurl, {
			action : 'wpup_send_otp',
			nonce  : cfg.nonce,
			mobile : mobile,
		} )
		.done( function ( res ) {
			if ( res.success ) {
				showNotice( i18n.otp_sent, 'success' );
				startCountdown( $countdownEl, $resendBtn, 60 );
			} else {
				showNotice( res.data.message, 'error' );
				$resendBtn.prop( 'disabled', false );
			}
		} )
		.fail( function () {
			showNotice( 'خطا در ارسال مجدد کد.', 'error' );
			$resendBtn.prop( 'disabled', false );
		} );
	}

	$btnResend.on( 'click', function () {
		resendOtp( $countdown, $btnResend );
	} );

	$btnResendReg.on( 'click', function () {
		resendOtp( $countdownReg, $btnResendReg );
	} );

	// ── Step 2a: Verify OTP (login existing user) ─────────────────────────────

	$btnVerify.on( 'click', function () {
		verifyAndLogin();
	} );

	$otpInput.on( 'keypress', function ( e ) {
		if ( e.which === 13 ) {
			verifyAndLogin();
		}
	} );

	function verifyAndLogin() {
		hideNotice();
		const otp = $otpInput.val().trim();

		if ( otp.length !== 6 ) {
			showNotice( 'کد تأیید باید ۶ رقم باشد.', 'error' );
			return;
		}

		$btnVerify.prop( 'disabled', true ).text( i18n.verifying );

		$.post( cfg.ajaxurl, {
			action   : 'wpup_verify_login',
			nonce    : cfg.nonce,
			mobile   : $mobileInput.val().trim(),
			otp      : otp,
			redirect : $redirect.val(),
		} )
		.done( handleVerifyResponse )
		.fail( ajaxFail )
		.always( function () {
			$btnVerify.prop( 'disabled', false ).text( i18n.verify );
		} );
	}

	// ── Step 2b: Verify OTP + register ────────────────────────────────────────

	$btnVerifyReg.on( 'click', function () {
		verifyAndRegister();
	} );

	function verifyAndRegister() {
		hideNotice();

		const otp       = $otpRegInput.val().trim();
		const firstName = $( '#wpup-first-name' ).val().trim();
		const lastName  = $( '#wpup-last-name' ).val().trim();
		const username  = $( '#wpup-username' ).val().trim();
		const password  = $( '#wpup-password' ).val();
		const email     = $( '#wpup-email' ).val().trim();

		if ( ! firstName ) { showNotice( 'نام الزامی است.', 'error' ); return; }
		if ( ! lastName )  { showNotice( 'نام خانوادگی الزامی است.', 'error' ); return; }
		if ( ! username )  { showNotice( 'نام کاربری الزامی است.', 'error' ); return; }
		if ( password.length < 6 ) { showNotice( 'رمز عبور باید حداقل ۶ کاراکتر باشد.', 'error' ); return; }
		if ( otp.length !== 6 )    { showNotice( 'کد تأیید باید ۶ رقم باشد.', 'error' ); return; }

		$btnVerifyReg.prop( 'disabled', true ).text( i18n.verifying );

		$.post( cfg.ajaxurl, {
			action     : 'wpup_verify_login',
			nonce      : cfg.nonce,
			mobile     : $mobileInput.val().trim(),
			otp        : otp,
			first_name : firstName,
			last_name  : lastName,
			username   : username,
			password   : password,
			email      : email,
			redirect   : $redirect.val(),
		} )
		.done( handleVerifyResponse )
		.fail( ajaxFail )
		.always( function () {
			$btnVerifyReg.prop( 'disabled', false ).text( i18n.verify );
		} );
	}

	// ── Shared response handler ────────────────────────────────────────────────

	function handleVerifyResponse( res ) {
		if ( ! res.success ) {
			showNotice( res.data.message, 'error' );
			return;
		}
		showNotice( 'ورود موفق! در حال انتقال…', 'success' );
		const dest = res.data.redirect || cfg.redirect || '/';
		setTimeout( function () {
			window.location.href = dest;
		}, 800 );
	}

	function ajaxFail() {
		showNotice( 'خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.', 'error' );
	}

	// ── OTP auto-advance (6 digits typed → submit) ────────────────────────────

	function autoSubmitOtp( $input, submitFn ) {
		$input.on( 'input', function () {
			const val = $( this ).val().replace( /\D/g, '' );
			$( this ).val( val );
			if ( val.length === 6 ) {
				submitFn();
			}
		} );
	}

	autoSubmitOtp( $otpInput,    verifyAndLogin );
	autoSubmitOtp( $otpRegInput, verifyAndRegister );

} )( jQuery );
