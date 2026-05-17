/* global wpupLoginCfg, jQuery */
( function ( $ ) {
	'use strict';

	var cfg  = wpupLoginCfg;
	var i18n = cfg.i18n;

	// =========================================================================
	// WpupForm – one instance per .wpup-login-wrapper on the page
	// =========================================================================

	function WpupForm( wrapper ) {
		var $w = $( wrapper );

		// ── Internal DOM refs (all scoped to this wrapper) ─────────────────
		var fn = {
			tab:          '.js-wpup-tab',
			panel:        '.js-wpup-panel',
			notice:       '.js-wpup-notice',
			mobileInput:  '.js-wpup-mobile',
			btnSendOtp:   '.js-wpup-send-otp',

			// Login (existing user)
			stepOtpLogin: '.js-wpup-step-otp-login',
			otpInput:     '.js-wpup-otp',
			btnVerify:    '.js-wpup-verify-login',
			mobileDisp:   '.js-wpup-mobile-disp',
			btnResend:    '.js-wpup-resend',
			countdown:    '.js-wpup-countdown',
			btnBack:      '.js-wpup-back-login',

			// Register (new user)
			stepOtpReg:   '.js-wpup-step-otp-reg',
			otpRegInput:  '.js-wpup-otp-reg',
			btnVerifyReg: '.js-wpup-verify-reg',
			mobileDispReg:'.js-wpup-mobile-disp-reg',
			btnResendReg: '.js-wpup-resend-reg',
			countdownReg: '.js-wpup-countdown-reg',
			btnBackReg:   '.js-wpup-back-reg',

			stepMobile:   '.js-wpup-step-mobile',
			redirect:     '.js-wpup-redirect',
		};

		function find( selector ) { return $w.find( selector ); }

		var countdownTimer    = null;
		var countdownTimerReg = null;

		// ── Tabs ──────────────────────────────────────────────────────────
		$w.on( 'click', fn.tab, function () {
			var $tab    = $( this );
			var panelId = $tab.data( 'target' );

			find( fn.tab ).removeClass( 'wpup-tab--active' ).attr( 'aria-selected', 'false' );
			$tab.addClass( 'wpup-tab--active' ).attr( 'aria-selected', 'true' );

			find( fn.panel ).hide().attr( 'aria-hidden', 'true' );
			$w.find( '[data-panel="' + panelId + '"]' ).show().attr( 'aria-hidden', 'false' );
		} );

		// ── Notice ────────────────────────────────────────────────────────
		function notice( msg, type ) {
			find( fn.notice )
				.text( msg )
				.removeClass( 'wpup-notice--success wpup-notice--error' )
				.addClass( 'wpup-notice--' + ( type || 'error' ) )
				.show();
		}
		function clearNotice() { find( fn.notice ).hide(); }

		// ── Countdown ─────────────────────────────────────────────────────
		function startCountdown( $cd, $btn, seconds, timerRef ) {
			if ( timerRef && timerRef.id ) { clearInterval( timerRef.id ); }
			var rem = seconds;
			$btn.hide();
			tick();
			var id = setInterval( function () {
				rem--;
				if ( rem <= 0 ) { clearInterval( id ); $cd.text( '' ); $btn.show(); }
				else { tick(); }
			}, 1000 );
			if ( timerRef ) { timerRef.id = id; }
			function tick() { $cd.text( i18n.resend_in.replace( '%s', rem ) ); }
		}

		// ── Step 1: Send OTP ──────────────────────────────────────────────
		function sendOtp() {
			clearNotice();
			var mobile = find( fn.mobileInput ).val().trim();
			if ( ! /^09[0-9]{9}$/.test( mobile ) ) {
				notice( i18n.mobile_inv );
				return;
			}
			var $btn = find( fn.btnSendOtp );
			$btn.prop( 'disabled', true ).text( i18n.sending );

			$.post( cfg.ajaxurl, {
				action: 'wpup_send_otp',
				nonce:  cfg.nonce,
				mobile: mobile,
			} )
			.done( function ( res ) {
				if ( ! res.success ) { notice( res.data.message ); return; }
				notice( i18n.otp_sent, 'success' );
				find( fn.stepMobile ).hide();
				if ( res.data.user_exists ) {
					find( fn.mobileDisp ).text( mobile );
					find( fn.stepOtpLogin ).show();
					find( fn.otpInput ).focus();
					var tr = {}; countdownTimer = tr;
					startCountdown( find( fn.countdown ), find( fn.btnResend ), 60, tr );
				} else {
					find( fn.mobileDispReg ).text( mobile );
					find( fn.stepOtpReg ).show();
					find( fn.otpRegInput ).focus();
					var tr2 = {}; countdownTimerReg = tr2;
					startCountdown( find( fn.countdownReg ), find( fn.btnResendReg ), 60, tr2 );
				}
			} )
			.fail( function () { notice( i18n.net_error ); } )
			.always( function () { $btn.prop( 'disabled', false ).text( i18n.send_otp ); } );
		}

		$w.on( 'click', fn.btnSendOtp, sendOtp );
		$w.on( 'keypress', fn.mobileInput, function ( e ) { if ( e.which === 13 ) { sendOtp(); } } );

		// ── Back ──────────────────────────────────────────────────────────
		$w.on( 'click', fn.btnBack, function () {
			find( fn.stepOtpLogin ).hide();
			find( fn.stepMobile ).show();
			clearNotice();
			find( fn.mobileInput ).focus();
		} );
		$w.on( 'click', fn.btnBackReg, function () {
			find( fn.stepOtpReg ).hide();
			find( fn.stepMobile ).show();
			clearNotice();
			find( fn.mobileInput ).focus();
		} );

		// ── Resend ────────────────────────────────────────────────────────
		function resendOtp( $cd, $btn, timer ) {
			var mobile = find( fn.mobileInput ).val().trim();
			$btn.prop( 'disabled', true );
			$.post( cfg.ajaxurl, {
				action: 'wpup_send_otp',
				nonce:  cfg.nonce,
				mobile: mobile,
			} )
			.done( function ( res ) {
				if ( res.success ) {
					notice( i18n.otp_sent, 'success' );
					startCountdown( $cd, $btn, 60, timer );
				} else {
					notice( res.data.message );
					$btn.prop( 'disabled', false );
				}
			} )
			.fail( function () { notice( i18n.net_error ); $btn.prop( 'disabled', false ); } );
		}
		$w.on( 'click', fn.btnResend, function () {
			resendOtp( find( fn.countdown ), find( fn.btnResend ), countdownTimer );
		} );
		$w.on( 'click', fn.btnResendReg, function () {
			resendOtp( find( fn.countdownReg ), find( fn.btnResendReg ), countdownTimerReg );
		} );

		// ── Verify (existing user) ────────────────────────────────────────
		function verifyLogin() {
			clearNotice();
			var otp = find( fn.otpInput ).val().trim();
			if ( otp.length !== 6 ) { notice( i18n.otp_len ); return; }
			var $btn = find( fn.btnVerify );
			$btn.prop( 'disabled', true ).text( i18n.verifying );
			$.post( cfg.ajaxurl, {
				action:    'wpup_verify_login',
				nonce:     cfg.nonce,
				mobile:    find( fn.mobileInput ).val().trim(),
				otp:       otp,
				redirect:  find( fn.redirect ).val() || cfg.redirect,
			} )
			.done( handleResponse )
			.fail( function () { notice( i18n.net_error ); } )
			.always( function () { $btn.prop( 'disabled', false ).text( i18n.verify ); } );
		}
		$w.on( 'click', fn.btnVerify, verifyLogin );
		$w.on( 'keypress', fn.otpInput, function ( e ) { if ( e.which === 13 ) { verifyLogin(); } } );

		// ── Verify (new user) ─────────────────────────────────────────────
		function verifyRegister() {
			clearNotice();
			var otp       = find( fn.otpRegInput ).val().trim();
			var firstName = find( '.js-wpup-first-name' ).val().trim();
			var lastName  = find( '.js-wpup-last-name' ).val().trim();
			var username  = find( '.js-wpup-username' ).val().trim();
			var password  = find( '.js-wpup-password' ).val();
			var email     = find( '.js-wpup-email' ).val().trim();

			if ( ! firstName ) { notice( i18n.req_fname ); return; }
			if ( ! lastName )  { notice( i18n.req_lname ); return; }
			if ( ! username )  { notice( i18n.req_uname ); return; }
			if ( password.length < 6 ) { notice( i18n.req_pass ); return; }
			if ( otp.length !== 6 )    { notice( i18n.otp_len );  return; }

			var $btn = find( fn.btnVerifyReg );
			$btn.prop( 'disabled', true ).text( i18n.verifying );
			$.post( cfg.ajaxurl, {
				action:      'wpup_verify_login',
				nonce:       cfg.nonce,
				mobile:      find( fn.mobileInput ).val().trim(),
				otp:         otp,
				first_name:  firstName,
				last_name:   lastName,
				username:    username,
				password:    password,
				email:       email,
				redirect:    find( fn.redirect ).val() || cfg.redirect,
			} )
			.done( handleResponse )
			.fail( function () { notice( i18n.net_error ); } )
			.always( function () { $btn.prop( 'disabled', false ).text( i18n.verify ); } );
		}
		$w.on( 'click', fn.btnVerifyReg, verifyRegister );

		// ── Shared response handler ───────────────────────────────────────
		function handleResponse( res ) {
			if ( ! res.success ) { notice( res.data.message ); return; }
			notice( i18n.success, 'success' );
			setTimeout( function () {
				window.location.href = res.data.redirect || cfg.redirect || '/';
			}, 700 );
		}

		// ── OTP auto-advance ──────────────────────────────────────────────
		$w.on( 'input', fn.otpInput, function () {
			$( this ).val( $( this ).val().replace( /\D/g, '' ) );
			if ( $( this ).val().length === 6 ) { verifyLogin(); }
		} );
		$w.on( 'input', fn.otpRegInput, function () {
			$( this ).val( $( this ).val().replace( /\D/g, '' ) );
			if ( $( this ).val().length === 6 ) { verifyRegister(); }
		} );

		// ── Password visibility toggle ────────────────────────────────────
		$w.on( 'click', '.js-wpup-toggle-pass', function () {
			var $i = $( this ).prev( 'input' );
			$i.attr( 'type', $i.attr( 'type' ) === 'password' ? 'text' : 'password' );
		} );
	}

	// =========================================================================
	// Bootstrap all existing forms + expose factory for dynamic injection
	// =========================================================================

	function initAll() {
		$( '.wpup-login-wrapper' ).each( function () {
			if ( ! $( this ).data( 'wpup-init' ) ) {
				$( this ).data( 'wpup-init', true );
				new WpupForm( this );
			}
		} );
	}

	$( initAll );

	// Public: called by wpup-inject.js after injecting a cloned form.
	window.wpupInitForm = function ( el ) { new WpupForm( el ); };

} )( jQuery );
