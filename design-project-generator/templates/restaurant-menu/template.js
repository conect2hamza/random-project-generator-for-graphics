/**
 * Restaurant menu demo: prefix every price with the currency symbol chosen in
 * the editor, so a menu can be checked in the market it is actually for.
 */
( function () {
	'use strict';

	function applySymbol( symbol ) {
		document.querySelectorAll( '.cost' ).forEach( function ( node ) {
			var value = node.textContent.replace( /^[^0-9]*/, '' );

			node.textContent = symbol + value;
		} );
	}

	applySymbol( '£' );

	window.dpgTemplate = {
		update: function ( state ) {
			var price = String( ( state.content || {} ).price || '' );
			var symbol = price.match( /^[^0-9\s]/ );

			applySymbol( symbol ? symbol[ 0 ] : '£' );
		}
	};
}() );
