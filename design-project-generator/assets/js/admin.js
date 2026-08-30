/**
 * Admin helpers.
 *
 * Small conveniences only — every screen works with JavaScript switched off.
 *
 * @package DesignProjectGenerator
 */

( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var lists = document.querySelectorAll( '.dpg-admin__toggles, .dpg-admin__palettes' );

		Array.prototype.forEach.call( lists, function ( list ) {
			var boxes = list.querySelectorAll( 'input[type="checkbox"]' );

			if ( boxes.length < 4 ) {
				return;
			}

			var bar = document.createElement( 'p' );

			bar.className = 'dpg-admin__bulk';

			[
				{ label: 'Select all', value: true },
				{ label: 'Select none', value: false }
			].forEach( function ( spec ) {
				var button = document.createElement( 'button' );

				button.type = 'button';
				button.className = 'button button-small';
				button.textContent = spec.label;
				button.addEventListener( 'click', function () {
					Array.prototype.forEach.call( boxes, function ( box ) {
						box.checked = spec.value;
					} );
				} );

				bar.appendChild( button );
			} );

			list.parentNode.insertBefore( bar, list );
		} );

		// Warn before leaving a half-filled "add record" form.
		var dirty = false;

		Array.prototype.forEach.call(
			document.querySelectorAll( '.dpg-admin__add input, .dpg-admin__add textarea' ),
			function ( field ) {
				field.addEventListener( 'input', function () {
					dirty = true;
				} );
			}
		);

		Array.prototype.forEach.call( document.querySelectorAll( '.dpg-admin form' ), function ( form ) {
			form.addEventListener( 'submit', function () {
				dirty = false;
			} );
		} );

		window.addEventListener( 'beforeunload', function ( event ) {
			if ( ! dirty ) {
				return;
			}

			event.preventDefault();
			event.returnValue = '';
		} );
	} );
}() );
