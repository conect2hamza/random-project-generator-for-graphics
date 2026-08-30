/**
 * Coming soon demo: a live countdown and the success and error states of the
 * sign-up form, which the brief asks the designer to account for.
 */
( function () {
	'use strict';

	var target = new Date( Date.now() + 1000 * 60 * 60 * 24 * 14 );

	function pad( value ) {
		return value < 10 ? '0' + value : String( value );
	}

	function tick() {
		var left = Math.max( 0, target - Date.now() );
		var seconds = Math.floor( left / 1000 );
		var values = {
			days: Math.floor( seconds / 86400 ),
			hours: Math.floor( ( seconds % 86400 ) / 3600 ),
			minutes: Math.floor( ( seconds % 3600 ) / 60 ),
			seconds: seconds % 60
		};

		Object.keys( values ).forEach( function ( unit ) {
			var node = document.querySelector( '[data-unit="' + unit + '"]' );

			if ( node ) {
				node.textContent = pad( values[ unit ] );
			}
		} );
	}

	tick();
	setInterval( tick, 1000 );

	var form = document.querySelector( '.signup' );
	var state = document.querySelector( '.state' );

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();

		var value = form.querySelector( 'input' ).value.trim();
		var valid = /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test( value );

		state.className = 'state ' + ( valid ? 'is-ok' : 'is-error' );
		state.textContent = valid
			? 'Thanks. This is the success state of the form.'
			: 'That does not look like an email address. This is the error state.';
	} );
}() );
