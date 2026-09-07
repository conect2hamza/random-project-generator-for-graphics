/**
 * Diff the JavaScript port against the PHP generator.
 *
 * Reads the fixture written by demo/parity-test.php and re-runs every case
 * through demo/src/engine.js and demo/src/render.js. Any disagreement in the
 * project data or in the rendered markup is a bug in the port.
 *
 * Usage: node demo/parity-test.js
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const ROOT = path.join( __dirname, '..' );
const PLUGIN = path.join( ROOT, 'design-project-generator' );
const FIXTURE = path.join( __dirname, '.parity.json' );

function readJson( name ) {
	return JSON.parse( fs.readFileSync( path.join( PLUGIN, 'data', name + '.json' ), 'utf8' ) );
}

const db = {
	projects: readJson( 'projects' ),
	industries: readJson( 'industries' ),
	styles: readJson( 'styles' ),
	palettes: readJson( 'palettes' ),
	typography: readJson( 'typography' ),
	challenges: readJson( 'challenges' ),
	restrictions: readJson( 'restrictions' ),
	hints: readJson( 'hints' ),
	templates: fs
		.readdirSync( path.join( PLUGIN, 'templates' ) )
		.filter( ( entry ) =>
			fs.existsSync( path.join( PLUGIN, 'templates', entry, 'template.html' ) )
		)
		.sort()
};

// Load the browser modules in a sandbox that gives them a window.
const sandbox = { window: {}, Math, Date, JSON, Object, Array, String, Number, parseInt, parseFloat, isNaN };

sandbox.window.Math = Math;
vm.createContext( sandbox );

for ( const file of [ 'engine.js', 'render.js' ] ) {
	vm.runInContext( fs.readFileSync( path.join( __dirname, 'src', file ), 'utf8' ), sandbox, {
		filename: file
	} );
}

const Engine = sandbox.window.DPGEngine;
const Render = sandbox.window.DPGRender;

if ( ! fs.existsSync( FIXTURE ) ) {
	console.error( 'Missing ' + FIXTURE + '. Run: php demo/parity-test.php > demo/.parity.json' );
	process.exit( 1 );
}

const fixture = JSON.parse( fs.readFileSync( FIXTURE, 'utf8' ) );

/** Collapse whitespace between tags so PHP's template indentation is ignored. */
function normaliseHtml( html ) {
	return html
		.replace( /\s+/g, ' ' )
		.replace( />\s+</g, '><' )
		.replace( /\s+>/g, '>' )
		.replace( />\s+/g, '>' )
		.replace( /\s+</g, '<' )
		.trim();
}

/** First differing path between two values, or null. */
function firstDiff( a, b, trail ) {
	trail = trail || '';

	if ( Array.isArray( a ) || Array.isArray( b ) ) {
		if ( ! Array.isArray( a ) || ! Array.isArray( b ) ) {
			return trail + ' (array vs not)';
		}

		if ( a.length !== b.length ) {
			return trail + ` (length ${ a.length } vs ${ b.length })`;
		}

		for ( let i = 0; i < a.length; i++ ) {
			const d = firstDiff( a[ i ], b[ i ], `${ trail }[${ i }]` );

			if ( d ) {
				return d;
			}
		}

		return null;
	}

	if ( a && b && typeof a === 'object' && typeof b === 'object' ) {
		const keys = new Set( [ ...Object.keys( a ), ...Object.keys( b ) ] );

		for ( const k of keys ) {
			const d = firstDiff( a[ k ], b[ k ], `${ trail }.${ k }` );

			if ( d ) {
				return d;
			}
		}

		return null;
	}

	// PHP integers arrive as numbers; keep the comparison loose on type only
	// where the string forms match exactly.
	if ( a !== b && String( a ) !== String( b ) ) {
		return `${ trail }: PHP ${ JSON.stringify( a ) } vs JS ${ JSON.stringify( b ) }`;
	}

	return null;
}

let dataFails = 0;
let htmlFails = 0;
const examples = [];

for ( const testCase of fixture.cases ) {
	const generator = new Engine.Generator( db, testCase.seed );
	const project = generator.generate( testCase.filters );

	if ( ! project ) {
		dataFails++;
		examples.push( `seed ${ testCase.seed }: JS produced nothing` );
		continue;
	}

	// PHP omits is_daily/daily_date on normal runs; so does the port.
	const diff = firstDiff( testCase.project, project, `seed ${ testCase.seed }` );

	if ( diff ) {
		dataFails++;

		if ( examples.length < 8 ) {
			examples.push( diff );
		}

		continue;
	}

	if ( normaliseHtml( testCase.html ) !== normaliseHtml( Render.project( project ) ) ) {
		htmlFails++;

		if ( examples.length < 8 ) {
			const a = normaliseHtml( testCase.html );
			const b = normaliseHtml( Render.project( project ) );
			let i = 0;

			while ( i < a.length && a[ i ] === b[ i ] ) {
				i++;
			}

			examples.push(
				`seed ${ testCase.seed } html diverges at ${ i }:\n` +
					`    PHP: ...${ a.slice( Math.max( 0, i - 40 ), i + 80 ) }\n` +
					`    JS : ...${ b.slice( Math.max( 0, i - 40 ), i + 80 ) }`
			);
		}
	}
}

let dailyFails = 0;

for ( const row of fixture.daily ) {
	const project = Engine.Generator.daily( db, row.date );
	const diff = firstDiff( row.project, project, `daily ${ row.date }` );

	if ( diff ) {
		dailyFails++;

		if ( examples.length < 10 ) {
			examples.push( diff );
		}
	}
}

const total = fixture.cases.length;

console.log( `cases compared      ${ total }` );
console.log( `project data match  ${ total - dataFails }/${ total }` );
console.log( `rendered html match ${ total - dataFails - htmlFails }/${ total - dataFails }` );
console.log( `daily challenges    ${ fixture.daily.length - dailyFails }/${ fixture.daily.length }` );

if ( examples.length ) {
	console.log( '\nfirst differences:' );
	examples.forEach( ( e ) => console.log( '  - ' + e ) );
}

const ok = dataFails === 0 && htmlFails === 0 && dailyFails === 0;

console.log( '\n' + ( ok ? 'PARITY: PASS' : 'PARITY: FAIL' ) );
process.exit( ok ? 0 : 1 );
