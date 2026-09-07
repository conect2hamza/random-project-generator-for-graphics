/**
 * Build the static demo.
 *
 * Assembles demo/index.html (a standalone page you can open from disk) and
 * demo/artifact.html (the same page shaped for publishing as an Artifact) out
 * of the plugin's real sources plus the JavaScript port of the generator.
 *
 * The build refuses to run if the port and the PHP disagree, so the demo can
 * never quietly drift away from what the plugin actually does.
 *
 * Usage: node demo/build.js
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

const ROOT = path.join( __dirname, '..' );
const PLUGIN = path.join( ROOT, 'design-project-generator' );
const SRC = path.join( __dirname, 'src' );

function read( file ) {
	return fs.readFileSync( file, 'utf8' );
}

function step( label ) {
	process.stdout.write( label.padEnd( 42 ) );
}

function done( detail ) {
	console.log( detail === undefined ? 'ok' : detail );
}

/* ------------------------------------------------------------------ *
 * 1. Parity: the port must agree with the PHP
 * ------------------------------------------------------------------ */

step( 'checking JS port against PHP' );

try {
	const fixture = execFileSync( 'php', [ path.join( __dirname, 'parity-test.php' ) ], {
		maxBuffer: 64 * 1024 * 1024
	} );

	fs.writeFileSync( path.join( __dirname, '.parity.json' ), fixture );

	const result = execFileSync( 'node', [ path.join( __dirname, 'parity-test.js' ) ], {
		encoding: 'utf8'
	} );

	const cases = ( result.match( /cases compared\s+(\d+)/ ) || [] )[ 1 ];

	done( `PASS (${ cases } cases)` );
} catch ( error ) {
	console.log( 'FAIL' );
	console.error( '\nThe JavaScript port no longer matches the PHP generator.' );
	console.error( ( error.stdout || '' ).toString() );
	process.exit( 1 );
}

/* ------------------------------------------------------------------ *
 * 2. The plugin's real markup and localized data
 * ------------------------------------------------------------------ */

step( 'rendering the shortcode through PHP' );

const shell = JSON.parse(
	execFileSync( 'php', [ path.join( __dirname, 'render-shell.php' ) ], {
		encoding: 'utf8',
		maxBuffer: 32 * 1024 * 1024
	} )
);

done( `${ ( shell.markup.length / 1024 ).toFixed( 1 ) } KB of markup` );

/* ------------------------------------------------------------------ *
 * 3. Data, templates and scripts
 * ------------------------------------------------------------------ */

step( 'collecting data and templates' );

const dataFiles = [
	'projects',
	'industries',
	'styles',
	'palettes',
	'typography',
	'challenges',
	'restrictions',
	'hints'
];

const db = { templates: shell.templates };

dataFiles.forEach( ( name ) => {
	db[ name ] = JSON.parse( read( path.join( PLUGIN, 'data', name + '.json' ) ) );
} );

const templates = {};

shell.templates.forEach( ( id ) => {
	const dir = path.join( PLUGIN, 'templates', id );
	const maybe = ( file ) =>
		fs.existsSync( path.join( dir, file ) ) ? read( path.join( dir, file ) ) : '';

	templates[ id ] = {
		html: read( path.join( dir, 'template.html' ) ),
		css: maybe( 'template.css' ),
		js: maybe( 'template.js' )
	};
} );

done( `${ dataFiles.length } data files, ${ Object.keys( templates ).length } templates` );

step( 'bundling the plugin front end' );

// The plugin's own scripts, verbatim and in dependency order.
const pluginJs = [ 'generator.js', 'timer.js', 'export.js', 'demo-editor.js' ]
	.map( ( file ) => read( path.join( PLUGIN, 'assets', 'js', file ) ) )
	.join( '\n\n' );

const pluginCss = read( path.join( PLUGIN, 'assets', 'css', 'frontend.css' ) );

done( `${ ( pluginJs.length / 1024 ).toFixed( 0 ) } KB js, ${ ( pluginCss.length / 1024 ).toFixed( 0 ) } KB css` );

/* ------------------------------------------------------------------ *
 * 4. Assemble
 * ------------------------------------------------------------------ */

step( 'assembling the page' );

const version = ( read( path.join( PLUGIN, 'design-project-generator.php' ) ).match(
	/^\s*\*\s*Version:\s*(.+)$/m
) || [ '', '1.0.0' ] )[ 1 ].trim();

const bundle = {
	scriptData: shell.scriptData,
	db,
	templates,
	stats: shell.stats
};

// The data block sits inside a <script type="application/json">, so the only
// sequence that could break out of it is a literal closing script tag.
const dataJson = JSON.stringify( bundle ).replace( /<\//g, '<\\/' );

const replacements = {
	VERSION: version,
	CHROME_CSS: read( path.join( SRC, 'chrome.css' ) ),
	PLUGIN_CSS: pluginCss,
	MARKUP: shell.markup,
	DATA: dataJson,
	ENGINE_JS: read( path.join( SRC, 'engine.js' ) ),
	RENDER_JS: read( path.join( SRC, 'render.js' ) ),
	DEMO_APP_JS: read( path.join( SRC, 'demo-app.js' ) ),
	PLUGIN_JS: pluginJs,
	CHROME_JS: read( path.join( SRC, 'demo-chrome.js' ) ),
	TYPES: shell.stats.types,
	INDUSTRIES: shell.stats.industries,
	STYLES: shell.stats.styles,
	PALETTES: shell.stats.palettes,
	CHALLENGES: shell.stats.challenges,
	TEMPLATES: shell.stats.templates,
	COMBINATIONS: ( shell.combinations / 1000000 ).toFixed( 1 ) + 'M'
};

let page = read( path.join( SRC, 'page.html' ) );

Object.keys( replacements ).forEach( ( token ) => {
	// A plain split/join, so a $-sign inside the replacement is never treated
	// as a regular-expression backreference.
	page = page.split( '{{' + token + '}}' ).join( String( replacements[ token ] ) );
} );

const leftover = page.match( /\{\{[A-Z_]+\}\}/g );

if ( leftover ) {
	console.log( 'FAIL' );
	console.error( 'Unreplaced tokens: ' + leftover.join( ', ' ) );
	process.exit( 1 );
}

const SPLIT = '<!--SPLIT-->';

if ( page.indexOf( SPLIT ) === -1 ) {
	console.log( 'FAIL' );
	console.error( 'page.html is missing its ' + SPLIT + ' marker.' );
	process.exit( 1 );
}

const head = page.slice( 0, page.indexOf( SPLIT ) ).trim();
const body = page.slice( page.indexOf( SPLIT ) + SPLIT.length ).trim();

// The Artifact host supplies <!doctype>, <html>, <head> and <body>, so the
// two halves simply run together.
fs.writeFileSync( path.join( __dirname, 'artifact.html' ), head + '\n\n' + body + '\n' );

// The standalone file needs its own document wrapper.
fs.writeFileSync(
	path.join( __dirname, 'index.html' ),
	[
		'<!DOCTYPE html>',
		'<html lang="en">',
		'<head>',
		'<meta charset="utf-8">',
		'<meta name="viewport" content="width=device-width, initial-scale=1">',
		head,
		'</head>',
		'<body>',
		body,
		'</body>',
		'</html>',
		''
	].join( '\n' )
);

done( `${ ( page.length / 1024 ).toFixed( 0 ) } KB` );

console.log( '\nwrote demo/index.html   (open directly in a browser)' );
console.log( 'wrote demo/artifact.html (publish as an Artifact)' );
