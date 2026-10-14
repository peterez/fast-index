/**
 * Help & FAQ screen of Fast Index.
 *
 * - "Copy system info": copies the read-only text; when the Clipboard API is missing or refused,
 *   the text field is shown and selected so it can be copied by hand.
 * - Opens the <details> element that an address like #fi-help-faq-quota points at.
 */
( function () {
	'use strict';

	var config = window.fastIndexHelp || {};

	function say( button, message ) {
		var status = button.parentNode ? button.parentNode.querySelector( '.fi-help-copy-status' ) : null;

		if ( status ) {
			status.textContent = message;
		}
	}

	function selectText( field, button ) {
		var done = false;

		// The field is out of sight and hidden from assistive technology until the person has to copy by hand.
		field.classList.add( 'fi-help-sysinfo-source--shown' );
		field.removeAttribute( 'aria-hidden' );
		field.focus();
		field.select();

		if ( field.setSelectionRange ) {
			field.setSelectionRange( 0, field.value.length );
		}

		try {
			done = document.execCommand && document.execCommand( 'copy' );
		} catch ( error ) {
			done = false;
		}

		say( button, done ? config.copied : config.selected );
	}

	function copy( button ) {
		var field = document.getElementById( button.getAttribute( 'data-fast-index-copy' ) );

		if ( ! field ) {
			return;
		}

		if ( navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext ) {
			navigator.clipboard.writeText( field.value ).then(
				function () {
					say( button, config.copied );
				},
				function () {
					selectText( field, button );
				}
			);
			return;
		}

		selectText( field, button );
	}

	function openTarget() {
		var target = window.location.hash ? document.getElementById( window.location.hash.slice( 1 ) ) : null;

		if ( target && 'DETAILS' === target.tagName ) {
			target.open = true;
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '[data-fast-index-copy]' ) : null;

		if ( button ) {
			copy( button );
		}
	} );

	window.addEventListener( 'hashchange', openTarget );
	openTarget();
}() );
