/**
 * Wires the demo page's own controls to the booted generator.
 *
 * Loads after the plugin scripts so window.DPG.instances is already populated.
 */

( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', fn );
		} else {
			fn();
		}
	}

	ready( function () {
		/* ---- theme toggle ---- */
		var toggle = document.querySelector( '[data-theme-toggle]' );

		function currentTheme() {
			var explicit = document.documentElement.getAttribute( 'data-theme' );

			if ( explicit ) {
				return explicit;
			}

			return window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches
				? 'dark'
				: 'light';
		}

		function paintToggle() {
			if ( toggle ) {
				toggle.textContent = currentTheme() === 'dark' ? 'Light theme' : 'Dark theme';
			}
		}

		paintToggle();

		if ( toggle ) {
			toggle.addEventListener( 'click', function () {
				document.documentElement.setAttribute(
					'data-theme',
					currentTheme() === 'dark' ? 'light' : 'dark'
				);
				paintToggle();
			} );
		}

		/* ---- generator-backed actions ---- */
		var instance = ( window.DPG && window.DPG.instances && window.DPG.instances[ 0 ] ) || null;

		if ( ! instance ) {
			return;
		}

		var daily = document.querySelector( '[data-demo-daily]' );

		if ( daily ) {
			daily.addEventListener( 'click', function () {
				var today = new Date().toISOString().slice( 0, 10 );
				var project = window.DPGEngine.Generator.daily( window.DPGDemo.db, today );

				if ( ! project ) {
					return;
				}

				instance.project = project;
				instance.renderCard( window.DPGRender.project( project ) );
				instance.afterProject( true );
				instance.announce(
					'Today’s challenge. Everyone opening this page on ' + today + ' gets this same brief.'
				);
			} );
		}

		/**
		 * Put the filter bar into an exact state before generating.
		 *
		 * Setting one field and leaving the rest is how you end up asking for
		 * something impossible — a branding brief with an interactive demo,
		 * for instance, since no branding project type has one.
		 *
		 * @param {Object} values Filter name to value.
		 */
		function setFilters( values ) {
			Array.prototype.forEach.call(
				instance.el.querySelectorAll( '[data-dpg-filter]' ),
				function ( field ) {
					var name = field.getAttribute( 'data-dpg-filter' );
					var value = Object.prototype.hasOwnProperty.call( values, name )
						? values[ name ]
						: ( field.type === 'checkbox' ? false : '' );

					if ( field.type === 'checkbox' ) {
						field.checked = !! value;
					} else {
						field.value = value;
					}
				}
			);
		}

		var expert = document.querySelector( '[data-demo-expert]' );

		if ( expert ) {
			expert.addEventListener( 'click', function () {
				setFilters( { difficulty: 'expert' } );
				instance.generate( 'random' );
			} );
		}

		var demoOnly = document.querySelector( '[data-demo-withdemo]' );

		if ( demoOnly ) {
			demoOnly.addEventListener( 'click', function () {
				setFilters( { demo_only: true } );
				instance.generate( 'random' );
			} );
		}
	} );
}() );
