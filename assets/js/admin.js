/**
 * Answers — admin enhancements (progressive, dependency-free).
 *
 * 1. Repeater: "Add question" clones a hidden <template> row, re-indexes the
 *    field names so each row posts as its own array entry, and enables the
 *    cloned inputs (template inputs are disabled so they never post). "Remove"
 *    deletes a row. Works on the product FAQ tab and the FAQ-set editor.
 * 2. Inline help: each "?" button is wired to an accessible popover; where the
 *    native Popover API is unavailable a keyboard-operable fallback is used.
 *
 * Loaded with `defer`. With JS disabled, existing rows remain editable and all
 * settings still save.
 */
( function () {
	'use strict';

	/* ---- Repeater ---------------------------------------------------- */

	function reindex( repeater ) {
		var rows = repeater.querySelectorAll(
			'[data-answers-rows] > [data-answers-row]'
		);
		rows.forEach( function ( row, index ) {
			row.querySelectorAll( '[name]' ).forEach( function ( field ) {
				field.name = field.name.replace(
					/\[(?:\d+|__INDEX__)\]/,
					'[' + index + ']'
				);
			} );
		} );
	}

	function enableRow( row ) {
		row.querySelectorAll( '[disabled]' ).forEach( function ( field ) {
			field.disabled = false;
		} );
	}

	function addRow( repeater ) {
		var template = repeater.querySelector( '[data-answers-template]' );
		var rows = repeater.querySelector( '[data-answers-rows]' );

		if ( ! template || ! rows ) {
			return;
		}

		var fragment = template.content.cloneNode( true );
		rows.appendChild( fragment );

		var added = rows.lastElementChild;
		if ( added ) {
			enableRow( added );
			reindex( repeater );
			var firstInput = added.querySelector( 'input, textarea' );
			if ( firstInput ) {
				firstInput.focus();
			}
		}
	}

	function initRepeater( repeater ) {
		repeater.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-answers-add]' ) ) {
				event.preventDefault();
				addRow( repeater );
				return;
			}

			var remove = event.target.closest( '[data-answers-remove]' );
			if ( remove ) {
				event.preventDefault();
				var row = remove.closest( '[data-answers-row]' );
				if ( row ) {
					row.remove();
					reindex( repeater );
				}
			}
		} );
	}

	document
		.querySelectorAll( '[data-answers-repeater]' )
		.forEach( initRepeater );

	/* ---- Inline help popovers ---------------------------------------- */

	var supportsPopover =
		typeof HTMLElement !== 'undefined' &&
		HTMLElement.prototype.hasOwnProperty( 'popover' );

	if ( supportsPopover ) {
		return;
	}

	function tipFor( button ) {
		var id = button.getAttribute( 'aria-describedby' );
		return id ? document.getElementById( id ) : null;
	}

	function closeAll( except ) {
		document
			.querySelectorAll( '.answers-help[aria-expanded="true"]' )
			.forEach( function ( button ) {
				if ( button === except ) {
					return;
				}
				button.setAttribute( 'aria-expanded', 'false' );
				var tip = tipFor( button );
				if ( tip ) {
					tip.hidden = true;
				}
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.answers-help' );

		if ( ! button ) {
			if ( ! event.target.closest( '.answers-tip' ) ) {
				closeAll( null );
			}
			return;
		}

		var tip = tipFor( button );
		if ( ! tip ) {
			return;
		}

		var open = button.getAttribute( 'aria-expanded' ) === 'true';
		closeAll( button );
		button.setAttribute( 'aria-expanded', String( ! open ) );
		tip.hidden = open;
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Escape' ) {
			closeAll( null );
		}
	} );
} )();
