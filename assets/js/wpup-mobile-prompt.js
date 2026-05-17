/* global wpupPrompt, jQuery */
( function ( $ ) {
	'use strict';

	var cfg = wpupPrompt;

	var $overlay      = $( '#wpup-prompt-overlay' );
	var $notice       = $( '#wpup-prompt-notice' );
	var $stepMobile   = $( '#wpup-prompt-step-mobile' );
	var $stepOtp      = $( '#wpup-prompt-step-otp' );
	var $mobile       = $( '#wpup-prompt-mobile' );
	var $otp          = $( '#wpup-prompt-otp' );
	var $btnSend      = $( '#wpup-prompt-send' );
	var $btnVerify    = $( '#wpup-prompt-verify' );
	var $btnChange    = $( '#wpup-prompt-change-mobile' );
	var $btnDismiss   = $( '#wpup-prompt-dismiss' );
	var $btnClose     = $( '#wpup-prompt-close' );

	function showNotice( msg, type ) {
		$notice.text( msg )
			.removeClass( 'wpup-notice--success wpup-notice--error' )
			.addClass( 'wpup-notice--' + ( type || 'error' ) )
			.show();
	}

	function hideNotice() { $notice.hide(); }

	function hideOverlay() { $overlay.fadeOut( 200 ); }

	// ── Send OTP ─────────────────────────────────────────────────────────────

	$btnSend.on( 'click', function () {
		hideNotice();
		var mobile = $mobile.val().trim();
		if ( ! /^09[0-9]{9}$/.test( mobile ) ) {
			showNotice( 'شماره موبایل معتبر نیست.' );
			return;
		}

		$btnSend.prop( 'disabled', true ).text( 'در حال ارسال…' );

		$.post( cfg.ajaxurl, {
			action : 'wpup_prompt_send_otp',
			nonce  : cfg.nonce,
			mobile : mobile,
		} )
		.done( function ( res ) {
			if ( ! res.success ) {
				showNotice( res.data.message );
				return;
			}
			showNotice( 'کد تأیید ارسال شد.', 'success' );
			$stepMobile.hide();
			$stepOtp.show();
			$otp.focus();
		} )
		.fail( function () { showNotice( 'خطا در ارتباط با سرور.' ); } )
		.always( function () { $btnSend.prop( 'disabled', false ).text( 'ارسال کد تأیید' ); } );
	} );

	// ── Verify OTP ───────────────────────────────────────────────────────────

	$btnVerify.on( 'click', function () {
		hideNotice();
		var otp = $otp.val().trim();
		if ( otp.length !== 6 ) {
			showNotice( 'کد تأیید باید ۶ رقم باشد.' );
			return;
		}

		$btnVerify.prop( 'disabled', true ).text( 'در حال تأیید…' );

		$.post( cfg.ajaxurl, {
			action : 'wpup_prompt_verify',
			nonce  : cfg.nonce,
			mobile : $mobile.val().trim(),
			otp    : otp,
		} )
		.done( function ( res ) {
			if ( ! res.success ) {
				showNotice( res.data.message );
				return;
			}
			showNotice( 'شماره موبایل با موفقیت ثبت شد.', 'success' );
			setTimeout( hideOverlay, 1200 );
		} )
		.fail( function () { showNotice( 'خطا در ارتباط با سرور.' ); } )
		.always( function () { $btnVerify.prop( 'disabled', false ).text( 'تأیید و ذخیره' ); } );
	} );

	// ── Change mobile ────────────────────────────────────────────────────────

	$btnChange.on( 'click', function () {
		hideNotice();
		$stepOtp.hide();
		$stepMobile.show();
		$mobile.focus();
	} );

	// ── Dismiss (close / "later") ────────────────────────────────────────────

	function dismiss() {
		$.post( cfg.ajaxurl, {
			action : 'wpup_prompt_dismiss',
			nonce  : cfg.nonce,
		} );
		hideOverlay();
	}

	$btnDismiss.on( 'click', dismiss );
	$btnClose.on( 'click', dismiss );

	// Digit-only for OTP
	$otp.on( 'input', function () {
		$( this ).val( $( this ).val().replace( /\D/g, '' ) );
	} );

} )( jQuery );
