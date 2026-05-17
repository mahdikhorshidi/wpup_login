( function ( $ ) {
	'use strict';

	function toggleIppanelSection() {
		var provider = $( '#wpup_sms_provider' ).val();
		var $rows    = $( 'input[name^="wpup_ippanel_"]' ).closest( 'tr' );
		var $heading = $rows.first().closest( 'table' ).prev( 'p' ).prev( 'h2' );

		if ( 'ippanel' === provider ) {
			$rows.show();
			$heading.show();
		} else {
			$rows.hide();
		}
	}

	$( function () {
		toggleIppanelSection();
		$( '#wpup_sms_provider' ).on( 'change', toggleIppanelSection );
	} );

} )( jQuery );
