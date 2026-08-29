<?php
/**
 * Gutenberg block.
 *
 * The block renders through the same PHP template as the shortcode, so both
 * routes into the plugin produce identical markup and identical escaping.
 *
 * @package DesignProjectGenerator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Block registration.
 */
class DPG_Block {

	/**
	 * Block name.
	 */
	const NAME = 'dpg/generator';

	/**
	 * Hook into WordPress.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ), 20 );
	}

	/**
	 * Block attribute schema.
	 *
	 * @return array
	 */
	public static function attributes() {
		return array(
			'heading'     => array(
				'type'    => 'string',
				'default' => '',
			),
			'tagline'     => array(
				'type'    => 'string',
				'default' => '',
			),
			'category'    => array(
				'type'    => 'string',
				'default' => '',
			),
			'difficulty'  => array(
				'type'    => 'string',
				'default' => '',
			),
			'daily'       => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'demoOnly'    => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'showFilters' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showTimer'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showDemo'    => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showExport'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showSave'    => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showPalette' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showHints'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'teacher'     => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}

	/**
	 * Register the block type and its editor assets.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'dpg-block',
			DPG_PLUGIN_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			DPG_VERSION,
			true
		);

		wp_register_style(
			'dpg-editor',
			DPG_PLUGIN_URL . 'assets/css/editor.css',
			array(),
			DPG_VERSION
		);

		wp_localize_script(
			'dpg-block',
			'DPGBlockData',
			array(
				'categories'   => wp_list_pluck( DPG_Project_Database::categories(), 'name', 'id' ),
				'difficulties' => array_combine(
					DPG_Security::$difficulties,
					array_map( array( 'DPG_REST_API', 'difficulty_label' ), DPG_Security::$difficulties )
				),
			)
		);

		register_block_type(
			self::NAME,
			array(
				'api_version'     => 2,
				'title'           => __( 'Design Project Generator', 'design-project-generator' ),
				'description'     => __( 'Generate randomised design briefs with challenges, demos and exports.', 'design-project-generator' ),
				'category'        => 'widgets',
				'icon'            => 'art',
				'keywords'        => array( 'design', 'brief', 'generator', 'practice', 'portfolio' ),
				'attributes'      => self::attributes(),
				'supports'        => array(
					'html'   => false,
					'align'  => array( 'wide', 'full' ),
					'anchor' => true,
				),
				'editor_script'   => 'dpg-block',
				'editor_style'    => 'dpg-editor',
				'style'           => 'dpg-frontend',
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Server-side render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$attributes = wp_parse_args(
			is_array( $attributes ) ? $attributes : array(),
			wp_list_pluck( self::attributes(), 'default' )
		);

		$yes_no = static function ( $value ) {
			return $value ? 'yes' : 'no';
		};

		$atts = array(
			'heading'      => DPG_Security::text( $attributes['heading'], 120 ),
			'tagline'      => DPG_Security::text( $attributes['tagline'], 200 ),
			'category'     => DPG_Security::key( $attributes['category'] ),
			'difficulty'   => DPG_Security::one_of( $attributes['difficulty'], DPG_Security::$difficulties, '' ),
			'daily'        => $yes_no( $attributes['daily'] ),
			'demo_only'    => $yes_no( $attributes['demoOnly'] ),
			'show_filters' => $yes_no( $attributes['showFilters'] ),
			'show_timer'   => $yes_no( $attributes['showTimer'] ),
			'show_demo'    => $yes_no( $attributes['showDemo'] ),
			'show_export'  => $yes_no( $attributes['showExport'] ),
			'show_save'    => $yes_no( $attributes['showSave'] ),
			'show_palette' => $yes_no( $attributes['showPalette'] ),
			'show_hints'   => $yes_no( $attributes['showHints'] ),
			'teacher'      => $yes_no( $attributes['teacher'] ),
		);

		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : '';

		return '<div ' . $wrapper . '>' . DPG_Shortcode::render_instance( $atts ) . '</div>';
	}
}
