/**
 * Fast Index admin screens.
 *
 * Remembers a dismissed Fast Index notice: the close button calls the nonce-protected dismiss URL.
 */
( function () {
	'use strict';

	function closest( element, selector ) {
		while ( element && 1 === element.nodeType ) {
			if ( element.matches( selector ) ) {
				return element;
			}
			element = element.parentNode;
		}
		return null;
	}

	document.addEventListener( 'click', function ( event ) {
		var dismiss = closest( event.target, '.fast-index-notice .notice-dismiss' );
		var notice;

		if ( ! dismiss ) {
			return;
		}

		notice = closest( dismiss, '.fast-index-notice' );
		if ( notice && notice.getAttribute( 'data-dismiss-url' ) && window.fetch ) {
			window.fetch( notice.getAttribute( 'data-dismiss-url' ), { credentials: 'same-origin' } );
		}
	} );
}() );
