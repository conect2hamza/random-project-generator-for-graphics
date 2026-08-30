/**
 * Landing page demo behaviour.
 *
 * Templates may expose window.dpgTemplate.update(state) to react to editor
 * changes beyond text and colour. This one keeps the price line grammatical
 * whatever the editor types into the price field.
 */
( function () {
	'use strict';

	window.dpgTemplate = {
		update: function ( state ) {
			var price = ( ( state.content || {} ).price || '' ).trim();
			var node = document.querySelector( '[data-dpg-field="price"]' );

			if ( node && price && ! /^[^0-9]/.test( price ) ) {
				node.textContent = '$' + price;
			}
		}
	};

	document.querySelectorAll( '.nav__links a' ).forEach( function ( link ) {
		link.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			var target = document.querySelector( link.getAttribute( 'href' ) );

			if ( target ) {
				target.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		} );
	} );
}() );
