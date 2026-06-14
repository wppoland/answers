/**
 * Answers — storefront FAQ accordion behaviour (progressive, dependency-free).
 *
 * Each question is a <button aria-expanded aria-controls> controlling a region
 * panel. Clicking (or Enter/Space, native to <button>) toggles the panel and
 * keeps aria-expanded in sync. A height transition is applied only when motion
 * is allowed; otherwise panels snap open/closed. With JS disabled the markup
 * remains valid and the answer text is reachable (panels are simply collapsed).
 */
( function () {
	'use strict';

	var reduceMotion =
		window.matchMedia &&
		window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function panelFor( button ) {
		var id = button.getAttribute( 'aria-controls' );
		return id ? document.getElementById( id ) : null;
	}

	function open( button, panel ) {
		button.setAttribute( 'aria-expanded', 'true' );
		panel.hidden = false;

		if ( reduceMotion ) {
			return;
		}

		panel.classList.add( 'is-open' );
		var target = panel.scrollHeight;
		panel.style.blockSize = '0px';
		// Force reflow so the transition runs from 0.
		void panel.offsetHeight;
		panel.style.blockSize = target + 'px';

		panel.addEventListener( 'transitionend', function done() {
			panel.style.blockSize = 'auto';
			panel.removeEventListener( 'transitionend', done );
		} );
	}

	function close( button, panel ) {
		button.setAttribute( 'aria-expanded', 'false' );

		if ( reduceMotion ) {
			panel.hidden = true;
			return;
		}

		var current = panel.scrollHeight;
		panel.style.blockSize = current + 'px';
		void panel.offsetHeight;
		panel.classList.remove( 'is-open' );
		panel.style.blockSize = '0px';

		panel.addEventListener( 'transitionend', function done() {
			panel.hidden = true;
			panel.style.blockSize = '';
			panel.removeEventListener( 'transitionend', done );
		} );
	}

	function init( root ) {
		if ( ! reduceMotion ) {
			root.classList.add( 'answers-faq--animated' );
		}

		root.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '.answers-faq__trigger' );

			if ( ! button || ! root.contains( button ) ) {
				return;
			}

			var panel = panelFor( button );

			if ( ! panel ) {
				return;
			}

			if ( button.getAttribute( 'aria-expanded' ) === 'true' ) {
				close( button, panel );
			} else {
				open( button, panel );
			}
		} );

		// Sync any server-rendered "first item open" panels with the animated class.
		if ( ! reduceMotion ) {
			root.querySelectorAll(
				'.answers-faq__trigger[aria-expanded="true"]'
			).forEach( function ( button ) {
				var panel = panelFor( button );
				if ( panel ) {
					panel.classList.add( 'is-open' );
					panel.style.blockSize = 'auto';
				}
			} );
		}
	}

	document
		.querySelectorAll( '[data-answers-faq]' )
		.forEach( init );
} )();
