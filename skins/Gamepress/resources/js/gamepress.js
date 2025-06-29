jQuery( function( $ ) {
	/**
	 * Scrolling to top of page
	 */
	$( '.scrollup' ).on( 'click', function() {
		$( 'html, body' ).animate( { scrollTop: 0 }, 1000, 'easeOutQuint' );
		return false;
	} );

	/**
	 * Make the top menu usable on touchscreen devices so that it's possible to
	 * open up a menu and tap on the sub-menu items
	 */
	$( '#primary-nav li.page_item_has_children > a' ).on( 'touchstart', function() {
		$( this ).off( 'touchstart' ).on( 'click', function( e ) {
			e.preventDefault();
		} );
		e.preventDefault();
	} );
} );
