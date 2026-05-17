/* global wpupLoginCfg, jQuery */
( function ( $ ) {
	'use strict';

	var cfg  = wpupLoginCfg;
	var i18n = cfg.i18n;

	// =========================================================================
	// OTP digit-box widget: 6 inputs that behave as one
	// =========================================================================
	function initOtpBoxes( $container, onComplete ) {
		var $boxes  = $container.find( 'input[maxlength="1"]' );
		var $hidden = $container.find( 'input[type="hidden"]' );

		function readValue() {
			var v = '';
			$boxes.each( function () { v += this.value; } );
			$hidden.val( v );
			return v;
		}

		$boxes.on( 'input', function () {
			var $b = $( this );
			var v  = $b.val().replace( /\D/g, '' );
			$b.val( v.slice( -1 ) );
			$b.toggleClass( 'wpup-filled', !! v );

			if ( v ) {
				$b.next( 'input' ).trigger( 'focus' );
			}
			var full = readValue();
			if ( full.length === 6 && onComplete ) { onComplete( full ); }
		} );

		$boxes.on( 'keydown', function ( e ) {
			var $b = $( this );
			if ( e.key === 'Backspace' && ! $b.val() ) {
				$b.prev( 'input' ).trigger( 'focus' );
			} else if ( e.key === 'ArrowLeft' ) {
				$b.next( 'input' ).trigger( 'focus' );
			} else if ( e.key === 'ArrowRight' ) {
				$b.prev( 'input' ).trigger( 'focus' );
			}
		} );

		$boxes.on( 'paste', function ( e ) {
			var pasted = ( e.originalEvent.clipboardData || window.clipboardData ).getData( 'text' );
			pasted = pasted.replace( /\D/g, '' ).slice( 0, 6 );
			if ( ! pasted ) { return; }
			e.preventDefault();
			$boxes.each( function ( i ) {
				var d = pasted.charAt( i ) || '';
				this.value = d;
				$( this ).toggleClass( 'wpup-filled', !! d );
			} );
			$boxes.eq( Math.min( pasted.length, 5 ) ).trigger( 'focus' );
			var full = readValue();
			if ( full.length === 6 && onComplete ) { onComplete( full ); }
		} );

		return {
			value:  function () { return readValue(); },
			focus:  function () { $boxes.eq( 0 ).trigger( 'focus' ); },
			clear:  function () { $boxes.val( '' ).removeClass( 'wpup-filled' ); $hidden.val( '' ); },
		};
	}

	// =========================================================================
	// Loading state helper
	// =========================================================================
	function setBusy( $btn, busy ) {
		$btn.attr( 'aria-busy', busy ? 'true' : 'false' ).prop( 'disabled', busy );
	}

	// =========================================================================
	// WpupForm – one instance per .wpup-login-wrapper
	// =========================================================================
	function WpupForm( wrapper ) {
		var $w = $( wrapper );
		function f( s ) { return $w.find( s ); }

		var countdownTimer    = { id: null };
		var countdownTimerReg = { id: null };

		// ── Tabs
		$w.on( 'click', '.js-wpup-tab', function () {
			var $tab = $( this );
			var t    = $tab.data( 'target' );
			f( '.js-wpup-tab' ).removeClass( 'wpup-tab--active' ).attr( 'aria-selected', 'false' );
			$tab.addClass( 'wpup-tab--active' ).attr( 'aria-selected', 'true' );
			f( '.js-wpup-panel' ).hide().attr( 'aria-hidden', 'true' );
			$w.find( '[data-panel="' + t + '"]' ).show().attr( 'aria-hidden', 'false' );
		} );

		// ── Notice
		function notice( msg, type ) {
			f( '.js-wpup-notice' )
				.text( msg )
				.removeClass( 'wpup-notice--success wpup-notice--error' )
				.addClass( 'wpup-notice--' + ( type || 'error' ) )
				.show();
		}
		function clearNotice() { f( '.js-wpup-notice' ).hide(); }

		// ── Countdown
		function startCountdown( $cd, $btn, seconds, timerRef ) {
			if ( timerRef.id ) { clearInterval( timerRef.id ); }
			var rem = seconds;
			$btn.hide();
			tick();
			timerRef.id = setInterval( function () {
				rem--;
				if ( rem <= 0 ) { clearInterval( timerRef.id ); $cd.text( '' ); $btn.show(); }
				else { tick(); }
			}, 1000 );
			function tick() { $cd.text( i18n.resend_in.replace( '%s', rem ) ); }
		}

		// ── OTP widgets
		var otpLogin = initOtpBoxes( f( '.js-wpup-step-otp-login .wpup-otp-boxes' ), function () {
			verifyLogin();
		} );
		var otpReg = initOtpBoxes( f( '.js-wpup-step-otp-reg .wpup-otp-boxes' ), function () {
			verifyRegister();
		} );

		// ── Send OTP
		function sendOtp() {
			clearNotice();
			var mobile = f( '.js-wpup-mobile' ).val().trim();
			var hp     = f( '.js-wpup-hp' ).val();
			if ( hp ) { return; } // bot caught silently
			if ( ! /^09[0-9]{9}$/.test( mobile ) ) { notice( i18n.mobile_inv ); return; }

			var formTs = parseInt( $w.attr( 'data-form-ts' ), 10 ) || 0;
			var elapsed = ( Date.now() / 1000 ) - formTs;
			if ( elapsed < 2 ) { return; } // submitted too fast → likely a bot

			var $btn = f( '.js-wpup-send-otp' );
			setBusy( $btn, true );

			$.post( cfg.ajaxurl, {
				action:  'wpup_send_otp',
				nonce:   cfg.nonce,
				mobile:  mobile,
				hp:      hp,
				form_ts: formTs,
			} )
			.done( function ( res ) {
				if ( ! res.success ) { notice( res.data.message ); return; }
				notice( i18n.otp_sent, 'success' );
				f( '.js-wpup-step-mobile' ).hide();
				if ( res.data.user_exists ) {
					f( '.js-wpup-mobile-disp' ).text( mobile );
					f( '.js-wpup-step-otp-login' ).show();
					setTimeout( function () { otpLogin.focus(); }, 100 );
					startCountdown( f( '.js-wpup-countdown' ), f( '.js-wpup-resend' ), 60, countdownTimer );
				} else {
					f( '.js-wpup-mobile-disp-reg' ).text( mobile );
					f( '.js-wpup-step-otp-reg' ).show();
					setTimeout( function () { f( '.js-wpup-first-name' ).trigger( 'focus' ); }, 100 );
					startCountdown( f( '.js-wpup-countdown-reg' ), f( '.js-wpup-resend-reg' ), 60, countdownTimerReg );
				}
			} )
			.fail( function () { notice( i18n.net_error ); } )
			.always( function () { setBusy( $btn, false ); } );
		}

		$w.on( 'click', '.js-wpup-send-otp', sendOtp );
		$w.on( 'keypress', '.js-wpup-mobile', function ( e ) { if ( e.which === 13 ) { sendOtp(); } } );

		// Numeric-only on mobile input
		$w.on( 'input', '.js-wpup-mobile', function () {
			this.value = this.value.replace( /\D/g, '' );
		} );

		// ── Back
		$w.on( 'click', '.js-wpup-back-login', function () {
			f( '.js-wpup-step-otp-login' ).hide();
			f( '.js-wpup-step-mobile' ).show();
			otpLogin.clear();
			clearNotice();
			f( '.js-wpup-mobile' ).trigger( 'focus' );
		} );
		$w.on( 'click', '.js-wpup-back-reg', function () {
			f( '.js-wpup-step-otp-reg' ).hide();
			f( '.js-wpup-step-mobile' ).show();
			otpReg.clear();
			clearNotice();
			f( '.js-wpup-mobile' ).trigger( 'focus' );
		} );

		// ── Resend
		function resend( $cd, $btn, timer ) {
			$btn.prop( 'disabled', true );
			$.post( cfg.ajaxurl, {
				action: 'wpup_send_otp',
				nonce:  cfg.nonce,
				mobile: f( '.js-wpup-mobile' ).val().trim(),
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
		$w.on( 'click', '.js-wpup-resend', function () {
			resend( f( '.js-wpup-countdown' ), f( '.js-wpup-resend' ), countdownTimer );
		} );
		$w.on( 'click', '.js-wpup-resend-reg', function () {
			resend( f( '.js-wpup-countdown-reg' ), f( '.js-wpup-resend-reg' ), countdownTimerReg );
		} );

		// ── Verify (existing user)
		function verifyLogin() {
			clearNotice();
			var otp = otpLogin.value();
			if ( otp.length !== 6 ) { notice( i18n.otp_len ); return; }
			var $btn = f( '.js-wpup-verify-login' );
			setBusy( $btn, true );
			$.post( cfg.ajaxurl, {
				action:   'wpup_verify_login',
				nonce:    cfg.nonce,
				mobile:   f( '.js-wpup-mobile' ).val().trim(),
				otp:      otp,
				redirect: f( '.js-wpup-redirect' ).val() || cfg.redirect,
			} )
			.done( handleResponse )
			.fail( function () { notice( i18n.net_error ); } )
			.always( function () { setBusy( $btn, false ); } );
		}

		// ── Verify (new user)
		function verifyRegister() {
			clearNotice();
			var otp       = otpReg.value();
			var firstName = f( '.js-wpup-first-name' ).val().trim();
			var lastName  = f( '.js-wpup-last-name' ).val().trim();
			var username  = f( '.js-wpup-username' ).val().trim();
			var password  = f( '.js-wpup-password' ).val();
			var email     = f( '.js-wpup-email' ).val().trim();

			if ( ! firstName ) { notice( i18n.req_fname ); return; }
			if ( ! lastName )  { notice( i18n.req_lname ); return; }
			if ( ! username )  { notice( i18n.req_uname ); return; }
			if ( password.length < 6 ) { notice( i18n.req_pass ); return; }
			if ( otp.length !== 6 )    { notice( i18n.otp_len );  return; }

			var $btn = f( '.js-wpup-verify-reg' );
			setBusy( $btn, true );
			$.post( cfg.ajaxurl, {
				action:     'wpup_verify_login',
				nonce:      cfg.nonce,
				mobile:     f( '.js-wpup-mobile' ).val().trim(),
				otp:        otp,
				first_name: firstName,
				last_name:  lastName,
				username:   username,
				password:   password,
				email:      email,
				redirect:   f( '.js-wpup-redirect' ).val() || cfg.redirect,
			} )
			.done( handleResponse )
			.fail( function () { notice( i18n.net_error ); } )
			.always( function () { setBusy( $btn, false ); } );
		}

		$w.on( 'click', '.js-wpup-verify-login', verifyLogin );
		$w.on( 'click', '.js-wpup-verify-reg', verifyRegister );

		function handleResponse( res ) {
			if ( ! res.success ) { notice( res.data.message ); return; }
			notice( i18n.success, 'success' );
			setTimeout( function () {
				window.location.href = res.data.redirect || cfg.redirect || '/';
			}, 600 );
		}

		// ── Password toggle
		$w.on( 'click', '.js-wpup-toggle-pass', function () {
			var $i = $( this ).prev( 'input' );
			$i.attr( 'type', $i.attr( 'type' ) === 'password' ? 'text' : 'password' );
		} );
	}

	// =========================================================================
	// Bootstrap + expose factory
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

	window.wpupInitForm = function ( el ) { new WpupForm( el ); };

} )( jQuery );
