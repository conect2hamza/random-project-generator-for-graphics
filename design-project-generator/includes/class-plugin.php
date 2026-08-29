<?php
/**
 * Plugin bootstrap.
 *
 * @package DesignProjectGenerator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the modules together and registers the front-end assets.
 */
class DPG_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var DPG_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Sub-modules, keyed by name.
	 *
	 * @var array<string,object>
	 */
	private $modules = array();

	/**
	 * Get the shared instance.
	 *
	 * @return DPG_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ), 20 );

		$this->modules['post_types'] = new DPG_Post_Types();
		$this->modules['rest']       = new DPG_REST_API();
		$this->modules['shortcode']  = new DPG_Shortcode();
		$this->modules['block']      = new DPG_Block();
		$this->modules['export']     = new DPG_Export();

		if ( is_admin() ) {
			$this->modules['admin'] = new DPG_Admin();
		}
	}

	/**
	 * Access a module.
	 *
	 * @param string $name Module name.
	 * @return object|null
	 */
	public function module( $name ) {
		return isset( $this->modules[ $name ] ) ? $this->modules[ $name ] : null;
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'design-project-generator',
			false,
			dirname( DPG_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Register (but do not queue) the front-end assets.
	 *
	 * Nothing here loads on a page that does not contain the generator: the
	 * shortcode and the block queue what they need, when they render.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'dpg-frontend',
			DPG_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			DPG_VERSION
		);

		wp_register_script(
			'dpg-generator',
			DPG_PLUGIN_URL . 'assets/js/generator.js',
			array(),
			DPG_VERSION,
			true
		);

		wp_register_script(
			'dpg-timer',
			DPG_PLUGIN_URL . 'assets/js/timer.js',
			array( 'dpg-generator' ),
			DPG_VERSION,
			true
		);

		wp_register_script(
			'dpg-export',
			DPG_PLUGIN_URL . 'assets/js/export.js',
			array( 'dpg-generator' ),
			DPG_VERSION,
			true
		);

		wp_register_script(
			'dpg-demo-editor',
			DPG_PLUGIN_URL . 'assets/js/demo-editor.js',
			array( 'dpg-generator' ),
			DPG_VERSION,
			true
		);
	}

	/**
	 * Queue the assets in the head when the current post obviously uses the
	 * generator. Rendering through the shortcode queues them too, so this is
	 * only an optimisation that avoids footer-loaded CSS on normal pages.
	 *
	 * @return void
	 */
	public function maybe_enqueue() {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();

		if ( ! $post ) {
			return;
		}

		$uses_shortcode = has_shortcode( (string) $post->post_content, DPG_Shortcode::TAG );
		$uses_block     = function_exists( 'has_block' ) && has_block( DPG_Block::NAME, $post );

		if ( $uses_shortcode || $uses_block ) {
			wp_enqueue_style( 'dpg-frontend' );
			wp_enqueue_script( 'dpg-generator' );
		}
	}

	/**
	 * Activation: register the post types, set defaults and flush rewrites.
	 *
	 * @return void
	 */
	public static function activate() {
		$post_types = new DPG_Post_Types();
		$post_types->register();

		if ( false === get_option( DPG_Admin::SETTINGS, false ) ) {
			add_option( DPG_Admin::SETTINGS, DPG_Admin::default_settings() );
		}

		update_option( 'dpg_version', DPG_VERSION );

		flush_rewrite_rules();
	}

	/**
	 * Deactivation.
	 *
	 * Nothing is deleted: saved projects, assignments and custom data all
	 * survive so that deactivating and reactivating is harmless.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
