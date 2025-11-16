/**
 * JavaScript file for the CookieConsent extension.
 */

( function ( $, cookieConsent ) {
	$.when( $.ready ).then( function () {
		cookieConsent.init();
	} );
} )( jQuery, cookieConsent );
