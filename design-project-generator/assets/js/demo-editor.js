/**
 * Mini demo system.
 *
 * Demo templates are bundled HTML, CSS and JavaScript files. They are assembled
 * into a sandboxed iframe with `allow-scripts` only, which puts them on an
 * opaque origin: template code cannot read cookies, storage or the DOM of the
 * WordPress page around it, and none of it is ever evaluated in the page.
 *
 * The editor never injects markup. Values travel to the frame as data over
 * postMessage, and the frame writes them with textContent and CSS custom
 * properties.
 *
 * @package DesignProjectGenerator
 */

( function () {
	'use strict';

	var DPG = window.DPG;

	if ( ! DPG || ! DPG.use ) {
		return;
	}

	var t = DPG.t;
	var cache = {};

	/**
	 * Runs inside the sandboxed frame. Kept as a string so it can be injected
	 * into the iframe document; it never runs in the WordPress page.
	 */
	var BOOTSTRAP = [
		'(function(){',
		'"use strict";',
		'function apply(state){',
		'  if(!state){return;}',
		'  var content=state.content||{};',
		'  Object.keys(content).forEach(function(key){',
		'    var nodes=document.querySelectorAll(\'[data-dpg-field="\'+key+\'"]\');',
		'    Array.prototype.forEach.call(nodes,function(node){',
		'      node.textContent=content[key];',
		'    });',
		'  });',
		'  var design=state.design||{};',
		'  var root=document.documentElement;',
		'  Object.keys(design).forEach(function(key){',
		'    root.style.setProperty("--dpg-"+key,design[key]);',
		'  });',
		'  if(window.dpgTemplate&&typeof window.dpgTemplate.update==="function"){',
		'    try{window.dpgTemplate.update(state);}catch(e){}',
		'  }',
		'}',
		'window.addEventListener("message",function(event){',
		'  if(event.source!==window.parent){return;}',
		'  var data=event.data;',
		'  if(!data||data.type!=="dpg-demo"){return;}',
		'  apply(data.state);',
		'});',
		'var initial=document.getElementById("dpg-demo-state");',
		'if(initial){try{apply(JSON.parse(initial.textContent));}catch(e){}}',
		'}());'
	].join( '\n' );

	var RESET = [
		'*,*::before,*::after{box-sizing:border-box;}',
		'html,body{margin:0;padding:0;}',
		'body{',
		'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;',
		'background:var(--dpg-background,#ffffff);',
		'color:var(--dpg-text,#111827);',
		'font-size:var(--dpg-fontSize,16px);',
		'line-height:1.5;',
		'-webkit-font-smoothing:antialiased;',
		'}',
		'img{max-width:100%;height:auto;}',
		'@media (prefers-reduced-motion: reduce){',
		'*{animation-duration:0.01ms !important;transition-duration:0.01ms !important;}',
		'}'
	].join( '' );

	/**
	 * Default editable values, seeded from the brief so the demo starts as a
	 * plausible first draft rather than lorem ipsum.
	 *
	 * @param {Object} project Project data.
	 * @return {Object}
	 */
	function seedState( project ) {
		project = project || {};

		var colors = ( project.palette && project.palette.colors ) || {};

		return {
			content: {
				headline: project.title || '',
				subheadline: project.objective || '',
				description: project.background || '',
				button: 'Learn more',
				price: '29',
				contact: ( project.client || '' ) + ' — hello@example.com',
				brand: project.client || '',
				eyebrow: project.category || ''
			},
			design: {
				primary: colors.primary || '#1f2937',
				secondary: colors.secondary || '#4b5563',
				accent: colors.accent || '#f59e0b',
				background: colors.background || '#f9fafb',
				text: colors.text || '#111827',
				fontSize: '16px',
				radius: '10px',
				spacing: '24px'
			}
		};
	}

	/**
	 * Build the complete document for the sandboxed frame.
	 *
	 * @param {Object} template Template source files.
	 * @param {Object} state    Current editor state.
	 * @return {string}
	 */
	function buildDocument( template, state ) {
		// The state is serialised into a JSON script block, so its values are
		// read as data by the frame and never parsed as markup or code.
		var json = JSON.stringify( state ).replace( /</g, '\\u003c' );

		return [
			'<!DOCTYPE html>',
			'<html lang="en"><head><meta charset="utf-8">',
			'<meta name="viewport" content="width=device-width, initial-scale=1">',
			'<title>Demo preview</title>',
			'<style>' + RESET + '</style>',
			'<style>' + ( template.css || '' ) + '</style>',
			'</head><body>',
			template.html || '',
			'<script type="application/json" id="dpg-demo-state">' + json + '<\/script>',
			'<script>' + BOOTSTRAP + '<\/script>',
			template.js ? '<script>' + template.js + '<\/script>' : '',
			'</body></html>'
		].join( '\n' );
	}

	/**
	 * Which editable fields a template declares.
	 *
	 * Parsing the template rather than keeping a hard-coded list means a new
	 * template only has to mark its elements to get controls for them.
	 *
	 * @param {string} html Template markup.
	 * @return {Array} Field names.
	 */
	function fieldsIn( html ) {
		var found = [];

		try {
			var parsed = new DOMParser().parseFromString( '<body>' + html + '</body>', 'text/html' );

			Array.prototype.forEach.call(
				parsed.querySelectorAll( '[data-dpg-field]' ),
				function ( node ) {
					var name = node.getAttribute( 'data-dpg-field' );

					if ( name && found.indexOf( name ) === -1 ) {
						found.push( name );
					}
				}
			);
		} catch ( e ) {
			return [];
		}

		return found;
	}

	function label( key ) {
		var map = {
			headline: 'headline',
			subheadline: 'subheadline',
			description: 'description',
			button: 'buttonText',
			price: 'price',
			contact: 'contact',
			primary: 'primaryColor',
			secondary: 'secondaryColor',
			accent: 'primaryColor',
			background: 'backgroundColor',
			text: 'textColor',
			fontSize: 'fontSize',
			radius: 'radius',
			spacing: 'spacing'
		};

		if ( map[ key ] ) {
			var translated = t( map[ key ], '' );

			if ( translated && translated !== map[ key ] ) {
				return translated;
			}
		}

		return key.replace( /([A-Z])/g, ' $1' ).replace( /^./, function ( c ) {
			return c.toUpperCase();
		} );
	}

	/**
	 * One demo editor, bound to a generator instance.
	 *
	 * @param {Object} instance Generator instance.
	 * @constructor
	 */
	function DemoEditor( instance ) {
		this.instance = instance;
		this.frame = instance.el.querySelector( '[data-dpg-demo-frame]' );
		this.controls = instance.el.querySelector( '[data-dpg-demo-controls]' );
		this.state = null;
		this.template = null;

		var button = instance.el.querySelector( '[data-dpg-open-demo]' );
		var self = this;

		if ( button ) {
			button.addEventListener( 'click', function () {
				self.open();
			} );
		}
	}

	DemoEditor.prototype.open = function () {
		var project = this.instance.project;

		if ( ! project || ! project.demo_type || project.demo_type === 'none' ) {
			this.instance.announce( t( 'noDemo' ) );

			return;
		}

		var self = this;

		this.instance.openModal( 'demo' );
		this.load( project.demo_type ).then( function ( template ) {
			self.template = template;
			self.state = seedState( project );
			self.renderControls();
			self.renderFrame();
		} ).catch( function () {
			self.instance.announce( t( 'demoLoadFailed' ) );
		} );
	};

	/**
	 * Fetch a template, once per page load.
	 *
	 * @param {string} id Template identifier.
	 * @return {Promise<Object>}
	 */
	DemoEditor.prototype.load = function ( id ) {
		if ( cache[ id ] ) {
			return Promise.resolve( cache[ id ] );
		}

		return DPG.request( '/demo/' + encodeURIComponent( id ) ).then( function ( template ) {
			cache[ id ] = template;

			return template;
		} );
	};

	DemoEditor.prototype.renderFrame = function () {
		if ( ! this.frame || ! this.template ) {
			return;
		}

		this.frame.srcdoc = buildDocument( this.template, this.state );
	};

	/**
	 * Push the current state into the frame without reloading it, so typing
	 * updates the preview live instead of flickering.
	 */
	DemoEditor.prototype.push = function () {
		if ( ! this.frame || ! this.frame.contentWindow ) {
			return;
		}

		this.frame.contentWindow.postMessage(
			{ type: 'dpg-demo', state: this.state },
			'*'
		);
	};

	DemoEditor.prototype.renderControls = function () {
		if ( ! this.controls ) {
			return;
		}

		var self = this;

		this.controls.textContent = '';

		var declared = fieldsIn( this.template.html );
		var contentKeys = declared.filter( function ( key ) {
			return Object.prototype.hasOwnProperty.call( self.state.content, key );
		} );

		if ( ! contentKeys.length ) {
			contentKeys = [ 'headline', 'description', 'button' ];
		}

		this.controls.appendChild(
			this.group( t( 'content' ), contentKeys.map( function ( key ) {
				var multiline = key === 'description' || key === 'subheadline';

				return self.field( {
					key: key,
					label: label( key ),
					type: multiline ? 'textarea' : 'text',
					value: self.state.content[ key ] || '',
					onChange: function ( value ) {
						self.state.content[ key ] = value;
						self.push();
					}
				} );
			} ) )
		);

		var colorKeys = [ 'primary', 'secondary', 'accent', 'background', 'text' ];
		var designNodes = colorKeys.map( function ( key ) {
			return self.field( {
				key: key,
				label: label( key ),
				type: 'color',
				value: self.state.design[ key ],
				onChange: function ( value ) {
					self.state.design[ key ] = value;
					self.push();
				}
			} );
		} );

		[
			{ key: 'fontSize', min: 12, max: 24, unit: 'px' },
			{ key: 'radius', min: 0, max: 32, unit: 'px' },
			{ key: 'spacing', min: 8, max: 64, unit: 'px' }
		].forEach( function ( spec ) {
			designNodes.push( self.field( {
				key: spec.key,
				label: label( spec.key ),
				type: 'range',
				min: spec.min,
				max: spec.max,
				value: parseInt( self.state.design[ spec.key ], 10 ) || spec.min,
				onChange: function ( value ) {
					self.state.design[ spec.key ] = value + spec.unit;
					self.push();
				}
			} ) );
		} );

		this.controls.appendChild( this.group( t( 'design' ), designNodes ) );
		this.controls.appendChild( this.actions() );
	};

	DemoEditor.prototype.group = function ( title, nodes ) {
		var section = document.createElement( 'section' );

		section.className = 'dpg-demo__group';

		var heading = document.createElement( 'h4' );

		heading.className = 'dpg-demo__group-title';
		heading.textContent = title;
		section.appendChild( heading );

		nodes.forEach( function ( node ) {
			section.appendChild( node );
		} );

		return section;
	};

	/**
	 * Build one labelled control.
	 *
	 * @param {Object} spec Control specification.
	 * @return {HTMLElement}
	 */
	DemoEditor.prototype.field = function ( spec ) {
		var wrap = document.createElement( 'p' );

		wrap.className = 'dpg-demo__field dpg-demo__field--' + spec.type;

		var id = 'dpg-demo-' + spec.key + '-' + Math.random().toString( 36 ).slice( 2, 8 );
		var labelEl = document.createElement( 'label' );

		labelEl.setAttribute( 'for', id );
		labelEl.textContent = spec.label;
		wrap.appendChild( labelEl );

		var input;

		if ( spec.type === 'textarea' ) {
			input = document.createElement( 'textarea' );
			input.rows = 3;
		} else {
			input = document.createElement( 'input' );
			input.type = spec.type === 'color' ? 'color' : ( spec.type === 'range' ? 'range' : 'text' );

			if ( spec.type === 'range' ) {
				input.min = spec.min;
				input.max = spec.max;
				input.step = 1;
			}
		}

		input.id = id;
		input.value = spec.value;
		input.className = 'dpg-demo__input';

		input.addEventListener( 'input', function () {
			spec.onChange( input.value );
		} );

		wrap.appendChild( input );

		return wrap;
	};

	DemoEditor.prototype.actions = function () {
		var self = this;
		var wrap = document.createElement( 'p' );

		wrap.className = 'dpg-demo__actions';

		var reset = document.createElement( 'button' );

		reset.type = 'button';
		reset.className = 'dpg-btn dpg-btn--sm dpg-btn--quiet';
		reset.textContent = t( 'resetDemo' );
		reset.addEventListener( 'click', function () {
			self.state = seedState( self.instance.project );
			self.renderControls();
			self.renderFrame();
		} );
		wrap.appendChild( reset );

		var save = document.createElement( 'button' );

		save.type = 'button';
		save.className = 'dpg-btn dpg-btn--sm';
		save.textContent = t( 'downloadDemo' );
		save.addEventListener( 'click', function () {
			var html = buildDocument( self.template, self.state );

			DPG.download(
				new Blob( [ html ], { type: 'text/html;charset=utf-8' } ),
				( self.instance.project.demo_type || 'demo' ) + '.html'
			);
		} );
		wrap.appendChild( save );

		return wrap;
	};

	DPG.use( {
		init: function ( instance ) {
			instance.demo = new DemoEditor( instance );
		},

		/**
		 * A new brief means new seed values, so drop the old editor state.
		 *
		 * @param {Object}  instance Generator instance.
		 * @param {boolean} isNew    Whether the project changed.
		 */
		onProject: function ( instance, isNew ) {
			if ( isNew && instance.demo ) {
				instance.demo.state = null;
				instance.demo.template = null;
			}
		}
	} );
}() );
