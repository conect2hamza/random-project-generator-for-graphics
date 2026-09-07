/**
 * JavaScript port of DPG_Project_Generator.
 *
 * This exists so the static demo can generate briefs with no PHP behind it.
 * It is a deliberate line-by-line port, not a reimplementation: the draw order
 * and every rounding rule match the PHP so that a given seed produces the same
 * brief in both. demo/parity-test.php checks that claim across hundreds of
 * seeds and filter combinations, and the build refuses to run if it fails.
 *
 * Read the PHP first if you are changing anything here:
 * includes/class-project-generator.php
 */

( function () {
	'use strict';

	var DIFFICULTIES = [ 'beginner', 'intermediate', 'advanced', 'expert' ];

	var DELIVERABLE_COUNTS = {
		beginner: [ 1, 1 ],
		intermediate: [ 2, 3 ],
		advanced: [ 3, 5 ],
		expert: [ 4, 7 ]
	};

	var RESTRICTION_COUNTS = {
		beginner: [ 1, 2 ],
		intermediate: [ 3, 3 ],
		advanced: [ 3, 4 ],
		expert: [ 4, 5 ]
	};

	var TIME_MULTIPLIER = {
		beginner: 0.6,
		intermediate: 1.0,
		advanced: 1.6,
		expert: 2.4
	};

	var CATEGORY_TAGS = {
		'branding': [ 'brand', 'colour', 'type', 'production', 'flexibility', 'legibility' ],
		'social-media': [ 'digital', 'copy', 'colour', 'flexibility', 'legibility' ],
		'marketing': [ 'print', 'copy', 'legibility', 'colour', 'layout' ],
		'ui-ux': [ 'digital', 'accessibility', 'layout', 'type', 'flexibility' ],
		'web-design': [ 'digital', 'accessibility', 'layout', 'colour', 'flexibility' ],
		'typography': [ 'type', 'layout', 'legibility', 'colour' ],
		'illustration': [ 'style', 'colour', 'production', 'layout' ],
		'print': [ 'print', 'production', 'type', 'colour', 'legibility' ]
	};

	var CHALLENGE_PREFERENCE = {
		beginner: [ 'timer', 'color', 'minimalism' ],
		intermediate: [ 'timer', 'color', 'typography', 'layout' ],
		advanced: [ 'layout', 'typography', 'concept', 'constraint' ],
		expert: [ 'concept', 'constraint', 'layout' ]
	};

	var SUCCESS_COUNTS = {
		beginner: 3,
		intermediate: 4,
		advanced: 5,
		expert: 6
	};

	var ALWAYS_CRITERION = 'Every restriction in the brief is respected.';

	var COLOR_OPENERS = [
		'%s. Use the accent on one element only.',
		'%s. Keep the background dominant and let the accent do the pointing.',
		'%s, applied roughly 60 / 30 / 10.',
		'%s. Substitutions are allowed if you can justify them.'
	];

	var SMALL_WORDS = [ 'a', 'an', 'and', 'the', 'of', 'for', 'to', 'in', 'on', 'with' ];

	/* ------------------------------------------------------------------ *
	 * Helpers that mirror the PHP side exactly
	 * ------------------------------------------------------------------ */

	function ucfirst( value ) {
		value = String( value == null ? '' : value );

		return value.charAt( 0 ).toUpperCase() + value.slice( 1 );
	}

	/** PHP str_word_count: runs of letters, apostrophes and hyphens. */
	function wordCount( text ) {
		var matches = String( text ).match( /[A-Za-z'-]+/g );

		return matches ? matches.length : 0;
	}

	/**
	 * Correct the indefinite article after token substitution.
	 * Mirrors DPG_Project_Generator::articles().
	 *
	 * @param {string} text Text with tokens already replaced.
	 * @return {string}
	 */
	function articles( text ) {
		text = text.replace( /\ba (?=(?!one\b|once\b|eu)[aeio])/g, 'an ' );
		text = text.replace( /\ban (?=[^aeiou\s])/g, 'a ' );

		return text;
	}

	/** PHP str_pad( x, 5, '0', STR_PAD_LEFT ). */
	function padLeft( value, length ) {
		value = String( value );

		while ( value.length < length ) {
			value = '0' + value;
		}

		return value;
	}

	/** DPG_Security::key(). */
	function key( value ) {
		if ( value == null || typeof value === 'object' ) {
			return '';
		}

		return String( value ).toLowerCase().replace( /[^a-z0-9_-]/g, '' ).slice( 0, 64 );
	}

	/** DPG_Security::hex(). */
	function hex( value, fallback ) {
		if ( typeof value === 'string' ) {
			var match = value.trim().match( /^#?([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/ );

			if ( match ) {
				var digits = match[ 1 ].toLowerCase();

				if ( digits.length === 3 ) {
					digits = digits[ 0 ] + digits[ 0 ] + digits[ 1 ] + digits[ 1 ] + digits[ 2 ] + digits[ 2 ];
				}

				return '#' + digits;
			}
		}

		return fallback;
	}

	/** DPG_Security::palette(). */
	function normalisePalette( palette ) {
		var defaults = {
			primary: '#1f2937',
			secondary: '#4b5563',
			accent: '#f59e0b',
			background: '#f9fafb',
			text: '#111827'
		};

		palette = palette || {};

		var source = palette.colors || palette;
		var colors = {};

		Object.keys( defaults ).forEach( function ( role ) {
			colors[ role ] = hex( source[ role ], defaults[ role ] );
		} );

		return {
			id: key( palette.id || '' ),
			name: String( palette.name || '' ),
			colors: colors
		};
	}

	/** DPG_Security::challenge(). */
	function normaliseChallenge( challenge ) {
		var types = [ 'timer', 'color', 'typography', 'layout', 'minimalism', 'concept', 'constraint' ];

		challenge = challenge || {};

		var type = key( challenge.type );

		return {
			id: key( challenge.id ),
			type: types.indexOf( type ) === -1 ? 'concept' : type,
			name: String( challenge.name || '' ),
			prompt: String( challenge.prompt || '' ),
			duration: Math.max( 0, Math.min( 86400, parseInt( challenge.duration, 10 ) || 0 ) )
		};
	}

	/* ------------------------------------------------------------------ *
	 * The generator
	 * ------------------------------------------------------------------ */

	/**
	 * @param {Object} db   The bundled data files, already parsed.
	 * @param {number} seed Optional seed; a random one is chosen when omitted.
	 * @constructor
	 */
	function Generator( db, seed ) {
		this.db = db;

		seed = parseInt( seed, 10 ) || 0;

		if ( seed <= 0 ) {
			seed = 1 + Math.floor( Math.random() * 2147483645 );
		}

		this.seed = seed;
		this.state = seed % 2147483647;

		if ( this.state <= 0 ) {
			this.state += 2147483646;
		}
	}

	/** Lehmer generator via Schrage's method, as in the PHP. */
	Generator.prototype.next = function () {
		var hi = Math.floor( this.state / 127773 );
		var lo = this.state % 127773;

		this.state = ( 16807 * lo ) - ( 2836 * hi );

		if ( this.state <= 0 ) {
			this.state += 2147483647;
		}

		return this.state;
	};

	Generator.prototype.range = function ( min, max ) {
		min = min | 0;
		max = max | 0;

		if ( max <= min ) {
			return min;
		}

		return min + ( this.next() % ( max - min + 1 ) );
	};

	Generator.prototype.pick = function ( items ) {
		items = Array.isArray( items ) ? items : [];

		if ( ! items.length ) {
			return null;
		}

		return items[ this.range( 0, items.length - 1 ) ];
	};

	Generator.prototype.shuffle = function ( items ) {
		items = ( Array.isArray( items ) ? items : [] ).slice();

		for ( var i = items.length - 1; i > 0; i-- ) {
			var j = this.range( 0, i );
			var tmp = items[ i ];

			items[ i ] = items[ j ];
			items[ j ] = tmp;
		}

		return items;
	};

	Generator.prototype.sample = function ( items, count ) {
		return this.shuffle( items ).slice( 0, Math.max( 0, count | 0 ) );
	};

	/* ---- data access, mirroring DPG_Project_Database ---- */

	Generator.prototype.categories = function () {
		return this.db.projects.categories;
	};

	Generator.prototype.types = function ( categoryId ) {
		var out = [];

		this.categories().forEach( function ( category ) {
			if ( categoryId && category.id !== categoryId ) {
				return;
			}

			category.types.forEach( function ( type ) {
				var copy = Object.assign( {}, type );

				copy.category_id = category.id;
				copy.category_name = category.name;
				out.push( copy );
			} );
		} );

		return out;
	};

	Generator.prototype.pool = function ( name ) {
		return this.db.styles[ name ] || [];
	};

	/* ---- generation ---- */

	Generator.prototype.applyMode = function ( filters, previous ) {
		var mode = filters.mode || 'random';

		if ( mode === 'random' || ! previous ) {
			return filters;
		}

		if ( mode === 'same-industry' ) {
			filters.industry = previous.industry_id || filters.industry;
		} else if ( mode === 'same-difficulty' ) {
			filters.difficulty = previous.difficulty || filters.difficulty;
		} else if ( mode === 'same-type' ) {
			filters.type = previous.project_type_id || filters.type;
		} else if ( mode === 'same-category' ) {
			filters.category = previous.category_id || filters.category;
		} else if ( mode === 'harder' || mode === 'easier' ) {
			var index = DIFFICULTIES.indexOf( previous.difficulty || 'beginner' );

			index = index === -1 ? 0 : index;
			index += ( mode === 'harder' ) ? 1 : -1;
			index = Math.max( 0, Math.min( DIFFICULTIES.length - 1, index ) );

			filters.difficulty = DIFFICULTIES[ index ];
		}

		return filters;
	};

	Generator.prototype.chooseType = function ( filters ) {
		var types = this.types( filters.category );

		if ( filters.demo_only && ! filters.type ) {
			types = types.filter( function ( type ) {
				return type.demo && type.demo !== 'none';
			} );
		}

		// Draw first, then honour an explicit type, so the draw happens either
		// way and the seed stays reproducible.
		var chosen = this.pick( types );

		if ( filters.type ) {
			for ( var i = 0; i < types.length; i++ ) {
				if ( types[ i ].id === filters.type ) {
					return types[ i ];
				}
			}

			return null;
		}

		return chosen;
	};

	Generator.prototype.choose = function ( records, id ) {
		var chosen = this.pick( records );

		if ( id ) {
			for ( var i = 0; i < records.length; i++ ) {
				if ( records[ i ].id === id ) {
					return records[ i ];
				}
			}
		}

		return chosen;
	};

	Generator.prototype.choosePalette = function ( style ) {
		var palettes = this.db.palettes.palettes;
		var wanted = ( style && style.palette_tags ) || [];

		if ( wanted.length ) {
			var matching = palettes.filter( function ( palette ) {
				var tags = palette.tags || [];

				return tags.some( function ( tag ) {
					return wanted.indexOf( tag ) !== -1;
				} );
			} );

			// PHP short-circuits: the range() draw only happens when there is
			// something to match against.
			if ( matching.length && this.range( 0, 100 ) < 85 ) {
				return this.pick( matching );
			}
		}

		return this.pick( palettes );
	};

	Generator.prototype.titleCase = function ( text ) {
		var words = String( text ).trim().split( /\s+/ );

		return words.map( function ( word, index ) {
			var lower = word.toLowerCase();

			return ( index > 0 && SMALL_WORDS.indexOf( lower ) !== -1 ) ? lower : ucfirst( lower );
		} ).join( ' ' );
	};

	Generator.prototype.title = function ( type, product ) {
		product = String( product || '' ).trim();

		if ( product === '' ) {
			return type.name;
		}

		var label = this.titleCase( product );
		var patterns = [
			label + ' — ' + type.name,
			type.name + ': ' + label,
			label + ' ' + type.name
		];

		if ( wordCount( label ) + wordCount( type.name ) > 5 ) {
			patterns.pop();
		}

		return this.pick( patterns );
	};

	Generator.prototype.fill = function ( text, tokens ) {
		text = String( text == null ? '' : text );

		Object.keys( tokens ).forEach( function ( token ) {
			text = text.split( token ).join( tokens[ token ] );
		} );

		return articles( text.trim() );
	};

	Generator.prototype.sentence = function ( text ) {
		text = String( text || '' ).trim();

		if ( text === '' ) {
			return '';
		}

		text = ucfirst( text );

		if ( ! /[.!?]$/.test( text ) ) {
			text += '.';
		}

		return text;
	};

	Generator.prototype.colorDirection = function ( palette ) {
		var name = ( palette && palette.name ) ? palette.name : 'Designer’s choice';

		return this.pick( COLOR_OPENERS ).replace( '%s', name );
	};

	Generator.prototype.typographyDirection = function ( pairing ) {
		if ( ! pairing || ! pairing.heading || ! pairing.heading.label ) {
			return 'Designer’s choice, but no more than two typefaces.';
		}

		return pairing.heading.label + ' for headings with ' +
			pairing.body.label.toLowerCase() + ' for supporting copy.';
	};

	Generator.prototype.estimatedTime = function ( type, difficulty ) {
		var base = parseInt( type.base_time, 10 ) || 60;
		var multiplier = TIME_MULTIPLIER[ difficulty ] != null ? TIME_MULTIPLIER[ difficulty ] : 1.0;

		return Math.max( 15, Math.round( ( base * multiplier ) / 15 ) * 15 );
	};

	Generator.prototype.demoType = function ( type ) {
		var demo = key( type.demo || 'none' );

		if ( demo === '' || demo === 'none' ) {
			return 'none';
		}

		return this.db.templates.indexOf( demo ) === -1 ? 'none' : demo;
	};

	Generator.prototype.deliverables = function ( type, difficulty ) {
		var base = type.deliverables || [];
		var extras = type.extras || [];
		var bounds = DELIVERABLE_COUNTS[ difficulty ] || [ 2, 3 ];
		var want = this.range( bounds[ 0 ], bounds[ 1 ] );
		var out = base.slice( 0, want );

		if ( out.length < want && extras.length ) {
			out = out.concat( this.sample( extras, want - out.length ) );
		}

		// array_unique keeps the first occurrence.
		return out.filter( function ( item, index ) {
			return out.indexOf( item ) === index;
		} );
	};

	Generator.prototype.restrictions = function ( type, difficulty ) {
		var pool = this.db.restrictions.restrictions;
		var category = type.category_id;
		var affinity = CATEGORY_TAGS[ category ] || [];
		var bounds = RESTRICTION_COUNTS[ difficulty ] || [ 2, 3 ];
		var want = this.range( bounds[ 0 ], bounds[ 1 ] );

		if ( category === 'illustration' ) {
			pool = pool.filter( function ( item ) {
				return ( item.tags || [] ).indexOf( 'image' ) === -1;
			} );
		}

		var relevant = [];
		var rest = [];

		this.shuffle( pool ).forEach( function ( item ) {
			var tags = item.tags || [];
			var overlaps = affinity.length && tags.some( function ( tag ) {
				return affinity.indexOf( tag ) !== -1;
			} );

			if ( overlaps ) {
				relevant.push( item );
			} else {
				rest.push( item );
			}
		} );

		var ordered = relevant.concat( rest );
		var out = [];
		var used = {};

		for ( var i = 0; i < ordered.length && out.length < want; i++ ) {
			var item = ordered[ i ];
			var tag = ( item.tags && item.tags[ 0 ] ) ? item.tags[ 0 ] : 'general';

			if ( used[ tag ] >= 2 ) {
				continue;
			}

			used[ tag ] = ( used[ tag ] || 0 ) + 1;
			out.push( item.text );
		}

		return out;
	};

	Generator.prototype.content = function ( type, difficulty ) {
		var items = type.content || [];

		if ( ! items.length ) {
			return [];
		}

		if ( difficulty === 'beginner' ) {
			return items.slice( 0, 2 );
		}

		if ( difficulty === 'intermediate' ) {
			return items.slice( 0, Math.max( 3, Math.ceil( items.length * 0.75 ) ) );
		}

		return items.slice();
	};

	Generator.prototype.successCriteria = function ( difficulty ) {
		var want = SUCCESS_COUNTS[ difficulty ] || 4;
		var pool = this.db.restrictions.success_criteria.filter( function ( item ) {
			return item.toLowerCase().indexOf( 'restrictions are all respected' ) === -1;
		} );

		return [ ALWAYS_CRITERION ].concat( this.sample( pool, want - 1 ) );
	};

	Generator.prototype.challenge = function ( difficulty ) {
		var pool = this.db.challenges.challenges;

		if ( ! pool.length ) {
			return normaliseChallenge( {} );
		}

		var wanted = CHALLENGE_PREFERENCE[ difficulty ] || [];
		var filtered = pool.filter( function ( item ) {
			return ! wanted.length || wanted.indexOf( item.type ) !== -1;
		} );

		return normaliseChallenge( this.pick( filtered.length ? filtered : pool ) );
	};

	Generator.prototype.projectId = function () {
		return 'DG-' + padLeft( this.seed % 100000, 5 );
	};

	/**
	 * Generate one project.
	 *
	 * The order of draws below is load-bearing — see the note in the PHP.
	 *
	 * @param {Object} filters  Filter values.
	 * @param {Object} previous Previous project, for the "same as" modes.
	 * @return {Object|null} The project, or null when nothing matches.
	 */
	Generator.prototype.generate = function ( filters, previous ) {
		filters = Object.assign( {
			category: '',
			type: '',
			industry: '',
			style: '',
			difficulty: '',
			mode: 'random',
			demo_only: false
		}, filters || {} );

		filters = this.applyMode( filters, previous );

		var type = this.chooseType( filters );

		if ( ! type ) {
			return null;
		}

		var difficulty = this.pick( DIFFICULTIES );

		difficulty = filters.difficulty ? filters.difficulty : difficulty;

		var industry = this.choose( this.db.industries.industries, filters.industry );
		var style = this.choose( this.db.styles.styles, filters.style );
		var palette = this.choosePalette( style );
		var typography = this.pick( this.db.typography.pairings );

		if ( ! industry || ! style ) {
			return null;
		}

		var client = String( this.pick( industry.clients ) || '' );
		var product = String( this.pick( industry.products || [] ) || '' );

		var tokens = {
			'{client}': client,
			'{product}': product,
			'{industry}': industry.name.toLowerCase()
		};

		var audience = ( this.range( 0, 100 ) < 92 && ( industry.audiences || [] ).length )
			? String( this.pick( industry.audiences ) )
			: String( this.pick( this.pool( 'audiences' ) ) );

		var objective = ( this.range( 0, 100 ) < 80 && ( industry.objectives || [] ).length )
			? String( this.pick( industry.objectives ) )
			: String( this.pick( this.pool( 'objectives' ) ) );

		var background = String( this.pick( industry.contexts || [] ) || '' );

		var project = {
			id: this.projectId(),
			seed: this.seed,
			title: this.title( type, product ),
			category: type.category_name,
			category_id: type.category_id,
			project_type: type.name,
			project_type_id: type.id,
			industry: industry.name,
			industry_id: industry.id,
			difficulty: difficulty,
			client: client,
			product: product,
			background: this.fill( background, tokens ),
			objective: this.sentence( this.fill( objective, tokens ) ),
			audience: audience,
			style: style.name,
			style_id: style.id,
			style_direction: style.direction || '',
			color_direction: this.colorDirection( palette ),
			typography: this.typographyDirection( typography ),
			typography_note: typography && typography.note ? typography.note : '',
			palette: normalisePalette( palette ),
			estimated_time: this.estimatedTime( type, difficulty ),
			demo_type: this.demoType( type ),
			deliverables: this.deliverables( type, difficulty ),
			restrictions: this.restrictions( type, difficulty ),
			content: this.content( type, difficulty ),
			success: this.successCriteria( difficulty ),
			challenge: this.challenge( difficulty ),
			tags: [ type.category_id, type.id, industry.id, style.id, difficulty ],
			personality: '',
			competitors: [],
			budget: '',
			deadline: ''
		};

		if ( difficulty === 'advanced' || difficulty === 'expert' ) {
			project.personality = String( this.pick( this.pool( 'personalities' ) ) || '' );
			project.competitors = this.sample(
				industry.competitors || [],
				difficulty === 'expert' ? 3 : 2
			);
		}

		if ( difficulty === 'expert' ) {
			project.budget = String( this.pick( this.pool( 'budgets' ) ) || '' );
			project.deadline = String( this.pick( this.pool( 'deadlines' ) ) || '' );
		}

		project.typography_note = this.fill( project.typography_note, tokens );

		return project;
	};

	/**
	 * The deterministic daily challenge.
	 *
	 * @param {Object} db   Data files.
	 * @param {string} date Y-m-d.
	 * @return {Object|null}
	 */
	Generator.daily = function ( db, date ) {
		var seed = Math.abs( crc32( 'dpg-daily-' + date ) ) % 2147483646;

		seed = seed > 0 ? seed : 1;

		var dayOfYear = Math.floor(
			( Date.UTC( +date.slice( 0, 4 ), +date.slice( 5, 7 ) - 1, +date.slice( 8, 10 ) ) -
				Date.UTC( +date.slice( 0, 4 ), 0, 1 ) ) / 86400000
		);

		var project = new Generator( db, seed ).generate( {
			difficulty: DIFFICULTIES[ dayOfYear % 3 ]
		} );

		if ( project ) {
			project.is_daily = true;
			project.daily_date = date;
		}

		return project;
	};

	var CRC_TABLE = null;

	function crc32( text ) {
		if ( ! CRC_TABLE ) {
			CRC_TABLE = [];

			for ( var n = 0; n < 256; n++ ) {
				var c = n;

				for ( var k = 0; k < 8; k++ ) {
					c = ( c & 1 ) ? ( 0xEDB88320 ^ ( c >>> 1 ) ) : ( c >>> 1 );
				}

				CRC_TABLE[ n ] = c >>> 0;
			}
		}

		var crc = 0xFFFFFFFF;

		for ( var i = 0; i < text.length; i++ ) {
			crc = ( crc >>> 8 ) ^ CRC_TABLE[ ( crc ^ text.charCodeAt( i ) ) & 0xFF ];
		}

		return ( crc ^ 0xFFFFFFFF ) >>> 0;
	}

	window.DPGEngine = {
		Generator: Generator,
		DIFFICULTIES: DIFFICULTIES,
		normalisePalette: normalisePalette
	};
}() );
