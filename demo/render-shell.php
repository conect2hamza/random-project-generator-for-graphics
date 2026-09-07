<?php
/**
 * Emit the plugin's real markup for the static demo.
 *
 * The demo does not mock up the interface: it embeds exactly what the
 * shortcode renders, together with the data wp_localize_script() would have
 * attached. Only the network layer is replaced, in demo/src/demo-app.js.
 *
 * Usage: php demo/render-shell.php > demo/.shell.json
 *
 * @package DesignProjectGenerator
 */

$plugin = __DIR__ . '/../design-project-generator/';

define( 'ABSPATH', true );
define( 'DPG_PLUGIN_DIR', $plugin );
define( 'DPG_PLUGIN_URL', 'assets/' );
define( 'DPG_PLUGIN_BASENAME', 'design-project-generator/design-project-generator.php' );
define( 'DPG_VERSION', '1.0.0' );

$GLOBALS['dpg_options']  = array();
$GLOBALS['dpg_enqueued'] = array();

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
	return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) {
	return trim( strip_tags( (string) $s ) ); }
function wp_unslash( $s ) {
	return is_string( $s ) ? stripslashes( $s ) : $s; }
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url_raw( $s ) {
	return (string) $s; }
function esc_textarea( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html_e( $s, $d = null ) {
	echo htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) {
	return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_attr_e( $s, $d = null ) {
	echo htmlspecialchars( $s, ENT_QUOTES ); }
function esc_attr__( $s, $d = null ) {
	return htmlspecialchars( $s, ENT_QUOTES ); }
function __( $s, $d = null ) {
	return $s; }
function _n( $s, $p, $n, $d = null ) {
	return 1 == $n ? $s : $p; } // phpcs:ignore Universal.Operators.StrictComparisons
function apply_filters( $t, $v ) {
	return $v; }
function wp_parse_args( $a, $d ) {
	return array_merge( $d, (array) $a ); }
function get_bloginfo( $k ) {
	return 'DesignForge demo'; }
function home_url( $p = '/' ) {
	return $p; }
function rest_url( $p = '' ) {
	return '/wp-json/' . $p; }
function wp_create_nonce( $a ) {
	return 'demo'; }
function sanitize_title( $s ) {
	return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( strip_tags( (string) $s ) ) ), '-' ); }
function selected( $a, $b, $echo = true ) {
	$r = ( (string) $a === (string) $b ) ? " selected='selected'" : '';
	if ( $echo ) {
		echo $r; }
	return $r; }
function checked( $a, $b = true, $echo = true ) {
	$r = ( (string) $a === (string) $b ) ? " checked='checked'" : '';
	if ( $echo ) {
		echo $r; }
	return $r; }
function wp_json_encode( $d, $f = 0 ) {
	return json_encode( $d, $f ); }
function wp_list_pluck( $list, $field, $index = null ) {
	$out = array();
	foreach ( $list as $i ) {
		$i = (array) $i;
		if ( null !== $index ) {
			$out[ $i[ $index ] ] = $i[ $field ] ?? null;
		} else {
			$out[] = $i[ $field ] ?? null; }
	}
	return $out; }
function shortcode_atts( $pairs, $atts, $sc = '' ) {
	$out = array();
	foreach ( $pairs as $n => $d ) {
		$out[ $n ] = array_key_exists( $n, (array) $atts ) ? $atts[ $n ] : $d; }
	return $out; }
function number_format_i18n( $n ) {
	return number_format( $n ); }
function wp_enqueue_style( $h ) {
	$GLOBALS['dpg_enqueued'][] = $h; }
function wp_enqueue_script( $h ) {
	$GLOBALS['dpg_enqueued'][] = $h; }
function wp_localize_script( $h, $n, $d ) {
	$GLOBALS['dpg_localized'][ $n ] = $d; }
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
require $plugin . 'includes/class-post-types.php';
require $plugin . 'includes/class-rest-api.php';
require $plugin . 'includes/class-admin.php';
require $plugin . 'includes/class-shortcode.php';

$atts            = DPG_Shortcode::defaults();
$atts['heading'] = 'Design Project Generator';

$markup = DPG_Shortcode::render_instance( $atts );

// The demo ships one brief pre-rendered, exactly as the shortcode does on a
// real page load. Its seed is fixed so the build is reproducible.
$stats = DPG_Project_Database::stats();

echo json_encode(
	array(
		'markup'     => $markup,
		'scriptData' => $GLOBALS['dpg_localized']['DPGData'] ?? array(),
		'stats'      => $stats,
		'combinations' => DPG_Project_Database::combination_count(),
		'templates'  => array_keys( DPG_Project_Database::demo_templates() ),
	),
	JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
