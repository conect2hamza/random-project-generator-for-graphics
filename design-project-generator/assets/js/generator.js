/**
 * Design Project Generator — front-end core.
 *
 * Everything here is vanilla JavaScript with no build step and no external
 * dependency. The brief markup is produced by PHP and swapped in wholesale, so
 * this file never builds HTML from project data.
 *
 * @package DesignProjectGenerator
 */

( function () {
	'use strict';

	var root = window;

	/**
	 * Public namespace. Declared before anything else so the optional modules
	 * (timer, export, demo editor) can register themselves as they load.
	 */
	var DPG = root.DPG = root.DPG || {};

	DPG.plugins = DPG.plugins || [];
	DPG.instances = DPG.instances || [];

	/**
	 * Register a module that wants to extend every generator instance.
	 *
	 * Modules load after this file but before DOMContentLoaded, so by the time
	 * instances are built the plugin list is complete.
	 *
	 * @param {Function} fn Receives each instance.
	 */
	DPG.use = function ( fn ) {
		if ( typeof fn === 'function' ) {
			DPG.plugins.push( fn );
		}
	};

	var data = root.DPGData || {};
	var i18n = data.i18n || {};
	var STORAGE_PROJECTS = 'dpg.projects';
	var STORAGE_STATE = 'dpg.state';

	/* ------------------------------------------------------------------ *
	 * Small helpers
	 * ------------------------------------------------------------------ */

	function t( key, fallback ) {
		return i18n[ key ] || fallback || key;
	}

	function $( selector, context ) {
		return ( context || document ).querySelector( selector );
	}

	function $$( selector, context ) {
		return Array.prototype.slice.call( ( context || document ).querySelectorAll( selector ) );
	}

	function on( element, type, handler, options ) {
		if ( element ) {
			element.addEventListener( type, handler, options );
		}
	}

	/**
	 * localStorage that never throws — private browsing and blocked storage
	 * are normal conditions, not errors worth breaking the page over.
	 */
	var store = {
		get: function ( key, fallback ) {
			try {
				var raw = root.localStorage.getItem( key );
				return raw === null ? fallback : JSON.parse( raw );
			} catch ( e ) {
				return fallback;
			}
		},
		set: function ( key, value ) {
			try {
				root.localStorage.setItem( key, JSON.stringify( value ) );
				return true;
			} catch ( e ) {
				return false;
			}
		},
		remove: function ( key ) {
			try {
				root.localStorage.removeItem( key );
			} catch ( e ) {
				/* Nothing to do. */
			}
		}
	};

	DPG.store = store;
	DPG.t = t;

	/**
	 * Minimal fetch wrapper for the plugin's own REST namespace.
	 *
	 * @param {string} path   Route below the namespace.
	 * @param {Object} params Options: method, body.
	 * @return {Promise<Object>}
	 */
	function request( path, params ) {
		params = params || {};

		var url = ( data.restUrl || '' ).replace( /\/$/, '' ) + path;
		var options = {
			method: params.method || 'GET',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' }
		};

		if ( data.nonce ) {
			options.headers[ 'X-WP-Nonce' ] = data.nonce;
		}

		if ( params.body ) {
			options.body = JSON.stringify( params.body );
		}

		return root.fetch( url, options ).then( function ( response ) {
			return response.json().then( function ( json ) {
				if ( ! response.ok ) {
					var error = new Error( ( json && json.message ) || t( 'error' ) );
					error.code = json && json.code;
					throw error;
				}

				return json;
			} );
		} );
	}

	DPG.request = request;

	/**
	 * Copy text to the clipboard, with a fallback for browsers that refuse the
	 * async API outside a secure context.
	 *
	 * @param {string} text Text to copy.
	 * @return {Promise<boolean>}
	 */
	function copyText( text ) {
		if ( root.navigator && root.navigator.clipboard && root.isSecureContext ) {
			return root.navigator.clipboard.writeText( text ).then( function () {
				return true;
			} ).catch( function () {
				return legacyCopy( text );
			} );
		}

		return Promise.resolve( legacyCopy( text ) );
	}

	function legacyCopy( text ) {
		var field = document.createElement( 'textarea' );

		field.value = text;
		field.setAttribute( 'readonly', 'readonly' );
		field.style.position = 'fixed';
		field.style.top = '-1000px';
		document.body.appendChild( field );
		field.select();

		var ok = false;

		try {
			ok = document.execCommand( 'copy' );
		} catch ( e ) {
			ok = false;
		}

		document.body.removeChild( field );

		return ok;
	}

	DPG.copyText = copyText;

	/**
	 * Trigger a browser download for a blob.
	 *
	 * @param {Blob}   blob     Content.
	 * @param {string} filename Suggested file name.
	 */
	function download( blob, filename ) {
		var url = URL.createObjectURL( blob );
		var link = document.createElement( 'a' );

		link.href = url;
		link.download = filename;
		document.body.appendChild( link );
		link.click();
		document.body.removeChild( link );

		root.setTimeout( function () {
			URL.revokeObjectURL( url );
		}, 4000 );
	}

	DPG.download = download;

	/* ------------------------------------------------------------------ *
	 * Colour helpers, shared with the palette tool and the demo editor
	 * ------------------------------------------------------------------ */

	var color = {
		toHsl: function ( hex ) {
			var value = hex.replace( '#', '' );
			var r = parseInt( value.substring( 0, 2 ), 16 ) / 255;
			var g = parseInt( value.substring( 2, 4 ), 16 ) / 255;
			var b = parseInt( value.substring( 4, 6 ), 16 ) / 255;
			var max = Math.max( r, g, b );
			var min = Math.min( r, g, b );
			var l = ( max + min ) / 2;
			var h = 0;
			var s = 0;

			if ( max !== min ) {
				var d = max - min;

				s = l > 0.5 ? d / ( 2 - max - min ) : d / ( max + min );

				if ( max === r ) {
					h = ( g - b ) / d + ( g < b ? 6 : 0 );
				} else if ( max === g ) {
					h = ( b - r ) / d + 2;
				} else {
					h = ( r - g ) / d + 4;
				}

				h /= 6;
			}

			return { h: h * 360, s: s * 100, l: l * 100 };
		},

		toHex: function ( h, s, l ) {
			h = ( ( h % 360 ) + 360 ) % 360 / 360;
			s = Math.min( 100, Math.max( 0, s ) ) / 100;
			l = Math.min( 100, Math.max( 0, l ) ) / 100;

			function channel( p, q, tv ) {
				if ( tv < 0 ) {
					tv += 1;
				}
				if ( tv > 1 ) {
					tv -= 1;
				}
				if ( tv < 1 / 6 ) {
					return p + ( q - p ) * 6 * tv;
				}
				if ( tv < 1 / 2 ) {
					return q;
				}
				if ( tv < 2 / 3 ) {
					return p + ( q - p ) * ( 2 / 3 - tv ) * 6;
				}
				return p;
			}

			var r;
			var g;
			var b;

			if ( s === 0 ) {
				r = g = b = l;
			} else {
				var q = l < 0.5 ? l * ( 1 + s ) : l + s - l * s;
				var p = 2 * l - q;

				r = channel( p, q, h + 1 / 3 );
				g = channel( p, q, h );
				b = channel( p, q, h - 1 / 3 );
			}

			function hex( x ) {
				var out = Math.round( x * 255 ).toString( 16 );
				return out.length === 1 ? '0' + out : out;
			}

			return '#' + hex( r ) + hex( g ) + hex( b );
		},

		/**
		 * Relative luminance, used to keep generated text colours readable.
		 *
		 * @param {string} hex Colour.
		 * @return {number} 0–1.
		 */
		luminance: function ( hex ) {
			var value = hex.replace( '#', '' );
			var parts = [ 0, 2, 4 ].map( function ( offset ) {
				var channel = parseInt( value.substring( offset, offset + 2 ), 16 ) / 255;
				return channel <= 0.03928 ? channel / 12.92 : Math.pow( ( channel + 0.055 ) / 1.055, 2.4 );
			} );

			return 0.2126 * parts[ 0 ] + 0.7152 * parts[ 1 ] + 0.0722 * parts[ 2 ];
		},

		contrast: function ( a, b ) {
			var la = color.luminance( a );
			var lb = color.luminance( b );

			return ( Math.max( la, lb ) + 0.05 ) / ( Math.min( la, lb ) + 0.05 );
		}
	};

	DPG.color = color;

	/* ------------------------------------------------------------------ *
	 * Generator instance
	 * ------------------------------------------------------------------ */

	/**
	 * One generator on the page.
	 *
	 * @param {HTMLElement} element Wrapper element.
	 * @constructor
	 */
	function Instance( element ) {
		this.el = element;
		this.config = this.readConfig();
		this.settings = this.config.settings || {};
		this.filters = this.config.filters || {};
		this.project = null;
		this.lastFilters = null;
		this.savedPostId = 0;
		this.checklist = {};
		this.caseStudy = {};
		this.busy = false;

		this.card = $( '[data-dpg-card]', element );
		this.live = $( '[data-dpg-live]', element );

		this.bind();
		this.adoptRenderedProject();
		this.restoreFromUrl();
	}

	Instance.prototype.readConfig = function () {
		var node = $( '[data-dpg-config]', this.el );

		if ( ! node ) {
			return {};
		}

		try {
			return JSON.parse( node.textContent );
		} catch ( e ) {
			return {};
		}
	};

	/**
	 * Adopt the brief PHP already rendered.
	 *
	 * Its data arrives in the config block alongside the markup, so the page
	 * costs no request at all and the tools are guaranteed to be looking at
	 * the same project the visitor is reading.
	 */
	Instance.prototype.adoptRenderedProject = function () {
		if ( ! this.config.project ) {
			return;
		}

		this.project = this.config.project;
		this.lastFilters = this.filters;
		this.afterProject( false );
	};

	Instance.prototype.announce = function ( message ) {
		if ( this.live ) {
			this.live.textContent = message || '';
		}
	};

	Instance.prototype.currentFilters = function () {
		var filters = {
			category: '',
			type: '',
			industry: '',
			style: '',
			difficulty: '',
			demo_only: false
		};

		var form = $( '[data-dpg-filters]', this.el );

		if ( ! form ) {
			return Object.assign( filters, this.filters );
		}

		$$( '[data-dpg-filter]', form ).forEach( function ( field ) {
			var key = field.getAttribute( 'data-dpg-filter' );

			filters[ key ] = field.type === 'checkbox' ? field.checked : field.value;
		} );

		return filters;
	};

	/**
	 * Ask the server for a project.
	 *
	 * @param {Object}  filters  Filter values.
	 * @param {boolean} silent   Suppress the status message.
	 * @param {string}  mode     Regeneration mode.
	 * @return {Promise<Object>}
	 */
	Instance.prototype.requestProject = function ( filters, silent, mode ) {
		var body = Object.assign( {}, filters );

		if ( mode && mode !== 'random' && this.project ) {
			body.mode = mode;
			body.previous = this.project;
		}

		if ( ! silent ) {
			this.announce( t( 'generating' ) );
		}

		return request( '/generate', { method: 'POST', body: body } );
	};

	Instance.prototype.generate = function ( mode ) {
		if ( this.busy ) {
			return;
		}

		this.busy = true;
		this.setBusyState( true );

		var self = this;
		var filters = this.currentFilters();

		this.requestProject( filters, false, mode ).then( function ( payload ) {
			self.project = payload.project;
			self.lastFilters = filters;
			self.renderCard( payload.html );
			self.afterProject( true );
			self.announce( t( 'generated' ) );
		} ).catch( function ( error ) {
			self.announce( error && error.code === 'dpg_no_match' ? t( 'noMatch' ) : ( error.message || t( 'error' ) ) );
		} ).then( function () {
			self.busy = false;
			self.setBusyState( false );
		} );
	};

	Instance.prototype.setBusyState = function ( busy ) {
		if ( this.card ) {
			this.card.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		}

		this.el.classList.toggle( 'is-loading', !! busy );
	};

	/**
	 * Replace the brief markup.
	 *
	 * The HTML comes from this site's own REST endpoint, produced by the same
	 * PHP template that rendered the first brief, with every value escaped
	 * server side.
	 *
	 * @param {string} html Rendered brief.
	 */
	Instance.prototype.renderCard = function ( html ) {
		if ( ! this.card || typeof html !== 'string' ) {
			return;
		}

		this.card.innerHTML = html;
	};

	/**
	 * Housekeeping after a new project becomes current.
	 *
	 * @param {boolean} isNew Whether this replaced an earlier project.
	 */
	Instance.prototype.afterProject = function ( isNew ) {
		if ( isNew ) {
			this.savedPostId = 0;
			this.checklist = {};
			this.caseStudy = {};
			this.resetChecklistUi();
			this.closeAllMenus();
		}

		this.basePalette = this.project && this.project.palette ? Object.assign( {}, this.project.palette.colors ) : null;
		this.paintPalette();
		this.updateDemoButton();
		this.updateProgressVisibility();

		var self = this;

		DPG.plugins.forEach( function ( plugin ) {
			if ( typeof plugin.onProject === 'function' ) {
				plugin.onProject( self, isNew );
			}
		} );
	};

	Instance.prototype.updateProgressVisibility = function () {
		var progress = $( '[data-dpg-progress]', this.el );

		if ( progress ) {
			progress.hidden = ! this.project;
		}
	};

	Instance.prototype.updateDemoButton = function () {
		var button = $( '[data-dpg-open-demo]', this.el );

		if ( ! button ) {
			return;
		}

		var hasDemo = !! ( this.project && this.project.demo_type && this.project.demo_type !== 'none' );

		button.hidden = ! hasDemo;
	};

	/* ---------------------------- Palette ---------------------------- */

	Instance.prototype.paintPalette = function () {
		var colors = this.project && this.project.palette ? this.project.palette.colors : null;

		if ( ! colors ) {
			return;
		}

		$$( '[data-dpg-swatch]', this.el ).forEach( function ( swatch ) {
			var role = swatch.getAttribute( 'data-dpg-swatch' );
			var hex = colors[ role ];

			if ( ! hex ) {
				return;
			}

			var chip = $( '.dpg-swatch__chip', swatch );
			var label = $( '[data-dpg-copy-hex]', swatch );

			if ( chip ) {
				chip.style.background = hex;
			}

			if ( label ) {
				label.textContent = hex.toUpperCase();
			}
		} );
	};

	/**
	 * Regenerate the unlocked colours around a random harmony.
	 */
	Instance.prototype.shufflePalette = function () {
		if ( ! this.project || ! this.project.palette ) {
			return;
		}

		var colors = this.project.palette.colors;
		var locked = {};

		$$( '[data-dpg-swatch]', this.el ).forEach( function ( swatch ) {
			locked[ swatch.getAttribute( 'data-dpg-swatch' ) ] = swatch.getAttribute( 'data-locked' ) === 'true';
		} );

		var harmonies = [ 0, 30, 150, 180, 210, 330 ];
		var base = Math.floor( Math.random() * 360 );
		var offset = harmonies[ Math.floor( Math.random() * harmonies.length ) ];
		var dark = Math.random() < 0.25;

		var next = {
			primary: color.toHex( base, 45 + Math.random() * 30, dark ? 68 : 26 ),
			secondary: color.toHex( base + 12, 20 + Math.random() * 25, dark ? 52 : 42 ),
			accent: color.toHex( base + offset, 62 + Math.random() * 28, 52 ),
			background: color.toHex( base, 12 + Math.random() * 14, dark ? 9 : 96 ),
			text: color.toHex( base, 12, dark ? 94 : 12 )
		};

		// A palette whose text cannot be read on its own background is not a
		// palette, so nudge the text value until it clears WCAG AA.
		var guard = 0;

		while ( color.contrast( next.text, next.background ) < 4.5 && guard < 12 ) {
			var hsl = color.toHsl( next.text );

			next.text = color.toHex( hsl.h, hsl.s, dark ? Math.min( 98, hsl.l + 6 ) : Math.max( 4, hsl.l - 6 ) );
			guard++;
		}

		Object.keys( next ).forEach( function ( role ) {
			if ( ! locked[ role ] ) {
				colors[ role ] = next[ role ];
			}
		} );

		this.paintPalette();
		this.markChecklist( 'palette', true );
	};

	Instance.prototype.resetPalette = function () {
		if ( ! this.project || ! this.basePalette ) {
			return;
		}

		this.project.palette.colors = Object.assign( {}, this.basePalette );

		$$( '[data-dpg-swatch]', this.el ).forEach( function ( swatch ) {
			swatch.setAttribute( 'data-locked', 'false' );

			var lock = $( '[data-dpg-lock]', swatch );

			if ( lock ) {
				lock.setAttribute( 'aria-pressed', 'false' );
			}
		} );

		this.paintPalette();
	};

	/* ---------------------------- Saving ----------------------------- */

	Instance.prototype.save = function () {
		var self = this;

		if ( ! this.project ) {
			return;
		}

		this.readCaseStudy();

		var payload = {
			project: this.project,
			status: this.status(),
			checklist: this.checklist,
			case_study: this.caseStudy
		};

		if ( this.config.loggedIn ) {
			if ( this.savedPostId ) {
				payload.id = this.savedPostId;
			}

			request( this.savedPostId ? '/projects/' + this.savedPostId : '/projects', {
				method: 'POST',
				body: payload
			} ).then( function ( response ) {
				if ( response.project && response.project.post_id ) {
					self.savedPostId = response.project.post_id;
				}

				self.announce( t( 'saved' ) );
			} ).catch( function ( error ) {
				self.announce( error.message || t( 'error' ) );
			} );

			return;
		}

		var saved = store.get( STORAGE_PROJECTS, [] );

		if ( ! Array.isArray( saved ) ) {
			saved = [];
		}

		var record = {
			key: this.project.id + '-' + this.project.seed,
			created: new Date().toISOString(),
			status: payload.status,
			checklist: payload.checklist,
			case_study: payload.case_study,
			project: this.project
		};

		saved = saved.filter( function ( row ) {
			return row && row.key !== record.key;
		} );

		saved.unshift( record );
		store.set( STORAGE_PROJECTS, saved.slice( 0, 60 ) );

		this.announce( t( 'savedLocal' ) );
	};

	Instance.prototype.status = function () {
		var values = Object.keys( this.checklist ).filter( function ( key ) {
			return this.checklist[ key ];
		}, this );

		if ( values.length === 0 ) {
			return 'not-started';
		}

		return values.length >= 5 ? 'completed' : 'in-progress';
	};

	Instance.prototype.markChecklist = function ( key, value ) {
		this.checklist[ key ] = !! value;

		var box = $( '[data-dpg-check="' + key + '"]', this.el );

		if ( box ) {
			box.checked = !! value;
		}

		this.updateScore();
	};

	Instance.prototype.resetChecklistUi = function () {
		$$( '[data-dpg-check]', this.el ).forEach( function ( box ) {
			box.checked = false;
		} );

		$$( '[data-dpg-case]', this.el ).forEach( function ( field ) {
			field.value = '';
		} );

		this.updateScore();
	};

	Instance.prototype.updateScore = function () {
		var target = $( '[data-dpg-score]', this.el );

		if ( ! target ) {
			return;
		}

		var boxes = $$( '[data-dpg-check]', this.el );
		var done = boxes.filter( function ( box ) {
			return box.checked;
		} );

		var score = boxes.length ? Math.round( ( done.length / boxes.length ) * 100 ) : 0;

		target.textContent = String( score );
	};

	Instance.prototype.readCaseStudy = function () {
		var out = {};

		$$( '[data-dpg-case]', this.el ).forEach( function ( field ) {
			out[ field.getAttribute( 'data-dpg-case' ) ] = field.value;
		} );

		this.caseStudy = out;

		return out;
	};

	Instance.prototype.loadSaved = function () {
		var list = $( '[data-dpg-saved-list]', this.el );
		var self = this;

		if ( ! list ) {
			return;
		}

		list.textContent = '';

		function paint( rows ) {
			if ( ! rows.length ) {
				var empty = document.createElement( 'p' );

				empty.className = 'dpg-saved__empty';
				empty.textContent = t( 'noSaved' );
				list.appendChild( empty );

				return;
			}

			rows.forEach( function ( row ) {
				list.appendChild( self.savedRow( row ) );
			} );
		}

		if ( this.config.loggedIn ) {
			request( '/projects' ).then( function ( response ) {
				paint( response.projects || [] );
			} ).catch( function () {
				paint( [] );
			} );

			return;
		}

		paint( store.get( STORAGE_PROJECTS, [] ) || [] );
	};

	/**
	 * Build one row of the saved-projects list. Built with DOM calls rather
	 * than markup strings so stored values can never be interpreted as HTML.
	 *
	 * @param {Object} row Saved record.
	 * @return {HTMLElement}
	 */
	Instance.prototype.savedRow = function ( row ) {
		var self = this;
		var project = row.project || {};
		var item = document.createElement( 'article' );

		item.className = 'dpg-saved__item';

		var heading = document.createElement( 'h4' );

		heading.className = 'dpg-saved__title';
		heading.textContent = project.title || project.id || '';
		item.appendChild( heading );

		var meta = document.createElement( 'p' );

		meta.className = 'dpg-saved__meta';
		meta.textContent = [
			project.id,
			project.category,
			project.difficulty,
			self.statusLabel( row.status )
		].filter( Boolean ).join( ' · ' );
		item.appendChild( meta );

		var actions = document.createElement( 'p' );

		actions.className = 'dpg-saved__actions';

		var open = document.createElement( 'button' );

		open.type = 'button';
		open.className = 'dpg-btn dpg-btn--sm';
		open.textContent = t( 'open', 'Open' );
		on( open, 'click', function () {
			self.openSaved( row );
		} );
		actions.appendChild( open );

		var remove = document.createElement( 'button' );

		remove.type = 'button';
		remove.className = 'dpg-btn dpg-btn--sm dpg-btn--quiet';
		remove.textContent = t( 'delete', 'Delete' );
		on( remove, 'click', function () {
			if ( ! root.confirm( t( 'confirmDelete' ) ) ) {
				return;
			}

			self.deleteSaved( row );
			item.remove();
		} );
		actions.appendChild( remove );

		item.appendChild( actions );

		return item;
	};

	Instance.prototype.statusLabel = function ( status ) {
		if ( status === 'completed' ) {
			return t( 'statusCompleted' );
		}

		if ( status === 'in-progress' ) {
			return t( 'statusInProgress' );
		}

		return t( 'statusNotStarted' );
	};

	/**
	 * Re-open a saved project by regenerating it from its seed and resolved
	 * selections, which rebuilds the brief exactly without storing markup.
	 *
	 * @param {Object} row Saved record.
	 */
	Instance.prototype.openSaved = function ( row ) {
		var project = row.project || {};
		var self = this;

		this.savedPostId = row.post_id || 0;
		this.checklist = row.checklist || {};
		this.caseStudy = row.case_study || {};

		this.requestProject( this.filtersFromProject( project ), false ).then( function ( payload ) {
			self.project = payload.project;
			self.renderCard( payload.html );
			self.afterProject( false );
			self.applySavedState();
			self.closeModal( 'my-projects' );
		} ).catch( function ( error ) {
			self.announce( error.message || t( 'error' ) );
		} );
	};

	Instance.prototype.applySavedState = function () {
		var self = this;

		Object.keys( this.checklist ).forEach( function ( key ) {
			var box = $( '[data-dpg-check="' + key + '"]', self.el );

			if ( box ) {
				box.checked = !! self.checklist[ key ];
			}
		} );

		Object.keys( this.caseStudy ).forEach( function ( key ) {
			var field = $( '[data-dpg-case="' + key + '"]', self.el );

			if ( field ) {
				field.value = self.caseStudy[ key ] || '';
			}
		} );

		this.updateScore();
	};

	Instance.prototype.filtersFromProject = function ( project ) {
		return {
			seed: project.seed || 0,
			category: project.category_id || '',
			type: project.project_type_id || '',
			industry: project.industry_id || '',
			style: project.style_id || '',
			difficulty: project.difficulty || ''
		};
	};

	Instance.prototype.deleteSaved = function ( row ) {
		var self = this;

		if ( this.config.loggedIn && row.post_id ) {
			request( '/projects/' + row.post_id, { method: 'DELETE' } ).then( function () {
				self.announce( t( 'deleted' ) );
			} ).catch( function ( error ) {
				self.announce( error.message || t( 'error' ) );
			} );

			return;
		}

		var saved = ( store.get( STORAGE_PROJECTS, [] ) || [] ).filter( function ( item ) {
			return item && item.key !== row.key;
		} );

		store.set( STORAGE_PROJECTS, saved );
		this.announce( t( 'deleted' ) );
	};

	/* ------------------------- Share and URL ------------------------- */

	/**
	 * Build a link that rebuilds this exact brief.
	 *
	 * The seed plus the resolved selections are enough: the generator draws in
	 * the same order whether or not a value was pinned, so replaying them
	 * reproduces the brief byte for byte.
	 *
	 * @return {string}
	 */
	Instance.prototype.shareUrl = function () {
		if ( ! this.project ) {
			return root.location.href;
		}

		var url = new URL( root.location.href );
		var project = this.project;

		url.searchParams.set( 'dpg_seed', project.seed );
		url.searchParams.set( 'dpg_cat', project.category_id || '' );
		url.searchParams.set( 'dpg_type', project.project_type_id || '' );
		url.searchParams.set( 'dpg_ind', project.industry_id || '' );
		url.searchParams.set( 'dpg_sty', project.style_id || '' );
		url.searchParams.set( 'dpg_dif', project.difficulty || '' );
		url.hash = this.el.id;

		return url.toString();
	};

	Instance.prototype.restoreFromUrl = function () {
		var params = new URLSearchParams( root.location.search );
		var seed = parseInt( params.get( 'dpg_seed' ), 10 );
		var hash = root.location.hash.replace( '#', '' );

		if ( ! seed ) {
			return;
		}

		// The hash names the generator a link was shared from. With no hash,
		// only the first generator on the page claims the link.
		if ( hash ? hash !== this.el.id : DPG.instances.length > 0 ) {
			return;
		}

		var self = this;
		var filters = {
			seed: seed,
			category: params.get( 'dpg_cat' ) || '',
			type: params.get( 'dpg_type' ) || '',
			industry: params.get( 'dpg_ind' ) || '',
			style: params.get( 'dpg_sty' ) || '',
			difficulty: params.get( 'dpg_dif' ) || ''
		};

		this.requestProject( filters, false ).then( function ( payload ) {
			self.project = payload.project;
			self.renderCard( payload.html );
			self.afterProject( true );
		} ).catch( function () {
			/* Fall back to whatever was rendered on the server. */
		} );
	};

	/* ---------------------------- Menus ------------------------------ */

	Instance.prototype.closeAllMenus = function () {
		$$( '[data-dpg-menu]', this.el ).forEach( function ( menu ) {
			menu.hidden = true;
		} );

		$$( '[data-dpg-menu-toggle]', this.el ).forEach( function ( toggle ) {
			toggle.setAttribute( 'aria-expanded', 'false' );
		} );
	};

	Instance.prototype.toggleMenu = function ( name ) {
		var menu = $( '[data-dpg-menu="' + name + '"]', this.el );
		var toggle = $( '[data-dpg-menu-toggle="' + name + '"]', this.el );

		if ( ! menu ) {
			return;
		}

		var willOpen = menu.hidden;

		this.closeAllMenus();

		menu.hidden = ! willOpen;

		if ( toggle ) {
			toggle.setAttribute( 'aria-expanded', willOpen ? 'true' : 'false' );
		}

		if ( willOpen ) {
			var first = $( 'button', menu );

			if ( first ) {
				first.focus();
			}
		}
	};

	/* ---------------------------- Modals ----------------------------- */

	Instance.prototype.openModal = function ( name ) {
		var modal = $( '[data-dpg-modal="' + name + '"]', this.el );

		if ( ! modal ) {
			return;
		}

		this.lastFocus = document.activeElement;
		modal.hidden = false;
		document.body.classList.add( 'dpg-modal-open' );

		var focusable = $( 'button, [href], input, select, textarea', modal );

		if ( focusable ) {
			focusable.focus();
		}

		this.openModalName = name;
	};

	Instance.prototype.closeModal = function ( name ) {
		var modal = name
			? $( '[data-dpg-modal="' + name + '"]', this.el )
			: $( '[data-dpg-modal]:not([hidden])', this.el );

		if ( ! modal ) {
			return;
		}

		modal.hidden = true;
		this.openModalName = null;
		document.body.classList.remove( 'dpg-modal-open' );

		if ( this.lastFocus && this.lastFocus.focus ) {
			this.lastFocus.focus();
		}
	};

	/**
	 * Keep tab focus inside an open dialog.
	 *
	 * @param {KeyboardEvent} event Key event.
	 */
	Instance.prototype.trapFocus = function ( event ) {
		var modal = $( '[data-dpg-modal]:not([hidden])', this.el );

		if ( ! modal || event.key !== 'Tab' ) {
			return;
		}

		var items = $$( 'button, [href], input, select, textarea, iframe, [tabindex]:not([tabindex="-1"])', modal )
			.filter( function ( item ) {
				return ! item.disabled && item.offsetParent !== null;
			} );

		if ( ! items.length ) {
			return;
		}

		var first = items[ 0 ];
		var last = items[ items.length - 1 ];

		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	};

	/* ------------------------- Small actions ------------------------- */

	Instance.prototype.hint = function () {
		var hints = ( data.options && data.options.hints ) || [];
		var target = $( '[data-dpg-hint]', this.el );

		if ( ! hints.length || ! target ) {
			return;
		}

		var next = hints[ Math.floor( Math.random() * hints.length ) ];
		var guard = 0;

		while ( next === target.textContent && hints.length > 1 && guard < 6 ) {
			next = hints[ Math.floor( Math.random() * hints.length ) ];
			guard++;
		}

		target.textContent = next;
	};

	Instance.prototype.solutionHint = function () {
		var hints = ( data.options && data.options.solutionHints ) || [];
		var target = $( '[data-dpg-hint]', this.el );

		if ( ! hints.length ) {
			return;
		}

		var pick = hints[ Math.floor( Math.random() * hints.length ) ];

		if ( target ) {
			target.textContent = pick;
		}

		this.announce( pick );
	};

	Instance.prototype.action = function ( name ) {
		var self = this;

		this.closeAllMenus();

		switch ( name ) {
			case 'copy':
				if ( DPG.exporter ) {
					copyText( DPG.exporter.toText( this.project ) ).then( function ( ok ) {
						self.announce( ok ? t( 'copied' ) : t( 'copyFailed' ) );
					} );
				}
				break;

			case 'print':
				this.el.classList.add( 'dpg--printing' );
				root.print();
				root.setTimeout( function () {
					self.el.classList.remove( 'dpg--printing' );
				}, 500 );
				break;

			case 'share':
				copyText( this.shareUrl() ).then( function ( ok ) {
					self.announce( ok ? t( 'linkCopied' ) : t( 'copyFailed' ) );
				} );
				break;

			case 'solution':
				this.solutionHint();
				break;

			case 'case':
				this.applySavedState();
				this.openModal( 'case' );
				break;

			case 'my-projects':
				this.loadSaved();
				this.openModal( 'my-projects' );
				break;

			case 'reset':
				$$( '[data-dpg-filter]', this.el ).forEach( function ( field ) {
					if ( field.type === 'checkbox' ) {
						field.checked = false;
					} else {
						field.value = '';
					}
				} );
				this.announce( t( 'reset', 'Filters cleared.' ) );
				break;
		}
	};

	/* --------------------------- Teacher ----------------------------- */

	Instance.prototype.createAssignment = function ( form ) {
		var self = this;
		var body = {};

		$$( '[data-dpg-assignment]', form ).forEach( function ( field ) {
			body[ field.getAttribute( 'data-dpg-assignment' ) ] = field.value;
		} );

		var result = $( '[data-dpg-teacher-result]', this.el );

		request( '/assignments', { method: 'POST', body: body } ).then( function ( response ) {
			self.announce( t( 'assignmentDone' ) );
			self.paintAssignment( result, response.assignment );
		} ).catch( function ( error ) {
			self.announce( error.message || t( 'error' ) );
		} );
	};

	Instance.prototype.paintAssignment = function ( target, assignment ) {
		if ( ! target || ! assignment ) {
			return;
		}

		target.textContent = '';

		var heading = document.createElement( 'h4' );

		heading.textContent = assignment.title;
		target.appendChild( heading );

		var list = document.createElement( 'ol' );

		list.className = 'dpg-teacher__list';

		( assignment.projects || [] ).forEach( function ( project, index ) {
			var item = document.createElement( 'li' );

			item.textContent = t( 'student' ) + ' ' + String( index + 1 ).padStart( 2, '0' ) +
				' — ' + project.project_type + ' · ' + project.industry + ' (' + project.id + ')';
			list.appendChild( item );
		} );

		target.appendChild( list );
	};

	/* ---------------------------- Binding ---------------------------- */

	Instance.prototype.bind = function () {
		var self = this;

		var form = $( '[data-dpg-filters]', this.el );

		on( form, 'submit', function ( event ) {
			event.preventDefault();
			self.generate( 'random' );
		} );

		$$( '[data-dpg-generate]', this.el ).forEach( function ( button ) {
			if ( button.type === 'submit' ) {
				return;
			}

			on( button, 'click', function () {
				self.generate( 'random' );
			} );
		} );

		$$( '[data-dpg-mode]', this.el ).forEach( function ( button ) {
			on( button, 'click', function () {
				self.generate( button.getAttribute( 'data-dpg-mode' ) );
			} );
		} );

		$$( '[data-dpg-menu-toggle]', this.el ).forEach( function ( toggle ) {
			on( toggle, 'click', function ( event ) {
				event.stopPropagation();
				self.toggleMenu( toggle.getAttribute( 'data-dpg-menu-toggle' ) );
			} );
		} );

		$$( '[data-dpg-action]', this.el ).forEach( function ( button ) {
			on( button, 'click', function () {
				self.action( button.getAttribute( 'data-dpg-action' ) );
			} );
		} );

		on( $( '[data-dpg-save]', this.el ), 'click', function () {
			self.save();
		} );

		on( $( '[data-dpg-palette-shuffle]', this.el ), 'click', function () {
			self.shufflePalette();
		} );

		on( $( '[data-dpg-palette-reset]', this.el ), 'click', function () {
			self.resetPalette();
		} );

		on( $( '[data-dpg-hint-button]', this.el ), 'click', function () {
			self.hint();
		} );

		// The palette list is rebuilt rarely, so a delegated listener keeps the
		// lock and copy buttons working whatever replaces it.
		on( this.el, 'click', function ( event ) {
			var lock = event.target.closest ? event.target.closest( '[data-dpg-lock]' ) : null;

			if ( lock ) {
				var swatch = lock.closest( '[data-dpg-swatch]' );
				var locked = swatch.getAttribute( 'data-locked' ) === 'true';

				swatch.setAttribute( 'data-locked', locked ? 'false' : 'true' );
				lock.setAttribute( 'aria-pressed', locked ? 'false' : 'true' );

				return;
			}

			var hex = event.target.closest ? event.target.closest( '[data-dpg-copy-hex]' ) : null;

			if ( hex ) {
				copyText( hex.textContent.trim() ).then( function ( ok ) {
					self.announce( ok ? t( 'copied' ) : t( 'copyFailed' ) );
				} );
			}
		} );

		$$( '[data-dpg-check]', this.el ).forEach( function ( box ) {
			on( box, 'change', function () {
				self.checklist[ box.getAttribute( 'data-dpg-check' ) ] = box.checked;
				self.updateScore();
			} );
		} );

		$$( '[data-dpg-modal-close]', this.el ).forEach( function ( button ) {
			on( button, 'click', function () {
				self.closeModal();
			} );
		} );

		on( $( '[data-dpg-case-form]', this.el ), 'submit', function ( event ) {
			event.preventDefault();
			self.readCaseStudy();
			self.save();
		} );

		on( $( '[data-dpg-teacher-form]', this.el ), 'submit', function ( event ) {
			event.preventDefault();
			self.createAssignment( event.target );
		} );

		on( document, 'click', function ( event ) {
			if ( ! self.el.contains( event.target ) ) {
				self.closeAllMenus();
			}
		} );

		on( document, 'keydown', function ( event ) {
			if ( event.key === 'Escape' ) {
				self.closeAllMenus();
				self.closeModal();
			}

			self.trapFocus( event );
		} );
	};

	/* ------------------------------------------------------------------ *
	 * Boot
	 * ------------------------------------------------------------------ */

	function boot() {
		$$( '[data-dpg]' ).forEach( function ( element ) {
			if ( element.dpgInstance ) {
				return;
			}

			var instance = new Instance( element );

			element.dpgInstance = instance;
			DPG.instances.push( instance );

			DPG.plugins.forEach( function ( plugin ) {
				if ( typeof plugin.init === 'function' ) {
					plugin.init( instance );
				}
			} );
		} );
	}

	DPG.boot = boot;
	DPG.Instance = Instance;

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
