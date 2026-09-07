/**
 * Static demo runtime.
 *
 * The plugin's own front-end scripts run here completely unmodified. The only
 * thing replaced is the network: fetch is intercepted for the plugin's REST
 * namespace and answered locally by the ported generator, so every code path
 * the real plugin takes — request, response shape, error handling, re-render —
 * is the code path exercised in this page.
 *
 * This file must load before the plugin scripts, because generator.js reads
 * window.DPGData when it evaluates.
 */

( function () {
	'use strict';

	var payload = document.getElementById( 'dpg-demo-data' );

	if ( ! payload ) {
		return;
	}

	var bundle = JSON.parse( payload.textContent );

	// Exactly what wp_localize_script() would have attached.
	window.DPGData = bundle.scriptData;
	window.DPGDemo = {
		db: bundle.db,
		templates: bundle.templates,
		stats: bundle.stats
	};

	var REST_PREFIX = '/wp-json/dpg/v1';
	var nativeFetch = window.fetch ? window.fetch.bind( window ) : null;

	/**
	 * A Response-shaped object. The plugin only reads `ok` and `json()`.
	 *
	 * @param {Object}  body   Payload.
	 * @param {boolean} ok     Success flag.
	 * @return {Promise<Object>}
	 */
	function reply( body, ok ) {
		return Promise.resolve( {
			ok: ok !== false,
			status: ok === false ? 400 : 200,
			json: function () {
				return Promise.resolve( body );
			}
		} );
	}

	function generate( body ) {
		var Engine = window.DPGEngine;
		var Render = window.DPGRender;
		var generator = new Engine.Generator( window.DPGDemo.db, body.seed || 0 );
		var project = generator.generate( body, body.previous );

		if ( ! project ) {
			return reply(
				{
					code: 'dpg_no_match',
					message: 'No project type matches those filters. Try widening them.'
				},
				false
			);
		}

		return reply( { project: project, html: Render.project( project ) } );
	}

	function demoTemplate( id ) {
		var template = window.DPGDemo.templates[ id ];

		if ( ! template ) {
			return reply(
				{ code: 'dpg_no_template', message: 'That demo template does not exist.' },
				false
			);
		}

		return reply( {
			id: id,
			html: template.html,
			css: template.css,
			js: template.js
		} );
	}

	window.fetch = function ( input, options ) {
		var url = typeof input === 'string' ? input : ( input && input.url ) || '';
		var index = url.indexOf( REST_PREFIX );

		if ( index === -1 ) {
			return nativeFetch
				? nativeFetch( input, options )
				: Promise.reject( new Error( 'offline' ) );
		}

		var route = url.slice( index + REST_PREFIX.length ).split( '?' )[ 0 ];
		var body = {};

		if ( options && options.body ) {
			try {
				body = JSON.parse( options.body );
			} catch ( e ) {
				body = {};
			}
		}

		if ( route === '/generate' ) {
			return generate( body );
		}

		if ( route.indexOf( '/demo/' ) === 0 ) {
			return demoTemplate( decodeURIComponent( route.slice( 6 ) ) );
		}

		if ( route === '/daily' ) {
			var project = window.DPGEngine.Generator.daily(
				window.DPGDemo.db,
				new Date().toISOString().slice( 0, 10 )
			);

			return reply( {
				project: project,
				html: window.DPGRender.project( project )
			} );
		}

		// Saving falls through to localStorage in the plugin whenever the
		// visitor is logged out, which is the case here, so these routes are
		// never reached. Answer honestly if anything does call them.
		return reply(
			{ code: 'dpg_demo_offline', message: 'That feature needs the WordPress plugin installed.' },
			false
		);
	};
}() );
