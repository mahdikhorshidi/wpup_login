/* global wpupLoginCfg, jQuery, wpupInitForm */
/**
 * Finds native WP / WooCommerce login forms anywhere on the page
 * (sidebar widgets, theme popups, WoodMart header, etc.) and replaces
 * them with a cloned Optiwise mobile-login form.
 *
 * The source template is a <script type="text/html" id="wpup-login-tpl">
 * printed by PHP in wp_footer.
 */
( function ( $ ) {
	'use strict';

	// Selectors for native WP / WC login forms we want to replace.
	// Order matters: most specific first.
	var NATIVE_SELECTORS = [
		// WooCommerce My Account form (PHP ob_start handles this but as safety net)
		'.woocommerce form.login',
		'.woocommerce form.register',
		// WP login page standard form
		'#loginform',
		// Login widget
		'.widget_loginout form',
		// WoodMart account header popup / dropdown
		'.wd-header-account-popup form',
		'.wd-header-account form.login',
		'.wd-header-account .woocommerce-form-login',
		// Storefront / WooCommerce blocks
		'.wc-block-components-checkout-step form.login',
		// Generic theme login forms that have WC classes
		'form.woocommerce-form-login',
		// Generic: any form with a WP nonce login field
		'form input[name="log"]',
	];

	var $tplScript = $( '#wpup-login-tpl' );
	if ( ! $tplScript.length ) { return; }

	var tplHtml = $tplScript.html();
	var uid = 0;

	function cloneForm( redirectUrl ) {
		uid++;
		var suffix = '--w' + uid;
		// Make all id / for / aria-* attributes unique so multi-instance works.
		var html = tplHtml
			.replace( /\bid="(wpup-[^"]+)"/g,          'id="$1' + suffix + '"' )
			.replace( /\bfor="(wpup-[^"]+)"/g,          'for="$1' + suffix + '"' )
			.replace( /aria-controls="(wpup-[^"]+)"/g,  'aria-controls="$1' + suffix + '"' )
			.replace( /aria-labelledby="(wpup-[^"]+)"/g,'aria-labelledby="$1' + suffix + '"' )
			.replace( /data-panel="(wpup-[^"]+)"/g,     'data-panel="$1' + suffix + '"' )
			.replace( /data-target="(wpup-[^"]+)"/g,    'data-target="$1' + suffix + '"' );

		var $clone = $( html );
		if ( redirectUrl ) {
			$clone.find( '.js-wpup-redirect' ).val( redirectUrl );
		}
		return $clone;
	}

	function inject() {
		var replaced = [];

		// Normalise selectors so we don't try to replace the same element twice.
		NATIVE_SELECTORS.forEach( function ( sel ) {
			try {
				$( sel ).each( function () {
					var $native = $( this ).closest( 'form, .woocommerce-form' );
					if ( ! $native.length ) { $native = $( this ); }

					// Skip if already inside our own form or already replaced.
					if (
						$native.closest( '.wpup-login-wrapper' ).length ||
						$native.closest( '[data-wpup-replaced]' ).length ||
						$native.is( '[data-wpup-replaced]' )
					) { return; }

					// Only replace on non-logged-in pages; double-check via presence
					// of typical WC / WP auth fields.
					if (
						! $native.find( 'input[name="log"], input[name="username"], input[name="email"]' ).length
					) { return; }

					var $form     = cloneForm( wpupLoginCfg.redirect );
					var $wrap     = $( '<div data-wpup-replaced="1"></div>' ).append( $form );

					$native.replaceWith( $wrap );
					wpupInitForm( $form[0] );
					replaced.push( $native[0] );
				} );
			} catch ( e ) { /* ignore unknown selector errors */ }
		} );
	}

	// Run after DOM is ready and after any theme JS has run.
	$( function () {
		inject();

		// Also handle dynamically opened panels (WoodMart popup, Ajax cart, etc.)
		// by re-checking on common theme events.
		var events = [
			'woodmart.panel.opened',
			'wc-mini-cart-ready',
			'added_to_cart',
			'tribe_events_after_ajax',
		];
		$( document ).on( events.join( ' ' ), function () {
			setTimeout( inject, 100 );
		} );

		// MutationObserver: catch late-rendered popups (e.g. WoodMart account panel).
		if ( window.MutationObserver ) {
			var observer = new MutationObserver( function ( mutations ) {
				mutations.forEach( function ( m ) {
					m.addedNodes.forEach( function ( node ) {
						if ( node.nodeType !== 1 ) { return; }
						var $node = $( node );
						if (
							$node.find( 'form.login, form#loginform, form.woocommerce-form-login' ).length ||
							$node.is( 'form.login, form#loginform' )
						) {
							setTimeout( inject, 50 );
						}
					} );
				} );
			} );
			observer.observe( document.body, { childList: true, subtree: true } );
		}
	} );

} )( jQuery );
