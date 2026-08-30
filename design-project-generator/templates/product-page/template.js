/**
 * Product page demo: gallery switching and the added-to-basket state, which
 * is the state most product briefs forget to ask for.
 */
( function () {
	'use strict';

	var shot = document.querySelector( '.shot' );
	var thumbs = document.querySelectorAll( '.thumbs button' );

	thumbs.forEach( function ( thumb ) {
		thumb.addEventListener( 'click', function () {
			thumbs.forEach( function ( other ) {
				other.classList.toggle( 'is-active', other === thumb );
			} );

			shot.setAttribute( 'data-shot', thumb.getAttribute( 'data-shot' ) );
		} );
	} );

	var add = document.querySelector( '.add' );
	var original = add.textContent;
	var timer = null;

	add.addEventListener( 'click', function () {
		add.classList.add( 'is-added' );
		add.textContent = 'Added';

		window.clearTimeout( timer );
		timer = window.setTimeout( function () {
			add.classList.remove( 'is-added' );
			add.textContent = original;
		}, 1600 );
	} );

	window.dpgTemplate = {
		update: function ( state ) {
			original = ( state.content || {} ).button || original;
		}
	};
}() );
