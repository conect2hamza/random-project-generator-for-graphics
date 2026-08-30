/**
 * Social card demo: switch between the real platform aspect ratios so a
 * headline can be checked at every size it will actually be published at.
 */
( function () {
	'use strict';

	var card = document.querySelector( '.card' );
	var chips = document.querySelectorAll( '.chip' );

	chips.forEach( function ( chip ) {
		chip.addEventListener( 'click', function () {
			chips.forEach( function ( other ) {
				other.classList.toggle( 'is-active', other === chip );
			} );

			card.setAttribute( 'data-format', chip.getAttribute( 'data-format' ) );
		} );
	} );
}() );
