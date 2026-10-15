/*!
 * CultDevOps UI 2.0.0
 *
 * Behaviour for the CultDevOps design library (cdo-ui.css). No dependency; wp.i18n and wp.a11y are used when the page
 * has them. One global: window.CDOUI. Components start by themselves from data-cdo-* attributes when the page loads;
 * call CDOUI.init( element ) after adding markup later. Every component works without this file (native select,
 * <details>, links to sections); this file adds the keyboard and screen-reader behaviour of the WAI-ARIA patterns.
 *
 * Single source: tools/cdo-ui/ in the CultDevOps workspace; copied byte for byte into each plugin by tools/cdo-ui/sync.sh.
 * Guide: tools/cdo-ui/CSS_GUIDE.md. License: GPL-2.0-or-later.
 */
( function ( window, document ) {
	'use strict';

	var VERSION = '2.0.0';

	if ( window.CDOUI && window.CDOUI.version ) {
		return;
	}

	var config = { textdomain: 'default' };
	var uid = 0;
	var liveRegion = null;

	/* ------------------------------------------------------------------ helpers */

	function extend( target, source ) {
		var key;
		if ( source ) {
			for ( key in source ) {
				if ( Object.prototype.hasOwnProperty.call( source, key ) ) {
					target[ key ] = source[ key ];
				}
			}
		}
		return target;
	}

	function nextId( prefix ) {
		uid += 1;
		return 'cdo-' + prefix + '-' + uid;
	}

	function i18n() {
		return window.wp && window.wp.i18n ? window.wp.i18n : null;
	}

	/* Translate with the plugin's text domain when wp.i18n is on the page; otherwise the English source. */
	function __( text ) {
		var lib = i18n();
		return lib && lib.__ ? lib.__( text, config.textdomain ) : text;
	}

	function _n( single, plural, number ) {
		var lib = i18n();
		if ( lib && lib._n ) {
			return lib._n( single, plural, number, config.textdomain );
		}
		return 1 === number ? single : plural;
	}

	/* sprintf for %s, %d and numbered %1$s placeholders. */
	function sprintf( format ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var index = 0;
		return String( format ).replace( /%(?:(\d+)\$)?([sd%])/g, function ( match, position, type ) {
			var value;
			if ( '%' === type ) {
				return '%';
			}
			value = position ? args[ parseInt( position, 10 ) - 1 ] : args[ index++ ];
			if ( 'd' === type ) {
				value = parseInt( value, 10 );
				return isNaN( value ) ? '' : String( value );
			}
			return undefined === value ? '' : String( value );
		} );
	}

	function closest( element, selector ) {
		var matches = Element.prototype.matches || Element.prototype.msMatchesSelector || Element.prototype.webkitMatchesSelector;
		while ( element && 1 === element.nodeType ) {
			if ( matches.call( element, selector ) ) {
				return element;
			}
			element = element.parentNode;
		}
		return null;
	}

	function all( root, selector ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( selector ) );
	}

	function emit( element, name, detail ) {
		var event;
		if ( 'function' === typeof window.CustomEvent ) {
			event = new window.CustomEvent( name, { bubbles: true, cancelable: true, detail: detail || {} } );
		} else {
			event = document.createEvent( 'CustomEvent' );
			event.initCustomEvent( name, true, true, detail || {} );
		}
		return element.dispatchEvent( event );
	}

	function fire( element, name ) {
		var event;
		if ( 'function' === typeof window.Event ) {
			event = new window.Event( name, { bubbles: true } );
		} else {
			event = document.createEvent( 'HTMLEvents' );
			event.initEvent( name, true, false );
		}
		element.dispatchEvent( event );
	}

	function addToken( element, attribute, token ) {
		var list = ( element.getAttribute( attribute ) || '' ).split( /\s+/ ).filter( Boolean );
		if ( list.indexOf( token ) === -1 ) {
			list.push( token );
		}
		element.setAttribute( attribute, list.join( ' ' ) );
	}

	function removeToken( element, attribute, token ) {
		var list = ( element.getAttribute( attribute ) || '' ).split( /\s+/ ).filter( function ( item ) {
			return item && item !== token;
		} );
		if ( list.length ) {
			element.setAttribute( attribute, list.join( ' ' ) );
		} else {
			element.removeAttribute( attribute );
		}
	}

	/* True the first time a component starts on an element; init() can run again on the same markup safely. */
	function once( element, name ) {
		if ( element.getAttribute( 'data-cdo-ready-' + name ) ) {
			return false;
		}
		element.setAttribute( 'data-cdo-ready-' + name, '1' );
		return true;
	}

	/* Lower case, accents removed: "Ürün" finds "urun" and "ürün". */
	function fold( text ) {
		var value = String( text || '' ).toLowerCase();
		if ( value.normalize ) {
			value = value.normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' );
		}
		return value.replace( /ı/g, 'i' );
	}

	/* Say something to screen readers: wp.a11y.speak when the page has it, our own live region otherwise. */
	function announce( message, politeness ) {
		if ( ! message ) {
			return;
		}
		if ( window.wp && window.wp.a11y && window.wp.a11y.speak ) {
			window.wp.a11y.speak( message, politeness || 'polite' );
			return;
		}
		if ( ! liveRegion ) {
			liveRegion = document.createElement( 'div' );
			liveRegion.setAttribute( 'aria-live', 'polite' );
			liveRegion.setAttribute( 'aria-atomic', 'true' );
			liveRegion.style.cssText = 'position:absolute;width:1px;height:1px;margin:-1px;padding:0;border:0;overflow:hidden;clip-path:inset(50%);white-space:nowrap;';
			document.body.appendChild( liveRegion );
		}
		liveRegion.setAttribute( 'aria-live', 'assertive' === politeness ? 'assertive' : 'polite' );
		liveRegion.textContent = '';
		window.setTimeout( function () {
			liveRegion.textContent = message;
		}, 60 );
	}

	var FOCUSABLE = 'a[href], area[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), summary, iframe, [tabindex]:not([tabindex="-1"]), [contenteditable="true"]';

	function focusables( root ) {
		return all( root, FOCUSABLE ).filter( function ( element ) {
			return element.offsetWidth > 0 || element.offsetHeight > 0 || element.getClientRects().length > 0;
		} );
	}

	function isRtl( element ) {
		var direction = window.getComputedStyle ? window.getComputedStyle( element ).direction : '';
		return 'rtl' === direction;
	}

	/* ------------------------------------------------------------------ combobox */

	/*
	 * WAI-ARIA 1.2 combobox with a listbox popup, built over a native <select data-cdo-combobox>. The select keeps the
	 * value and is what the form submits; it gets a "change" event whenever the person picks something, so code that
	 * listens to the select keeps working.
	 */
	function Combobox( select ) {
		this.select = select;
		this.multiple = !! select.multiple;
		this.items = [];
		this.active = -1;
		this.isOpen = false;
		this.syncing = false;
		this.build();
		this.bind();
		this.render();
		select.cdoCombobox = this;
	}

	Combobox.prototype.build = function () {
		var select = this.select;
		var wrapper = document.createElement( 'div' );
		var field = document.createElement( 'div' );
		var input = document.createElement( 'input' );
		var toggle = document.createElement( 'button' );
		var popup = document.createElement( 'div' );
		var listbox = document.createElement( 'div' );
		var empty = document.createElement( 'p' );
		var label = select.id ? document.querySelector( 'label[for="' + select.id + '"]' ) : null;
		var listId = nextId( 'listbox' );
		var describedBy = select.getAttribute( 'aria-describedby' );
		var width = select.getAttribute( 'data-cdo-width' );

		wrapper.className = 'cdo-combobox' + ( this.multiple ? ' cdo-combobox--multi' : '' ) + ( 'full' === width ? ' cdo-combobox--full' : '' );
		field.className = 'cdo-combobox__field';

		if ( this.multiple ) {
			this.chips = document.createElement( 'ul' );
			this.chips.className = 'cdo-combobox__chips';
			field.appendChild( this.chips );
		}

		input.type = 'text';
		input.className = 'cdo-combobox__input';
		input.id = select.id ? select.id + '-cdo' : nextId( 'combobox' );
		input.setAttribute( 'role', 'combobox' );
		input.setAttribute( 'aria-autocomplete', 'list' );
		input.setAttribute( 'aria-expanded', 'false' );
		input.setAttribute( 'aria-controls', listId );
		input.setAttribute( 'autocomplete', 'off' );
		input.setAttribute( 'autocapitalize', 'off' );
		input.setAttribute( 'spellcheck', 'false' );
		// Direction from the text itself: an address stays left to right on an Arabic screen, an Arabic label right to left.
		input.setAttribute( 'dir', 'auto' );
		input.placeholder = select.getAttribute( 'data-cdo-placeholder' ) || ( this.multiple ? __( 'Type to search…' ) : '' );

		if ( label ) {
			if ( ! label.id ) {
				label.id = nextId( 'label' );
			}
			input.setAttribute( 'aria-labelledby', label.id );
			listbox.setAttribute( 'aria-labelledby', label.id );
			label.htmlFor = input.id;
			this.label = label;
		} else if ( select.getAttribute( 'aria-labelledby' ) ) {
			input.setAttribute( 'aria-labelledby', select.getAttribute( 'aria-labelledby' ) );
			listbox.setAttribute( 'aria-labelledby', select.getAttribute( 'aria-labelledby' ) );
		} else if ( select.getAttribute( 'aria-label' ) ) {
			input.setAttribute( 'aria-label', select.getAttribute( 'aria-label' ) );
			listbox.setAttribute( 'aria-label', select.getAttribute( 'aria-label' ) );
		}
		if ( describedBy ) {
			input.setAttribute( 'aria-describedby', describedBy );
		}
		if ( select.required ) {
			input.setAttribute( 'aria-required', 'true' );
		}
		if ( 'true' === select.getAttribute( 'aria-invalid' ) ) {
			input.setAttribute( 'aria-invalid', 'true' );
			wrapper.className += ' is-invalid';
		}

		toggle.type = 'button';
		toggle.className = 'cdo-combobox__toggle';
		toggle.tabIndex = -1;
		toggle.setAttribute( 'aria-label', __( 'Show options' ) );
		toggle.setAttribute( 'aria-expanded', 'false' );
		toggle.setAttribute( 'aria-controls', listId );

		popup.className = 'cdo-combobox__popup';
		popup.hidden = true;
		listbox.className = 'cdo-combobox__listbox';
		listbox.id = listId;
		listbox.setAttribute( 'role', 'listbox' );
		if ( this.multiple ) {
			listbox.setAttribute( 'aria-multiselectable', 'true' );
		}
		empty.className = 'cdo-combobox__empty';
		empty.hidden = true;
		empty.textContent = select.getAttribute( 'data-cdo-empty' ) || __( 'Nothing matches. Try a shorter or different word.' );

		this.buildOptions( select, listbox );

		field.appendChild( input );
		field.appendChild( toggle );
		popup.appendChild( listbox );
		popup.appendChild( empty );
		wrapper.appendChild( field );
		wrapper.appendChild( popup );

		select.parentNode.insertBefore( wrapper, select );
		wrapper.appendChild( select );
		select.className = ( select.className ? select.className + ' ' : '' ) + 'cdo-combobox__native';
		select.tabIndex = -1;
		select.setAttribute( 'aria-hidden', 'true' );

		this.wrapper = wrapper;
		this.field = field;
		this.input = input;
		this.toggleButton = toggle;
		this.popup = popup;
		this.listbox = listbox;
		this.empty = empty;

		if ( select.disabled ) {
			input.disabled = true;
			wrapper.className += ' is-disabled';
		}

		// The library's own wrapper (.cdo-select) would draw a second chevron around the new field.
		if ( wrapper.parentNode && /(^|\s)cdo-select(\s|$)/.test( wrapper.parentNode.className ) ) {
			wrapper.parentNode.className = wrapper.parentNode.className.replace( /(^|\s)cdo-select(--\w+)?/g, ' ' ).trim();
		}
	};

	Combobox.prototype.buildOptions = function ( select, listbox ) {
		var self = this;
		var listId = listbox.id;

		function addOption( option, parent ) {
			var element = document.createElement( 'div' );
			var text = document.createElement( 'span' );
			var description = option.getAttribute( 'data-cdo-description' );
			var item;
			element.className = 'cdo-combobox__option';
			element.id = listId + '-' + self.items.length;
			element.setAttribute( 'role', 'option' );
			element.setAttribute( 'aria-selected', 'false' );
			text.className = 'cdo-combobox__option-text';
			text.textContent = option.text;
			element.appendChild( text );
			if ( description ) {
				var desc = document.createElement( 'span' );
				desc.className = 'cdo-combobox__option-desc';
				desc.textContent = description;
				element.appendChild( desc );
			}
			if ( option.disabled ) {
				element.setAttribute( 'aria-disabled', 'true' );
			}
			item = {
				element: element,
				textNode: text,
				option: option,
				label: option.text,
				search: fold( option.text + ' ' + ( description || '' ) + ' ' + ( option.getAttribute( 'data-cdo-keywords' ) || '' ) ),
				disabled: option.disabled,
				placeholder: '' === option.value && ! self.multiple
			};
			element.setAttribute( 'data-cdo-index', String( self.items.length ) );
			self.items.push( item );
			parent.appendChild( element );
		}

		Array.prototype.forEach.call( select.children, function ( child ) {
			var group;
			var groupLabel;
			if ( 'OPTGROUP' === child.tagName ) {
				group = document.createElement( 'div' );
				groupLabel = document.createElement( 'div' );
				group.className = 'cdo-combobox__group';
				group.setAttribute( 'role', 'group' );
				groupLabel.className = 'cdo-combobox__group-label';
				groupLabel.id = nextId( 'group' );
				groupLabel.setAttribute( 'role', 'presentation' );
				groupLabel.textContent = child.label;
				group.setAttribute( 'aria-labelledby', groupLabel.id );
				group.appendChild( groupLabel );
				Array.prototype.forEach.call( child.children, function ( option ) {
					addOption( option, group );
				} );
				listbox.appendChild( group );
			} else if ( 'OPTION' === child.tagName ) {
				addOption( child, listbox );
			}
		} );
	};

	Combobox.prototype.bind = function () {
		var self = this;
		var input = this.input;

		input.addEventListener( 'keydown', function ( event ) {
			self.onKeydown( event );
		} );
		input.addEventListener( 'input', function () {
			self.filter( input.value );
			if ( ! self.isOpen ) {
				self.open();
			}
			self.setActive( self.firstVisible( 0, 1 ) );
		} );
		input.addEventListener( 'focus', function () {
			if ( ! self.multiple ) {
				input.select();
			}
		} );
		input.addEventListener( 'blur', function () {
			window.setTimeout( function () {
				if ( ! self.wrapper.contains( document.activeElement ) ) {
					self.close();
					self.restoreText();
				}
			}, 120 );
		} );

		this.field.addEventListener( 'mousedown', function ( event ) {
			if ( closest( event.target, '.cdo-chip__remove' ) || input.disabled ) {
				return;
			}
			if ( event.target !== input ) {
				event.preventDefault();
				input.focus();
			}
			if ( self.isOpen && event.target !== input ) {
				self.close();
			} else if ( ! self.isOpen ) {
				self.filter( '' );
				self.open();
				self.setActive( self.selectedIndex() );
			}
		} );

		this.listbox.addEventListener( 'mousedown', function ( event ) {
			event.preventDefault();
		} );
		this.listbox.addEventListener( 'click', function ( event ) {
			var element = closest( event.target, '[role="option"]' );
			if ( element ) {
				self.choose( parseInt( element.getAttribute( 'data-cdo-index' ), 10 ) );
			}
		} );
		this.listbox.addEventListener( 'mousemove', function ( event ) {
			var element = closest( event.target, '[role="option"]' );
			var index = element ? parseInt( element.getAttribute( 'data-cdo-index' ), 10 ) : -1;
			if ( index > -1 && index !== self.active && ! self.items[ index ].disabled ) {
				self.setActive( index, true );
			}
		} );

		if ( this.chips ) {
			this.chips.addEventListener( 'click', function ( event ) {
				var button = closest( event.target, '.cdo-chip__remove' );
				if ( button ) {
					self.choose( parseInt( button.getAttribute( 'data-cdo-index' ), 10 ), true );
					input.focus();
				}
			} );
		}

		// Changes made to the select by other code (or a form reset) show up in the combobox.
		this.select.addEventListener( 'change', function () {
			if ( ! self.syncing ) {
				self.render();
			}
		} );
		if ( this.select.form ) {
			this.select.form.addEventListener( 'reset', function () {
				window.setTimeout( function () {
					self.render();
				}, 0 );
			} );
		}

		// A required select left empty: show our message on the combobox instead of the browser's on a hidden select.
		this.select.addEventListener( 'invalid', function ( event ) {
			event.preventDefault();
			setFieldState( input, 'error', self.select.getAttribute( 'data-cdo-required-message' ) || __( 'Choose at least one option.' ) );
			self.wrapper.className += ' is-invalid';
			input.focus();
		} );

		document.addEventListener( 'mousedown', function ( event ) {
			if ( self.isOpen && ! self.wrapper.contains( event.target ) ) {
				self.close();
				self.restoreText();
			}
		} );
	};

	Combobox.prototype.onKeydown = function ( event ) {
		var key = event.key;
		var input = this.input;
		var handled = true;

		if ( 'ArrowDown' === key || 'Down' === key ) {
			if ( ! this.isOpen ) {
				this.filter( '' );
				this.open();
				if ( ! event.altKey ) {
					this.setActive( this.selectedIndex() > -1 ? this.selectedIndex() : this.firstVisible( 0, 1 ) );
				}
			} else {
				this.move( 1 );
			}
		} else if ( 'ArrowUp' === key || 'Up' === key ) {
			if ( ! this.isOpen ) {
				this.filter( '' );
				this.open();
				this.setActive( this.selectedIndex() > -1 ? this.selectedIndex() : this.firstVisible( this.items.length - 1, -1 ) );
			} else if ( event.altKey ) {
				this.close();
			} else {
				this.move( -1 );
			}
		} else if ( ( 'Home' === key || 'End' === key ) && this.isOpen ) {
			this.setActive( 'Home' === key ? this.firstVisible( 0, 1 ) : this.firstVisible( this.items.length - 1, -1 ) );
		} else if ( ( 'PageDown' === key || 'PageUp' === key ) && this.isOpen ) {
			this.move( 'PageDown' === key ? 10 : -10 );
		} else if ( 'Enter' === key ) {
			if ( this.isOpen && this.active > -1 ) {
				this.choose( this.active );
			} else if ( ! this.isOpen ) {
				handled = false;
			}
		} else if ( 'Escape' === key || 'Esc' === key ) {
			if ( this.isOpen ) {
				this.close();
				this.restoreText();
			} else if ( input.value ) {
				input.value = '';
				this.filter( '' );
				this.restoreText();
			} else {
				handled = false;
			}
		} else if ( 'Backspace' === key && this.multiple && '' === input.value ) {
			var last = this.selectedIndexes().pop();
			if ( undefined !== last ) {
				this.choose( last, true );
			} else {
				handled = false;
			}
		} else if ( 'Tab' === key ) {
			this.close();
			handled = false;
		} else {
			handled = false;
		}

		if ( handled ) {
			event.preventDefault();
		}
	};

	Combobox.prototype.visibleIndexes = function () {
		var list = [];
		this.items.forEach( function ( item, index ) {
			if ( ! item.element.hidden && ! item.disabled ) {
				list.push( index );
			}
		} );
		return list;
	};

	Combobox.prototype.firstVisible = function ( from, step ) {
		var index;
		for ( index = from; index >= 0 && index < this.items.length; index += step ) {
			if ( ! this.items[ index ].element.hidden && ! this.items[ index ].disabled ) {
				return index;
			}
		}
		return -1;
	};

	Combobox.prototype.move = function ( step ) {
		var visible = this.visibleIndexes();
		var position = visible.indexOf( this.active );
		if ( ! visible.length ) {
			return;
		}
		if ( position === -1 ) {
			position = step > 0 ? -1 : visible.length;
		}
		position = Math.max( 0, Math.min( visible.length - 1, position + step ) );
		this.setActive( visible[ position ] );
	};

	Combobox.prototype.setActive = function ( index, fromPointer ) {
		var item;
		if ( this.active > -1 && this.items[ this.active ] ) {
			this.items[ this.active ].element.classList.remove( 'is-active' );
		}
		this.active = index;
		if ( index < 0 || ! this.items[ index ] ) {
			this.active = -1;
			this.input.removeAttribute( 'aria-activedescendant' );
			return;
		}
		item = this.items[ index ];
		item.element.classList.add( 'is-active' );
		this.input.setAttribute( 'aria-activedescendant', item.element.id );
		if ( ! fromPointer ) {
			this.scrollIntoView( item.element );
		}
	};

	Combobox.prototype.scrollIntoView = function ( element ) {
		var list = this.listbox;
		var top = element.offsetTop;
		var bottom = top + element.offsetHeight;
		if ( element.offsetParent !== list ) {
			top -= list.offsetTop;
			bottom -= list.offsetTop;
		}
		if ( top < list.scrollTop ) {
			list.scrollTop = top;
		} else if ( bottom > list.scrollTop + list.clientHeight ) {
			list.scrollTop = bottom - list.clientHeight;
		}
	};

	Combobox.prototype.selectedIndex = function () {
		var found = -1;
		this.items.some( function ( item, index ) {
			if ( item.option.selected && ! item.placeholder ) {
				found = index;
				return true;
			}
			return false;
		} );
		return found;
	};

	Combobox.prototype.selectedIndexes = function () {
		var list = [];
		this.items.forEach( function ( item, index ) {
			if ( item.option.selected ) {
				list.push( index );
			}
		} );
		return list;
	};

	Combobox.prototype.filter = function ( query ) {
		var needle = fold( query ).trim();
		var count = 0;
		var self = this;

		this.items.forEach( function ( item ) {
			var match = ! needle || item.search.indexOf( needle ) !== -1;
			item.element.hidden = ! match;
			self.highlight( item, match && needle ? needle : '' );
			if ( match && ! item.disabled && ! item.placeholder ) {
				count++;
			}
		} );
		all( this.listbox, '.cdo-combobox__group' ).forEach( function ( group ) {
			group.hidden = ! group.querySelector( '[role="option"]:not([hidden])' );
		} );
		this.empty.hidden = count > 0;
		this.count = count;

		if ( needle ) {
			window.clearTimeout( this.announceTimer );
			this.announceTimer = window.setTimeout( function () {
				announce( count ? sprintf( _n( '%d option found.', '%d options found.', count ), count ) : self.empty.textContent );
			}, 400 );
		}
	};

	/* Bold the matched part of an option; text nodes only, so labels are never read as HTML. */
	Combobox.prototype.highlight = function ( item, needle ) {
		var label = item.label;
		var folded = fold( label );
		var at = needle ? folded.indexOf( needle ) : -1;
		var node = item.textNode;
		var mark;

		while ( node.firstChild ) {
			node.removeChild( node.firstChild );
		}
		// Folding can change the length of a label (decomposed letters); highlight only when it did not.
		if ( at === -1 || folded.length !== label.length ) {
			node.textContent = label;
			return;
		}
		node.appendChild( document.createTextNode( label.slice( 0, at ) ) );
		mark = document.createElement( 'mark' );
		mark.className = 'cdo-combobox__match';
		mark.textContent = label.slice( at, at + needle.length );
		node.appendChild( mark );
		node.appendChild( document.createTextNode( label.slice( at + needle.length ) ) );
	};

	Combobox.prototype.open = function () {
		var rect;
		var below;
		if ( this.isOpen || this.input.disabled ) {
			return;
		}
		this.isOpen = true;
		this.popup.hidden = false;
		this.wrapper.classList.add( 'is-open' );
		this.input.setAttribute( 'aria-expanded', 'true' );
		this.toggleButton.setAttribute( 'aria-expanded', 'true' );
		this.toggleButton.setAttribute( 'aria-label', __( 'Hide options' ) );

		// Open upwards when the screen has no room below and more room above.
		this.wrapper.classList.remove( 'cdo-combobox--up' );
		rect = this.field.getBoundingClientRect();
		below = window.innerHeight - rect.bottom;
		if ( below < Math.min( 300, this.popup.offsetHeight + 8 ) && rect.top > below ) {
			this.wrapper.classList.add( 'cdo-combobox--up' );
		}
	};

	Combobox.prototype.close = function () {
		if ( ! this.isOpen ) {
			return;
		}
		this.isOpen = false;
		this.popup.hidden = true;
		this.wrapper.classList.remove( 'is-open' );
		this.input.setAttribute( 'aria-expanded', 'false' );
		this.toggleButton.setAttribute( 'aria-expanded', 'false' );
		this.toggleButton.setAttribute( 'aria-label', __( 'Show options' ) );
		this.setActive( -1 );
	};

	/* After closing: a single combobox shows the chosen label again; a multiple one clears its search text. */
	Combobox.prototype.restoreText = function () {
		var index;
		if ( this.multiple ) {
			this.input.value = '';
		} else {
			index = this.selectedIndex();
			this.input.value = index > -1 ? this.items[ index ].label : '';
			// Still in the field: the label is selected, so typing replaces it instead of adding to it.
			if ( document.activeElement === this.input ) {
				this.input.select();
			}
		}
		this.filter( '' );
	};

	Combobox.prototype.choose = function ( index, fromChip ) {
		var item = this.items[ index ];
		var wasSelected;
		if ( ! item || item.disabled ) {
			return;
		}
		wasSelected = item.option.selected;
		if ( this.multiple ) {
			item.option.selected = ! wasSelected;
		} else {
			this.select.value = item.option.value;
			item.option.selected = true;
		}
		this.syncing = true;
		fire( this.select, 'input' );
		fire( this.select, 'change' );
		this.syncing = false;
		this.render();
		this.clearError();

		if ( this.multiple ) {
			announce( sprintf( wasSelected ? __( '%s removed.' ) : __( '%s selected.' ), item.label ) );
			if ( ! fromChip && this.input.value ) {
				this.input.value = '';
				this.filter( '' );
			}
			if ( this.isOpen ) {
				this.setActive( index );
			}
		} else {
			this.close();
			this.input.value = item.placeholder ? '' : item.label;
			this.filter( '' );
			announce( sprintf( __( '%s selected.' ), item.label ) );
		}
	};

	Combobox.prototype.clearError = function () {
		if ( /(^|\s)is-invalid(\s|$)/.test( this.wrapper.className ) && ( this.selectedIndexes().length || ! this.select.required ) ) {
			this.wrapper.className = this.wrapper.className.replace( /(^|\s)is-invalid/g, '' );
			setFieldState( this.input, '', '' );
		}
	};

	Combobox.prototype.render = function () {
		var self = this;
		var index;

		this.items.forEach( function ( item ) {
			item.element.setAttribute( 'aria-selected', item.option.selected && ! item.placeholder ? 'true' : 'false' );
		} );

		if ( this.multiple ) {
			while ( this.chips.firstChild ) {
				this.chips.removeChild( this.chips.firstChild );
			}
			this.items.forEach( function ( item, itemIndex ) {
				var chip;
				var text;
				var remove;
				if ( ! item.option.selected ) {
					return;
				}
				chip = document.createElement( 'li' );
				text = document.createElement( 'span' );
				remove = document.createElement( 'button' );
				chip.className = 'cdo-chip';
				text.className = 'cdo-chip__text';
				text.textContent = item.label;
				remove.type = 'button';
				remove.className = 'cdo-chip__remove';
				remove.setAttribute( 'data-cdo-index', String( itemIndex ) );
				remove.setAttribute( 'aria-label', sprintf( __( 'Remove %s' ), item.label ) );
				if ( self.input.disabled ) {
					remove.disabled = true;
				}
				chip.appendChild( text );
				chip.appendChild( remove );
				self.chips.appendChild( chip );
			} );
			if ( this.label ) {
				this.chips.setAttribute( 'aria-label', sprintf( __( 'Selected: %s' ), this.label.textContent.trim() ) );
			}
		} else if ( document.activeElement !== this.input || ! this.isOpen ) {
			index = this.selectedIndex();
			this.input.value = index > -1 ? this.items[ index ].label : '';
		}
	};

	/* ------------------------------------------------------------------ field states, counter, clear */

	/*
	 * Show an error or success message under a control and wire aria-invalid / aria-describedby.
	 * state: 'error', 'success' or '' (clear).
	 */
	function setFieldState( control, state, message ) {
		var field = closest( control, '.cdo-field' ) || control.parentNode;
		var messageId = control.getAttribute( 'data-cdo-message-id' );
		var element = messageId ? document.getElementById( messageId ) : null;

		if ( ! element && field ) {
			element = field.querySelector( '.cdo-field__message' );
		}
		if ( ! element && message ) {
			element = document.createElement( 'p' );
			element.className = 'cdo-field__message';
			var anchor = closest( control, '.cdo-input, .cdo-select, .cdo-combobox, .cdo-stepper' ) || control;
			anchor.parentNode.insertBefore( element, anchor.nextSibling );
		}
		if ( element ) {
			if ( ! element.id ) {
				element.id = nextId( 'message' );
			}
			control.setAttribute( 'data-cdo-message-id', element.id );
			element.textContent = message || '';
			element.hidden = ! message;
			element.className = 'cdo-field__message' + ( state ? ' cdo-field__message--' + state : '' );
			if ( message ) {
				addToken( control, 'aria-describedby', element.id );
			} else {
				removeToken( control, 'aria-describedby', element.id );
			}
		}
		if ( field && field.classList ) {
			field.classList.toggle( 'cdo-field--error', 'error' === state );
			field.classList.toggle( 'cdo-field--success', 'success' === state );
		}
		if ( 'error' === state ) {
			control.setAttribute( 'aria-invalid', 'true' );
		} else {
			control.removeAttribute( 'aria-invalid' );
		}
	}

	function initCounter( control ) {
		var max = parseInt( control.getAttribute( 'maxlength' ) || control.getAttribute( 'data-cdo-counter' ), 10 );
		var visible = document.createElement( 'p' );
		var spoken = document.createElement( 'span' );
		var lastSpoken = null;
		if ( ! max || ! once( control, 'counter' ) ) {
			return;
		}
		visible.className = 'cdo-field__counter';
		visible.setAttribute( 'aria-hidden', 'true' );
		spoken.className = 'cdo-sr-only';
		spoken.setAttribute( 'aria-live', 'polite' );
		var anchor = closest( control, '.cdo-input' ) || control;
		anchor.parentNode.insertBefore( visible, anchor.nextSibling );
		visible.parentNode.insertBefore( spoken, visible.nextSibling );

		function update() {
			var used = control.value.length;
			var left = max - used;
			// Isolated left to right, so "12 / 160" does not turn into "160 / 12" on a right-to-left screen.
			visible.textContent = '\u2066' + sprintf( __( '%1$d / %2$d' ), used, max ) + '\u2069';
			visible.classList.toggle( 'is-near', left >= 0 && left <= Math.max( 10, Math.round( max * 0.1 ) ) );
			visible.classList.toggle( 'is-over', left < 0 );
			// Speak only near the limit, and only when the number changes, so typing is not drowned in announcements.
			if ( left <= Math.max( 10, Math.round( max * 0.1 ) ) && left !== lastSpoken ) {
				lastSpoken = left;
				spoken.textContent = left >= 0 ? sprintf( _n( '%d character left.', '%d characters left.', left ), left ) : sprintf( _n( '%d character too many.', '%d characters too many.', -left ), -left );
			}
		}
		control.addEventListener( 'input', update );
		update();
	}

	function initClearable( control ) {
		var button;
		if ( ! once( control, 'clear' ) ) {
			return;
		}
		button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'cdo-input__clear';
		button.setAttribute( 'aria-label', __( 'Clear the field' ) );
		control.parentNode.insertBefore( button, control.nextSibling );

		function update() {
			button.hidden = ! control.value || control.disabled || control.readOnly;
		}
		button.addEventListener( 'click', function () {
			control.value = '';
			fire( control, 'input' );
			fire( control, 'change' );
			control.focus();
		} );
		control.addEventListener( 'input', update );
		update();
	}

	/* ------------------------------------------------------------------ stepper */

	function initStepper( root ) {
		var input = root.querySelector( 'input[type="number"]' );
		var down;
		var up;
		if ( ! input || ! once( root, 'stepper' ) ) {
			return;
		}
		if ( ! input.id ) {
			input.id = nextId( 'number' );
		}
		input.classList.add( 'cdo-stepper__input' );

		function make( direction ) {
			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'cdo-stepper__button cdo-stepper__button--' + direction;
			button.setAttribute( 'aria-label', 'up' === direction ? __( 'Increase' ) : __( 'Decrease' ) );
			button.setAttribute( 'aria-controls', input.id );
			button.addEventListener( 'click', function () {
				step( 'up' === direction ? 1 : -1 );
			} );
			return button;
		}

		function bounds() {
			return {
				min: '' === input.min ? -Infinity : parseFloat( input.min ),
				max: '' === input.max ? Infinity : parseFloat( input.max ),
				step: parseFloat( input.step ) || 1
			};
		}

		function step( direction ) {
			var b = bounds();
			var value = parseFloat( input.value );
			var decimals = ( String( b.step ).split( '.' )[ 1 ] || '' ).length;
			if ( isNaN( value ) ) {
				value = b.min > -Infinity ? b.min : 0;
			} else {
				value += direction * b.step;
			}
			value = Math.max( b.min, Math.min( b.max, value ) );
			input.value = value.toFixed( decimals );
			fire( input, 'input' );
			fire( input, 'change' );
			update();
			announce( input.value );
		}

		function update() {
			var b = bounds();
			var value = parseFloat( input.value );
			down.disabled = ! isNaN( value ) && value <= b.min;
			up.disabled = ! isNaN( value ) && value >= b.max;
		}

		down = make( 'down' );
		up = make( 'up' );
		root.insertBefore( down, input );
		root.appendChild( up );
		input.addEventListener( 'input', update );
		update();
	}

	/* ------------------------------------------------------------------ copy */

	function copyText( text, done ) {
		var area;
		var ok = false;
		if ( window.navigator.clipboard && window.isSecureContext ) {
			window.navigator.clipboard.writeText( text ).then( function () {
				done( true );
			}, function () {
				done( false );
			} );
			return;
		}
		area = document.createElement( 'textarea' );
		area.value = text;
		area.setAttribute( 'readonly', '' );
		area.style.cssText = 'position:fixed;inset-block-start:0;opacity:0;';
		document.body.appendChild( area );
		area.select();
		try {
			ok = document.execCommand( 'copy' );
		} catch ( error ) {
			ok = false;
		}
		document.body.removeChild( area );
		done( ok );
	}

	function initCopy( root ) {
		var source = root.querySelector( '.cdo-copy__input, input, textarea, code' );
		var button = root.querySelector( '.cdo-copy__button, [data-cdo-copy-button]' );
		var status = root.querySelector( '.cdo-copy__status' );
		if ( ! source || ! button || ! once( root, 'copy' ) ) {
			return;
		}
		if ( ! status ) {
			status = document.createElement( 'p' );
			status.className = 'cdo-copy__status';
			root.appendChild( status );
		}
		status.setAttribute( 'role', 'status' );
		button.hidden = false;
		button.addEventListener( 'click', function () {
			var text = undefined !== source.value ? source.value : source.textContent;
			copyText( text, function ( ok ) {
				status.classList.toggle( 'is-error', ! ok );
				if ( ok ) {
					status.textContent = root.getAttribute( 'data-cdo-copied' ) || __( 'Copied. You can paste it now.' );
				} else {
					status.textContent = __( 'Copying did not work. The text is selected: press Ctrl+C (Cmd+C on a Mac).' );
					if ( source.select ) {
						source.focus();
						source.select();
					}
				}
				window.clearTimeout( status.cdoTimer );
				status.cdoTimer = window.setTimeout( function () {
					status.textContent = '';
				}, 6000 );
			} );
		} );
	}

	/* ------------------------------------------------------------------ tooltip and popover */

	/*
	 * Keep a floating bubble inside the screen: slide it sideways until it fits (8 px from each edge), and open it on
	 * the other side (below/above) when there is no room. Works the same left to right and right to left.
	 */
	function placeFloating( host, bubble, flipClass, flipWhenTop ) {
		var rect;
		var shift = 0;
		var width = document.documentElement.clientWidth;
		host.classList.remove( flipClass );
		bubble.style.transform = '';
		rect = bubble.getBoundingClientRect();
		if ( ! rect.width ) {
			return;
		}
		if ( rect.right > width - 8 ) {
			shift = width - 8 - rect.right;
		}
		if ( rect.left + shift < 8 ) {
			shift = 8 - rect.left;
		}
		if ( shift ) {
			bubble.style.transform = 'translateX(' + Math.round( shift ) + 'px)';
		}
		if ( flipWhenTop ? rect.top < 40 : rect.bottom > window.innerHeight - 8 ) {
			host.classList.add( flipClass );
		}
	}

	function initTooltip( root ) {
		var toggle = root.querySelector( '.cdo-tooltip__toggle' );
		var bubble = root.querySelector( '.cdo-tooltip__bubble' );
		if ( ! toggle || ! bubble || ! once( root, 'tooltip' ) ) {
			return;
		}
		if ( ! bubble.id ) {
			bubble.id = nextId( 'tooltip' );
		}
		bubble.setAttribute( 'role', 'tooltip' );
		addToken( toggle, 'aria-describedby', bubble.id );

		function show() {
			root.classList.remove( 'is-dismissed' );
			placeFloating( root, bubble, 'cdo-tooltip--below', true );
		}
		root.addEventListener( 'mouseenter', show );
		toggle.addEventListener( 'focus', show );
		toggle.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			root.classList.toggle( 'is-dismissed' );
		} );
		root.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key || 'Esc' === event.key ) {
				root.classList.add( 'is-dismissed' );
				event.stopPropagation();
			}
		} );
		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && root.matches( ':hover' ) ) {
				root.classList.add( 'is-dismissed' );
			}
		} );
	}

	function initPopover( details ) {
		var summary = details.querySelector( 'summary' );
		var panel = details.querySelector( '.cdo-popover__panel' );
		if ( ! summary || ! panel || ! once( details, 'popover' ) ) {
			return;
		}
		if ( ! panel.id ) {
			panel.id = nextId( 'popover' );
		}
		summary.setAttribute( 'aria-controls', panel.id );

		details.addEventListener( 'toggle', function () {
			if ( details.open ) {
				// One "What is this?" open at a time.
				all( document, 'details.cdo-popover[open]' ).forEach( function ( other ) {
					if ( other !== details ) {
						other.open = false;
					}
				} );
				placeFloating( details, panel, 'cdo-popover--up', false );
			}
		} );
		details.addEventListener( 'keydown', function ( event ) {
			if ( ( 'Escape' === event.key || 'Esc' === event.key ) && details.open ) {
				details.open = false;
				summary.focus();
				event.stopPropagation();
			}
		} );
		document.addEventListener( 'click', function ( event ) {
			if ( details.open && ! details.contains( event.target ) ) {
				details.open = false;
			}
		} );
	}

	/* ------------------------------------------------------------------ dialog and confirm */

	var dialogStack = [];

	function trapKeydown( event ) {
		var current = dialogStack[ dialogStack.length - 1 ];
		var items;
		var first;
		var last;
		if ( ! current ) {
			return;
		}
		if ( 'Escape' === event.key || 'Esc' === event.key ) {
			if ( 'false' !== current.dialog.getAttribute( 'data-cdo-escape' ) ) {
				event.preventDefault();
				closeDialog( current.dialog, 'escape' );
			}
			return;
		}
		if ( 'Tab' !== event.key ) {
			return;
		}
		items = focusables( current.dialog.querySelector( '.cdo-dialog__panel' ) || current.dialog );
		if ( ! items.length ) {
			event.preventDefault();
			return;
		}
		first = items[ 0 ];
		last = items[ items.length - 1 ];
		if ( event.shiftKey && ( document.activeElement === first || ! current.dialog.contains( document.activeElement ) ) ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && ( document.activeElement === last || ! current.dialog.contains( document.activeElement ) ) ) {
			event.preventDefault();
			first.focus();
		}
	}

	/*
	 * Open a .cdo-dialog: focus moves inside ([data-cdo-autofocus] or the first control), Tab stays inside,
	 * Escape and the backdrop close it, and focus returns to the element that opened it.
	 */
	function openDialog( dialog, opener ) {
		var target;
		var title;
		if ( ! dialog ) {
			return;
		}
		dialog.setAttribute( 'role', dialog.getAttribute( 'role' ) || 'dialog' );
		dialog.setAttribute( 'aria-modal', 'true' );
		if ( ! dialog.getAttribute( 'aria-labelledby' ) ) {
			title = dialog.querySelector( '.cdo-dialog__header h2, .cdo-dialog__header h3, .cdo-dialog__title' );
			if ( title ) {
				if ( ! title.id ) {
					title.id = nextId( 'dialog-title' );
				}
				dialog.setAttribute( 'aria-labelledby', title.id );
			}
		}
		dialog.hidden = false;
		dialogStack.push( { dialog: dialog, opener: opener || document.activeElement } );
		document.documentElement.classList.add( 'cdo-dialog-open' );
		if ( 1 === dialogStack.length ) {
			document.addEventListener( 'keydown', trapKeydown, true );
		}
		target = dialog.querySelector( '[data-cdo-autofocus]' ) || focusables( dialog.querySelector( '.cdo-dialog__body' ) || dialog )[ 0 ] || focusables( dialog )[ 0 ];
		if ( target ) {
			target.focus();
		}
		emit( dialog, 'cdo:dialog-open', {} );
	}

	function closeDialog( dialog, reason ) {
		var index = -1;
		var entry;
		dialogStack.forEach( function ( item, position ) {
			if ( item.dialog === dialog ) {
				index = position;
			}
		} );
		if ( index === -1 ) {
			dialog.hidden = true;
			return;
		}
		entry = dialogStack.splice( index, 1 )[ 0 ];
		dialog.hidden = true;
		if ( ! dialogStack.length ) {
			document.documentElement.classList.remove( 'cdo-dialog-open' );
			document.removeEventListener( 'keydown', trapKeydown, true );
		}
		if ( entry.opener && entry.opener.focus && document.contains( entry.opener ) ) {
			entry.opener.focus();
		}
		emit( dialog, 'cdo:dialog-close', { reason: reason || '' } );
	}

	/*
	 * A confirm dialog built on the fly. options: title, message, confirm, cancel, danger (bool).
	 * Calls callback( true|false ) and returns a Promise when the browser has one.
	 */
	function confirmDialog( options, callback ) {
		var settings = extend( { title: __( 'Are you sure?' ), message: '', confirm: __( 'Continue' ), cancel: __( 'Cancel' ), danger: false }, options );
		var host = document.createElement( 'div' );
		var dialog = document.createElement( 'div' );
		var panel = document.createElement( 'div' );
		var header = document.createElement( 'div' );
		var title = document.createElement( 'h2' );
		var body = document.createElement( 'div' );
		var footer = document.createElement( 'div' );
		var cancel = document.createElement( 'button' );
		var ok = document.createElement( 'button' );
		var titleId = nextId( 'confirm-title' );
		var bodyId = nextId( 'confirm-body' );
		var answered = false;
		var resolver = null;
		var promise = null;
		var opener = document.activeElement;

		if ( 'function' === typeof window.Promise ) {
			promise = new window.Promise( function ( resolve ) {
				resolver = resolve;
			} );
		}

		host.className = 'cdo-ui';
		dialog.className = 'cdo-dialog cdo-dialog--confirm' + ( settings.danger ? ' cdo-dialog--danger' : '' );
		dialog.setAttribute( 'role', 'alertdialog' );
		dialog.setAttribute( 'aria-labelledby', titleId );
		dialog.setAttribute( 'aria-describedby', bodyId );
		dialog.hidden = true;
		panel.className = 'cdo-dialog__panel';
		header.className = 'cdo-dialog__header';
		title.className = 'cdo-dialog__title';
		title.id = titleId;
		title.textContent = settings.title;
		body.className = 'cdo-dialog__body';
		body.id = bodyId;
		String( settings.message ).split( /\n{2,}/ ).forEach( function ( paragraph ) {
			var p = document.createElement( 'p' );
			p.textContent = paragraph;
			body.appendChild( p );
		} );
		footer.className = 'cdo-dialog__footer';
		cancel.type = 'button';
		cancel.className = 'button';
		cancel.textContent = settings.cancel;
		cancel.setAttribute( 'data-cdo-autofocus', '' );
		ok.type = 'button';
		ok.className = settings.danger ? 'button cdo-button--danger' : 'button button-primary';
		ok.textContent = settings.confirm;

		header.appendChild( title );
		footer.appendChild( cancel );
		footer.appendChild( ok );
		panel.appendChild( header );
		panel.appendChild( body );
		panel.appendChild( footer );
		dialog.appendChild( panel );
		host.appendChild( dialog );
		document.body.appendChild( host );

		function answer( value ) {
			if ( answered ) {
				return;
			}
			answered = true;
			closeDialog( dialog, value ? 'confirm' : 'cancel' );
			window.setTimeout( function () {
				if ( host.parentNode ) {
					host.parentNode.removeChild( host );
				}
			}, 0 );
			if ( callback ) {
				callback( value );
			}
			if ( resolver ) {
				resolver( value );
			}
		}

		cancel.addEventListener( 'click', function () {
			answer( false );
		} );
		ok.addEventListener( 'click', function () {
			answer( true );
		} );
		dialog.addEventListener( 'cdo:dialog-close', function () {
			answer( false );
		} );
		dialog.addEventListener( 'mousedown', function ( event ) {
			if ( event.target === dialog ) {
				answer( false );
			}
		} );
		openDialog( dialog, opener );
		return promise;
	}

	/* ------------------------------------------------------------------ busy button */

	/* CDOUI.busy( button, true, 'Saving…' ) shows a spinner and the text; CDOUI.busy( button, false ) restores it. */
	function busy( button, on, text ) {
		if ( ! button ) {
			return;
		}
		if ( on ) {
			if ( undefined === button.cdoLabel ) {
				button.cdoLabel = 'INPUT' === button.tagName ? button.value : button.innerHTML;
			}
			button.classList.add( 'cdo-is-busy' );
			button.setAttribute( 'aria-disabled', 'true' );
			if ( 'INPUT' === button.tagName ) {
				button.value = text || __( 'Working…' );
			} else {
				button.textContent = text || __( 'Working…' );
			}
			announce( text || __( 'Working…' ) );
		} else {
			button.classList.remove( 'cdo-is-busy' );
			button.removeAttribute( 'aria-disabled' );
			if ( undefined !== button.cdoLabel ) {
				if ( 'INPUT' === button.tagName ) {
					button.value = button.cdoLabel;
				} else {
					button.innerHTML = button.cdoLabel;
				}
				button.cdoLabel = undefined;
			}
			if ( text ) {
				announce( text );
			}
		}
	}

	/* ------------------------------------------------------------------ tabs */

	function initTabs( root ) {
		var tabs = all( root, '.cdo-tabs__tab' ).filter( function ( tab ) {
			return closest( tab, '.cdo-tabs' ) === root;
		} );
		var list = root.querySelector( '.cdo-tabs__list' );
		var panels = [];
		var selected = 0;
		var stored;
		var key = root.getAttribute( 'data-cdo-tabs' );

		if ( ! tabs.length || ! list || ! once( root, 'tabs' ) ) {
			return;
		}
		list.setAttribute( 'role', 'tablist' );
		all( list, 'li' ).forEach( function ( item ) {
			item.setAttribute( 'role', 'presentation' );
		} );

		tabs.forEach( function ( tab, index ) {
			var href = tab.getAttribute( 'href' ) || '';
			var panel = href.charAt( 0 ) === '#' ? document.getElementById( href.slice( 1 ) ) : document.getElementById( tab.getAttribute( 'aria-controls' ) );
			if ( ! tab.id ) {
				tab.id = nextId( 'tab' );
			}
			tab.setAttribute( 'role', 'tab' );
			if ( panel ) {
				tab.setAttribute( 'aria-controls', panel.id );
				panel.setAttribute( 'role', 'tabpanel' );
				panel.setAttribute( 'aria-labelledby', tab.id );
				panel.tabIndex = 0;
			}
			panels.push( panel );
			if ( 'true' === tab.getAttribute( 'aria-selected' ) || ( window.location.hash && window.location.hash === href ) ) {
				selected = index;
			}
		} );

		if ( key && 'true' !== tabs[ selected ].getAttribute( 'aria-selected' ) ) {
			try {
				stored = window.sessionStorage.getItem( 'cdo-tabs:' + key );
			} catch ( error ) {
				stored = null;
			}
			tabs.forEach( function ( tab, index ) {
				if ( stored && stored === tab.getAttribute( 'aria-controls' ) ) {
					selected = index;
				}
			} );
		}

		function activate( index, focus ) {
			tabs.forEach( function ( tab, position ) {
				var on = position === index;
				tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				tab.tabIndex = on ? 0 : -1;
				if ( panels[ position ] ) {
					panels[ position ].hidden = ! on;
				}
			} );
			if ( focus ) {
				tabs[ index ].focus();
			}
			if ( key ) {
				try {
					window.sessionStorage.setItem( 'cdo-tabs:' + key, tabs[ index ].getAttribute( 'aria-controls' ) || '' );
				} catch ( error ) {
					// Storage blocked: the first tab opens next time.
				}
			}
			emit( root, 'cdo:tab', { index: index, tab: tabs[ index ] } );
		}

		list.addEventListener( 'click', function ( event ) {
			var tab = closest( event.target, '[role="tab"]' );
			if ( tab ) {
				event.preventDefault();
				activate( tabs.indexOf( tab ), true );
			}
		} );
		list.addEventListener( 'keydown', function ( event ) {
			var index = tabs.indexOf( document.activeElement );
			var rtl = isRtl( root );
			var next = -1;
			if ( index === -1 ) {
				return;
			}
			if ( 'ArrowRight' === event.key || 'Right' === event.key ) {
				next = rtl ? index - 1 : index + 1;
			} else if ( 'ArrowLeft' === event.key || 'Left' === event.key ) {
				next = rtl ? index + 1 : index - 1;
			} else if ( 'Home' === event.key ) {
				next = 0;
			} else if ( 'End' === event.key ) {
				next = tabs.length - 1;
			} else {
				return;
			}
			event.preventDefault();
			activate( ( next + tabs.length ) % tabs.length, true );
		} );

		root.classList.add( 'is-ready' );
		activate( selected, false );
	}

	/* ------------------------------------------------------------------ wizard */

	/*
	 * Setup wizard: one panel at a time, a step indicator, Back / Next / Skip / Finish. The current step is kept in
	 * localStorage under data-cdo-wizard (the key), so a person who leaves the screen comes back to the same step.
	 * Events on the wizard element: cdo:wizard-step { index, total }, cdo:wizard-finish, cdo:wizard-skip.
	 */
	function initWizard( root ) {
		var panels = all( root, '[data-cdo-wizard-panel]' );
		var steps = all( root, '.cdo-wizard__step' );
		var back = root.querySelector( '[data-cdo-wizard-back]' );
		var next = root.querySelector( '[data-cdo-wizard-next]' );
		var finish = root.querySelector( '[data-cdo-wizard-finish]' );
		var skip = root.querySelector( '[data-cdo-wizard-skip]' );
		var count = root.querySelector( '.cdo-wizard__count' );
		var key = root.getAttribute( 'data-cdo-wizard' );
		var storageKey = key ? 'cdo-wizard:' + key : '';
		var current = 0;
		var stored;

		if ( ! panels.length || ! once( root, 'wizard' ) ) {
			return;
		}
		if ( ! count ) {
			count = document.createElement( 'p' );
			count.className = 'cdo-wizard__count';
			panels[ 0 ].parentNode.insertBefore( count, panels[ 0 ] );
		}
		count.setAttribute( 'aria-live', 'polite' );

		if ( storageKey ) {
			try {
				stored = parseInt( window.localStorage.getItem( storageKey ), 10 );
			} catch ( error ) {
				stored = NaN;
			}
			if ( ! isNaN( stored ) && stored >= 0 && stored < panels.length ) {
				current = stored;
			}
		}
		if ( root.getAttribute( 'data-cdo-wizard-start' ) ) {
			current = Math.min( panels.length - 1, Math.max( 0, parseInt( root.getAttribute( 'data-cdo-wizard-start' ), 10 ) || 0 ) );
		}

		function save() {
			if ( ! storageKey ) {
				return;
			}
			try {
				window.localStorage.setItem( storageKey, String( current ) );
			} catch ( error ) {
				// Storage blocked: the wizard starts at the first step next time.
			}
		}

		function show( index, focus ) {
			var title;
			current = index;
			panels.forEach( function ( panel, position ) {
				panel.hidden = position !== index;
			} );
			steps.forEach( function ( step, position ) {
				step.classList.toggle( 'is-done', position < index );
				if ( position === index ) {
					step.setAttribute( 'aria-current', 'step' );
				} else {
					step.removeAttribute( 'aria-current' );
				}
			} );
			count.textContent = sprintf( __( 'Step %1$d of %2$d' ), index + 1, panels.length );
			if ( back ) {
				back.hidden = 0 === index;
			}
			if ( next ) {
				next.hidden = index === panels.length - 1;
			}
			if ( finish ) {
				finish.hidden = index !== panels.length - 1;
			}
			save();
			if ( focus ) {
				title = panels[ index ].querySelector( '.cdo-wizard__title, h2, h3' );
				if ( title ) {
					if ( ! title.hasAttribute( 'tabindex' ) ) {
						title.setAttribute( 'tabindex', '-1' );
					}
					title.focus();
				}
			}
			emit( root, 'cdo:wizard-step', { index: index, total: panels.length } );
		}

		/* A step with a required field left empty does not move on; the first problem gets the focus. */
		function valid( panel ) {
			var invalid = null;
			all( panel, 'input, select, textarea' ).some( function ( control ) {
				if ( control.willValidate && ! control.checkValidity() ) {
					invalid = control;
					return true;
				}
				return false;
			} );
			if ( invalid ) {
				var shown = invalid.cdoCombobox ? invalid.cdoCombobox.input : invalid;
				setFieldState( shown, 'error', invalid.getAttribute( 'data-cdo-required-message' ) || invalid.validationMessage );
				shown.focus();
				announce( __( 'Please fix the marked field, then go on.' ), 'assertive' );
				return false;
			}
			return true;
		}

		if ( back ) {
			back.addEventListener( 'click', function () {
				if ( current > 0 ) {
					show( current - 1, true );
				}
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				if ( valid( panels[ current ] ) && current < panels.length - 1 ) {
					show( current + 1, true );
				}
			} );
		}
		if ( finish ) {
			finish.addEventListener( 'click', function ( event ) {
				if ( ! valid( panels[ current ] ) ) {
					event.preventDefault();
					return;
				}
				if ( storageKey ) {
					try {
						window.localStorage.removeItem( storageKey );
					} catch ( error ) {
						// Nothing to clear.
					}
				}
				if ( ! emit( root, 'cdo:wizard-finish', { index: current, total: panels.length } ) ) {
					event.preventDefault();
				}
			} );
		}
		if ( skip && 'A' !== skip.tagName ) {
			skip.addEventListener( 'click', function () {
				emit( root, 'cdo:wizard-skip', { index: current, total: panels.length } );
			} );
		}
		root.classList.add( 'is-ready' );
		show( current, false );
	}

	/* ------------------------------------------------------------------ notices, accordion, table cards */

	function initDismissible( notice ) {
		var button = notice.querySelector( '.cdo-notice__dismiss' );
		if ( ! once( notice, 'dismiss' ) ) {
			return;
		}
		if ( ! button ) {
			button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'cdo-notice__dismiss';
			button.setAttribute( 'aria-label', __( 'Dismiss this notice' ) );
			notice.appendChild( button );
		}
		button.addEventListener( 'click', function () {
			var after;
			if ( ! emit( notice, 'cdo:dismiss', { key: notice.getAttribute( 'data-cdo-dismissible' ) || '' } ) ) {
				return;
			}
			// Focus goes to the next thing on the page, so keyboard users do not land at the top.
			after = focusables( document ).filter( function ( element ) {
				return ! notice.contains( element ) && ( notice.compareDocumentPosition( element ) & 4 );
			} )[ 0 ];
			notice.hidden = true;
			announce( __( 'Notice dismissed.' ) );
			if ( after ) {
				after.focus();
			}
		} );
	}

	function initAccordion( root ) {
		var items = all( root, 'details' );
		if ( ! once( root, 'accordion' ) ) {
			return;
		}
		items.forEach( function ( item ) {
			item.addEventListener( 'toggle', function () {
				if ( item.open ) {
					items.forEach( function ( other ) {
						if ( other !== item ) {
							other.open = false;
						}
					} );
				}
			} );
		} );
	}

	/* Fill data-label from the header row and restore table roles, which display:block takes away in some browsers. */
	function initTableCards( table ) {
		var headers = all( table, 'thead th, thead td' ).map( function ( cell ) {
			return cell.textContent.trim();
		} );
		if ( ! once( table, 'cards' ) ) {
			return;
		}
		table.setAttribute( 'role', 'table' );
		all( table, 'thead, tbody, tfoot' ).forEach( function ( group ) {
			group.setAttribute( 'role', 'rowgroup' );
		} );
		all( table, 'tr' ).forEach( function ( row ) {
			var column = 0;
			row.setAttribute( 'role', 'row' );
			Array.prototype.forEach.call( row.children, function ( cell ) {
				var inHead = 'THEAD' === row.parentNode.tagName;
				if ( inHead ) {
					cell.setAttribute( 'role', 'columnheader' );
				} else {
					cell.setAttribute( 'role', 'TH' === cell.tagName ? 'rowheader' : 'cell' );
					if ( ! cell.hasAttribute( 'data-label' ) && headers[ column ] ) {
						cell.setAttribute( 'data-label', headers[ column ] );
					}
				}
				column += cell.colSpan || 1;
			} );
		} );
	}

	/* ------------------------------------------------------------------ document-level behaviour */

	function onDocumentClick( event ) {
		var opener = closest( event.target, '[data-cdo-dialog-open]' );
		var closer = closest( event.target, '[data-cdo-dialog-close]' );
		var confirmTrigger = closest( event.target, '[data-cdo-confirm]' );
		var dialog;

		if ( opener ) {
			dialog = document.getElementById( opener.getAttribute( 'data-cdo-dialog-open' ) );
			if ( dialog ) {
				event.preventDefault();
				openDialog( dialog, opener );
			}
			return;
		}
		if ( closer ) {
			dialog = closest( closer, '.cdo-dialog' );
			if ( dialog ) {
				event.preventDefault();
				closeDialog( dialog, 'button' );
			}
			return;
		}
		if ( confirmTrigger && 'FORM' !== confirmTrigger.tagName && ! confirmTrigger.cdoConfirmed ) {
			event.preventDefault();
			confirmDialog( {
				title: confirmTrigger.getAttribute( 'data-cdo-confirm-title' ) || undefined,
				message: confirmTrigger.getAttribute( 'data-cdo-confirm' ),
				confirm: confirmTrigger.getAttribute( 'data-cdo-confirm-ok' ) || undefined,
				cancel: confirmTrigger.getAttribute( 'data-cdo-confirm-cancel' ) || undefined,
				danger: confirmTrigger.hasAttribute( 'data-cdo-confirm-danger' )
			}, function ( yes ) {
				if ( ! yes ) {
					return;
				}
				confirmTrigger.cdoConfirmed = true;
				if ( confirmTrigger.form && 'submit' === confirmTrigger.type ) {
					if ( confirmTrigger.form.requestSubmit ) {
						confirmTrigger.form.requestSubmit( confirmTrigger );
					} else {
						confirmTrigger.click();
					}
				} else if ( confirmTrigger.href ) {
					window.location.href = confirmTrigger.href;
				} else {
					confirmTrigger.click();
				}
				confirmTrigger.cdoConfirmed = false;
			} );
			return;
		}

		// A click on the dark backdrop of a dialog closes it.
		if ( event.target.classList && event.target.classList.contains( 'cdo-dialog' ) && ! event.target.hidden && 'false' !== event.target.getAttribute( 'data-cdo-backdrop' ) ) {
			closeDialog( event.target, 'backdrop' );
		}
	}

	function onDocumentSubmit( event ) {
		var form = event.target;
		if ( ! form.hasAttribute || ! form.hasAttribute( 'data-cdo-confirm' ) || form.cdoConfirmed ) {
			return;
		}
		event.preventDefault();
		confirmDialog( {
			title: form.getAttribute( 'data-cdo-confirm-title' ) || undefined,
			message: form.getAttribute( 'data-cdo-confirm' ),
			confirm: form.getAttribute( 'data-cdo-confirm-ok' ) || undefined,
			cancel: form.getAttribute( 'data-cdo-confirm-cancel' ) || undefined,
			danger: form.hasAttribute( 'data-cdo-confirm-danger' )
		}, function ( yes ) {
			if ( yes ) {
				form.cdoConfirmed = true;
				form.submit();
			}
		} );
	}

	/* ------------------------------------------------------------------ init */

	function init( root ) {
		root = root || document;
		all( root, 'select[data-cdo-combobox]' ).forEach( function ( select ) {
			if ( once( select, 'combobox' ) ) {
				new Combobox( select ); // eslint-disable-line no-new
			}
		} );
		all( root, '[data-cdo-counter]' ).forEach( initCounter );
		all( root, '[data-cdo-clearable]' ).forEach( initClearable );
		all( root, '[data-cdo-stepper]' ).forEach( initStepper );
		all( root, '[data-cdo-copy]' ).forEach( initCopy );
		all( root, '.cdo-tooltip' ).forEach( initTooltip );
		all( root, 'details.cdo-popover' ).forEach( initPopover );
		all( root, '[data-cdo-tabs]' ).forEach( initTabs );
		all( root, '[data-cdo-wizard]' ).forEach( initWizard );
		all( root, '[data-cdo-dismissible]' ).forEach( initDismissible );
		all( root, '[data-cdo-accordion="single"]' ).forEach( initAccordion );
		all( root, 'table.cdo-table--cards' ).forEach( initTableCards );
		// A code block that scrolls sideways must be reachable with the keyboard (WCAG 2.1.1).
		all( root, 'pre.cdo-code:not([tabindex])' ).forEach( function ( pre ) {
			pre.tabIndex = 0;
		} );
	}

	function start() {
		document.documentElement.classList.add( 'cdo-js' );
		document.addEventListener( 'click', onDocumentClick );
		document.addEventListener( 'submit', onDocumentSubmit );
		init( document );
	}

	extend( config, window.cdoUiConfig );

	window.CDOUI = {
		version: VERSION,
		config: function ( options ) {
			extend( config, options );
			return extend( {}, config );
		},
		init: init,
		__: __,
		sprintf: sprintf,
		announce: announce,
		combobox: function ( select ) {
			if ( select.cdoCombobox ) {
				return select.cdoCombobox;
			}
			select.setAttribute( 'data-cdo-ready-combobox', '1' );
			return new Combobox( select );
		},
		dialog: { open: openDialog, close: closeDialog },
		confirm: confirmDialog,
		busy: busy,
		field: {
			setError: function ( control, message ) {
				setFieldState( control.cdoCombobox ? control.cdoCombobox.input : control, 'error', message );
			},
			setSuccess: function ( control, message ) {
				setFieldState( control.cdoCombobox ? control.cdoCombobox.input : control, 'success', message );
			},
			clear: function ( control ) {
				setFieldState( control.cdoCombobox ? control.cdoCombobox.input : control, '', '' );
			}
		},
		copy: copyText
	};

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}( window, document ) );
