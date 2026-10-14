/**
 * Fast Index admin screens.
 *
 * - Remembers a dismissed Fast Index notice (the close button calls the nonce-protected dismiss URL).
 * - Keeps the premium prompt of locked settings (behaviour of 2.x).
 */
( function () {
	'use strict';

	var config = window.fastIndexAdmin || {};

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
		var locked;
		var notice;

		if ( dismiss ) {
			notice = closest( dismiss, '.fast-index-notice' );
			if ( notice && notice.getAttribute( 'data-dismiss-url' ) && window.fetch ) {
				window.fetch( notice.getAttribute( 'data-dismiss-url' ), { credentials: 'same-origin' } );
			}
			return;
		}

		locked = closest( event.target, '.licenceAlert' );

		if ( locked && config.upgradeMessage ) {
			event.preventDefault();
			if ( window.confirm( config.upgradeMessage ) ) {
				window.location.href = config.upgradeUrl;
			}
		}
	} );
}() );
