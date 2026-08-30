/**
 * Pricing demo: the monthly and annual states, so the designer can see what
 * happens to the number when the toggle changes.
 */
( function () {
	'use strict';

	var buttons = document.querySelectorAll( '.switch button' );
	var cycle = 'monthly';

	function paint() {
		document.querySelectorAll( '.amount' ).forEach( function ( node ) {
			var base = parseFloat( node.getAttribute( 'data-base' ) ) || 0;

			node.textContent = cycle === 'annual'
				? String( Math.round( base * 12 * 0.8 ) )
				: String( base );
		} );

		document.querySelectorAll( '.price small' ).forEach( function ( node ) {
			node.textContent = cycle === 'annual' ? '/year' : '/month';
		} );
	}

	buttons.forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			cycle = button.getAttribute( 'data-cycle' );

			buttons.forEach( function ( other ) {
				other.classList.toggle( 'is-active', other === button );
			} );

			paint();
		} );
	} );

	window.dpgTemplate = {
		update: function ( state ) {
			// Keep the featured plan's base price in step with the editor.
			var price = parseFloat( ( state.content || {} ).price );
			var node = document.querySelector( '.plan--featured .amount' );

			if ( node && ! isNaN( price ) ) {
				node.setAttribute( 'data-base', String( price ) );
			}

			paint();
		}
	};
}() );
