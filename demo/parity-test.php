<?php
/**
 * Dump PHP generator output for the parity check.
 *
 * The static demo re-implements the generator and the brief renderer in
 * JavaScript. That is only defensible if the two agree exactly, so this script
 * writes the PHP result for a spread of seeds and filter combinations and
 * demo/parity-test.js diffs the JavaScript port against it. demo/build.js runs
 * both and refuses to produce a demo when they disagree.
 *
 * Usage: php demo/parity-test.php > demo/.parity.json
 *
 * @package DesignProjectGenerator
 */

$plugin = __DIR__ . '/../design-project-generator/';

define( 'ABSPATH', true );
define( 'DPG_PLUGIN_DIR', $plugin );
define( 'DPG_PLUGIN_URL', 'https://example.test/wp-content/plugins/design-project-generator/' );
define( 'DPG_PLUGIN_BASENAME', 'design-project-generator/design-project-generator.php' );
define( 'DPG_VERSION', '1.0.0' );

$GLOBALS['dpg_options'] = array();

// Just enough WordPress to run the generator and the renderer headlessly.
function get_option( $k, $d = false ) {
	return $GLOBALS['dpg_options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) {
	$GLOBALS['dpg_options'][ $k ] = $v;
	return true; }
function wp_rand( $min = 0, $max = 0 ) {
	return random_int( $min, $max ? $max : PHP_INT_MAX ); }
function current_time( $f ) {
	return date( $f ); }
function current_user_can( $c ) {
	return false; }
function is_user_logged_in() {
	return false; }
function sanitize_text_field( $s ) {
	$s = strip_tags( (string) $s );
	return trim( preg_replace( '/\s+/', ' ', $s ) ); }
function sanitize_textarea_field( $s ) {
	return trim( strip_tags( (string) $s ) ); }
function wp_unslash( $s ) {
	return is_string( $s ) ? stripslashes( $s ) : $s; }
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html_e( $s, $d = null ) {
	echo htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) {
	return htmlspecialchars( $s, ENT_QUOTES ); }
function __( $s, $d = null ) {
	return $s; }
function _n( $s, $p, $n, $d = null ) {
	// WordPress evaluates the English plural form as ( n != 1 ), loosely.
	return 1 == $n ? $s : $p; } // phpcs:ignore Universal.Operators.StrictComparisons
function apply_filters( $t, $v ) {
	return $v; }
function wp_parse_args( $a, $d ) {
	return array_merge( $d, (array) $a ); }
function get_bloginfo( $k ) {
	return 'Demo'; }
function sanitize_title( $s ) {
	$s = strtolower( strip_tags( (string) $s ) );
	return trim( preg_replace( '/[^a-z0-9]+/', '-', $s ), '-' ); }
function add_shortcode( $t, $c ) {}
function add_action( $h, $c, $p = 10, $a = 1 ) {}
function add_filter( $h, $c, $p = 10, $a = 1 ) {}

class WP_Error {
	public $code;
	public $msg;
	public function __construct( $c = '', $m = '', $d = null ) {
		$this->code = $c;
		$this->msg  = $m; } }

function is_wp_error( $t ) {
	return $t instanceof WP_Error; }

require $plugin . 'includes/class-security.php';
require $plugin . 'includes/class-project-database.php';
require $plugin . 'includes/class-project-generator.php';
require $plugin . 'includes/class-export.php';
require $plugin . 'includes/class-rest-api.php';
require $plugin . 'includes/class-shortcode.php';

$cases = array();

// A spread of seeds with no filters at all.
for ( $seed = 1; $seed <= 120; $seed++ ) {
	$cases[] = array(
		'seed'    => $seed * 7919,
		'filters' => array(),
	);
}

// Every difficulty.
foreach ( DPG_Security::$difficulties as $index => $difficulty ) {
	for ( $i = 0; $i < 20; $i++ ) {
		$cases[] = array(
			'seed'    => 100003 + ( $index * 1000 ) + $i,
			'filters' => array( 'difficulty' => $difficulty ),
		);
	}
}

// Every category, and every project type at least once.
foreach ( DPG_Project_Database::categories() as $index => $category ) {
	$cases[] = array(
		'seed'    => 500000 + $index,
		'filters' => array( 'category' => $category['id'] ),
	);
}

foreach ( DPG_Project_Database::types() as $index => $type ) {
	$cases[] = array(
		'seed'    => 700000 + $index,
		'filters' => array( 'type' => $type['id'] ),
	);
}

// Every industry and every style.
foreach ( DPG_Project_Database::industries() as $index => $industry ) {
	$cases[] = array(
		'seed'    => 800000 + $index,
		'filters' => array( 'industry' => $industry['id'] ),
	);
}

foreach ( DPG_Project_Database::styles() as $index => $style ) {
	$cases[] = array(
		'seed'    => 900000 + $index,
		'filters' => array( 'style' => $style['id'] ),
	);
}

// Combined filters, and the demo-only path.
foreach ( array( 'branding', 'ui-ux', 'print', 'illustration' ) as $index => $category ) {
	foreach ( DPG_Security::$difficulties as $difficulty ) {
		$cases[] = array(
			'seed'    => 1100000 + ( $index * 10 ) + strlen( $difficulty ),
			'filters' => array(
				'category'   => $category,
				'difficulty' => $difficulty,
			),
		);
	}
}

for ( $i = 0; $i < 25; $i++ ) {
	$cases[] = array(
		'seed'    => 1300000 + $i,
		'filters' => array( 'demo_only' => true ),
	);
}

$out = array();

foreach ( $cases as $case ) {
	$generator = new DPG_Project_Generator( $case['seed'] );
	$project   = $generator->generate( DPG_Security::filters( $case['filters'] ) );

	if ( is_wp_error( $project ) ) {
		continue;
	}

	$out[] = array(
		'seed'    => $case['seed'],
		'filters' => $case['filters'],
		'project' => $project,
		'html'    => DPG_Shortcode::render_project_html( $project ),
	);
}

// The daily challenge for a fixed set of dates.
$daily = array();

foreach ( array( '2026-01-01', '2026-02-14', '2026-06-30', '2026-08-30', '2026-12-25' ) as $date ) {
	$project = DPG_Project_Generator::daily( $date );

	if ( ! is_wp_error( $project ) ) {
		$daily[] = array(
			'date'    => $date,
			'project' => $project,
		);
	}
}

echo wp_json_encode_compat(
	array(
		'cases' => $out,
		'daily' => $daily,
	)
);

/**
 * json_encode without depending on WordPress.
 *
 * @param mixed $data Data.
 * @return string
 */
function wp_json_encode_compat( $data ) {
	return json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}
