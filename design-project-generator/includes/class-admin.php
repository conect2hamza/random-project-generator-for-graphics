<?php
/**
 * Admin screens.
 *
 * Every bundled record can be switched off and every dataset can be extended,
 * so a site owner can shape the generator for their own audience without
 * editing the JSON files (which would be overwritten on update).
 *
 * @package DesignProjectGenerator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin menu, data editors, settings and tools.
 */
class DPG_Admin {

	/**
	 * Top-level menu slug.
	 */
	const MENU = 'dpg-dashboard';

	/**
	 * Settings option name.
	 */
	const SETTINGS = 'dpg_settings';

	/**
	 * Hook into WordPress.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'handle_post' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . DPG_PLUGIN_BASENAME, array( $this, 'action_links' ) );
		add_filter( 'manage_' . DPG_Post_Types::PROJECT . '_posts_columns', array( $this, 'project_columns' ) );
		add_action( 'manage_' . DPG_Post_Types::PROJECT . '_posts_custom_column', array( $this, 'project_column' ), 10, 2 );
		add_filter( 'manage_' . DPG_Post_Types::ASSIGNMENT . '_posts_columns', array( $this, 'assignment_columns' ) );
		add_action( 'manage_' . DPG_Post_Types::ASSIGNMENT . '_posts_custom_column', array( $this, 'assignment_column' ), 10, 2 );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'default_category'   => '',
			'default_difficulty' => '',
			'guest_saving'       => 1,
			'daily_challenge'    => 1,
			'pdf_size'           => 'a4',
			'png_width'          => 1920,
			'png_quality'        => 'standard',
		);
	}

	/**
	 * Current settings.
	 *
	 * @return array
	 */
	public static function settings() {
		$stored = get_option( self::SETTINGS, array() );

		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::default_settings() );
	}

	/**
	 * Register the admin menu.
	 *
	 * @return void
	 */
	public function menu() {
		$cap = DPG_Security::MANAGE_CAP;

		add_menu_page(
			__( 'Design Project Generator', 'design-project-generator' ),
			__( 'Design Projects', 'design-project-generator' ),
			$cap,
			self::MENU,
			array( $this, 'screen_dashboard' ),
			'dashicons-art',
			57
		);

		$pages = array(
			self::MENU          => __( 'Dashboard', 'design-project-generator' ),
			'dpg-categories'    => __( 'Categories', 'design-project-generator' ),
			'dpg-industries'    => __( 'Industries', 'design-project-generator' ),
			'dpg-styles'        => __( 'Styles', 'design-project-generator' ),
			'dpg-palettes'      => __( 'Colour Palettes', 'design-project-generator' ),
			'dpg-challenges'    => __( 'Challenges', 'design-project-generator' ),
			'dpg-templates'     => __( 'Demo Templates', 'design-project-generator' ),
			'dpg-settings'      => __( 'Settings', 'design-project-generator' ),
			'dpg-tools'         => __( 'Tools', 'design-project-generator' ),
		);

		$callbacks = array(
			self::MENU       => 'screen_dashboard',
			'dpg-categories' => 'screen_categories',
			'dpg-industries' => 'screen_industries',
			'dpg-styles'     => 'screen_styles',
			'dpg-palettes'   => 'screen_palettes',
			'dpg-challenges' => 'screen_challenges',
			'dpg-templates'  => 'screen_templates',
			'dpg-settings'   => 'screen_settings',
			'dpg-tools'      => 'screen_tools',
		);

		foreach ( $pages as $slug => $label ) {
			add_submenu_page(
				self::MENU,
				$label,
				$label,
				$cap,
				$slug,
				array( $this, $callbacks[ $slug ] )
			);
		}

		// The two post-type lists sit in the same menu, between the data
		// screens, so everything to do with the plugin is in one place.
		add_submenu_page(
			self::MENU,
			__( 'Saved Projects', 'design-project-generator' ),
			__( 'Projects', 'design-project-generator' ),
			$cap,
			'edit.php?post_type=' . DPG_Post_Types::PROJECT
		);

		add_submenu_page(
			self::MENU,
			__( 'Assignments', 'design-project-generator' ),
			__( 'Assignments', 'design-project-generator' ),
			DPG_Security::TEACH_CAP,
			'edit.php?post_type=' . DPG_Post_Types::ASSIGNMENT
		);
	}

	/**
	 * Add a settings link on the plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=dpg-settings' ) ) . '">' . esc_html__( 'Settings', 'design-project-generator' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Queue admin assets on the plugin screens only.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'dpg-' ) && false === strpos( (string) $hook, self::MENU ) ) {
			return;
		}

		wp_enqueue_style( 'dpg-admin', DPG_PLUGIN_URL . 'assets/css/admin.css', array(), DPG_VERSION );
		wp_enqueue_script( 'dpg-admin', DPG_PLUGIN_URL . 'assets/js/admin.js', array(), DPG_VERSION, true );
	}

	/**
	 * Extra columns on the saved projects list.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function project_columns( $columns ) {
		$out = array();

		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;

			if ( 'title' === $key ) {
				$out['dpg_difficulty'] = __( 'Difficulty', 'design-project-generator' );
				$out['dpg_status']     = __( 'Status', 'design-project-generator' );
			}
		}

		return $out;
	}

	/**
	 * Render the extra columns.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post identifier.
	 * @return void
	 */
	public function project_column( $column, $post_id ) {
		$row = DPG_Post_Types::read_project( $post_id );

		if ( ! $row ) {
			return;
		}

		if ( 'dpg_difficulty' === $column ) {
			echo esc_html( DPG_REST_API::difficulty_label( $row['project']['difficulty'] ) );
		}

		if ( 'dpg_status' === $column ) {
			echo esc_html( self::status_label( $row['status'] ) );
		}
	}

	/**
	 * Extra columns on the assignments list.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function assignment_columns( $columns ) {
		$out = array();

		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;

			if ( 'title' === $key ) {
				$out['dpg_brief']  = __( 'Briefs', 'design-project-generator' );
				$out['dpg_export'] = __( 'Export', 'design-project-generator' );
			}
		}

		return $out;
	}

	/**
	 * Render the assignment columns, including the download link.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post identifier.
	 * @return void
	 */
	public function assignment_column( $column, $post_id ) {
		$assignment = DPG_Post_Types::read_assignment( $post_id );

		if ( ! $assignment ) {
			return;
		}

		if ( 'dpg_brief' === $column ) {
			$settings = $assignment['settings'];
			$bits     = array();

			$bits[] = sprintf(
				/* translators: %d: number of generated briefs. */
				_n( '%d brief', '%d briefs', count( $assignment['projects'] ), 'design-project-generator' ),
				count( $assignment['projects'] )
			);

			if ( ! empty( $settings['difficulty'] ) ) {
				$bits[] = DPG_REST_API::difficulty_label( $settings['difficulty'] );
			}

			if ( ! empty( $settings['due'] ) ) {
				$bits[] = $settings['due'];
			}

			echo esc_html( implode( ' · ', $bits ) );
		}

		if ( 'dpg_export' === $column ) {
			$url = wp_nonce_url(
				add_query_arg(
					array(
						'action'     => 'dpg_export_assignment',
						'assignment' => (int) $post_id,
					),
					admin_url( 'admin-post.php' )
				),
				'dpg_export_assignment_' . (int) $post_id
			);

			printf(
				'<a href="%s" class="button button-small">%s</a>',
				esc_url( $url ),
				esc_html__( 'Download .txt', 'design-project-generator' )
			);
		}
	}

	/**
	 * Human label for a saved-project status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( $status ) {
		switch ( $status ) {
			case 'in-progress':
				return __( 'In progress', 'design-project-generator' );

			case 'completed':
				return __( 'Completed', 'design-project-generator' );
		}

		return __( 'Not started', 'design-project-generator' );
	}

	/* --------------------------------------------------------------------- *
	 * Form handling
	 * --------------------------------------------------------------------- */

	/**
	 * Handle every admin form submission.
	 *
	 * @return void
	 */
	public function handle_post() {
		if ( ! isset( $_POST['dpg_action'] ) ) {
			return;
		}

		if ( ! DPG_Security::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'design-project-generator' ), '', array( 'response' => 403 ) );
		}

		$action = sanitize_key( wp_unslash( $_POST['dpg_action'] ) );
		check_admin_referer( 'dpg_admin_' . $action );

		$dataset = isset( $_POST['dataset'] ) ? DPG_Security::key( wp_unslash( $_POST['dataset'] ) ) : '';
		$notice  = 'saved';

		switch ( $action ) {
			case 'add_record':
				$notice = $this->add_record( $dataset ) ? 'added' : 'invalid';
				break;

			case 'delete_record':
				$this->delete_record( $dataset, isset( $_POST['record'] ) ? DPG_Security::key( wp_unslash( $_POST['record'] ) ) : '' );
				$notice = 'deleted';
				break;

			case 'toggle':
				$this->save_toggles( $dataset );
				$notice = 'saved';
				break;

			case 'save_settings':
				$this->save_settings();
				break;

			case 'import':
				$notice = $this->import() ? 'imported' : 'invalid';
				break;

			case 'reset':
				delete_option( DPG_Project_Database::CUSTOM_OPTION );
				delete_option( DPG_Project_Database::DISABLED_OPTION );
				$notice = 'reset';
				break;
		}

		$redirect = wp_get_referer();
		$redirect = $redirect ? $redirect : admin_url( 'admin.php?page=' . self::MENU );

		wp_safe_redirect( add_query_arg( 'dpg_notice', $notice, remove_query_arg( 'dpg_notice', $redirect ) ) );
		exit;
	}

	/**
	 * Field definitions for each extendable dataset.
	 *
	 * @param string $dataset Dataset key.
	 * @return array
	 */
	private static function schema( $dataset ) {
		switch ( $dataset ) {
			case 'types':
				return array(
					array(
						'key'      => 'category_id',
						'label'    => __( 'Category', 'design-project-generator' ),
						'type'     => 'category',
						'required' => true,
					),
					array(
						'key'      => 'name',
						'label'    => __( 'Project type name', 'design-project-generator' ),
						'type'     => 'text',
						'required' => true,
					),
					array(
						'key'   => 'deliverables',
						'label' => __( 'Deliverables (one per line)', 'design-project-generator' ),
						'type'  => 'lines',
					),
					array(
						'key'   => 'extras',
						'label' => __( 'Optional extra deliverables (one per line)', 'design-project-generator' ),
						'type'  => 'lines',
					),
					array(
						'key'   => 'content',
						'label' => __( 'Required content items (one per line)', 'design-project-generator' ),
						'type'  => 'lines',
					),
					array(
						'key'     => 'base_time',
						'label'   => __( 'Base time in minutes', 'design-project-generator' ),
						'type'    => 'number',
						'default' => 60,
					),
					array(
						'key'   => 'demo',
						'label' => __( 'Demo template', 'design-project-generator' ),
						'type'  => 'template',
					),
				);

			case 'industries':
				return array(
					array(
						'key'      => 'name',
						'label'    => __( 'Industry name', 'design-project-generator' ),
						'type'     => 'text',
						'required' => true,
					),
					array(
						'key'      => 'clients',
						'label'    => __( 'Fictional client names (one per line)', 'design-project-generator' ),
						'type'     => 'lines',
						'required' => true,
					),
					array(
						'key'   => 'products',
						'label' => __( 'Products or initiatives (one per line)', 'design-project-generator' ),
						'type'  => 'lines',
					),
					array(
						'key'   => 'audiences',
						'label' => __( 'Target audiences (one per line)', 'design-project-generator' ),
						'type'  => 'lines',
					),
					array(
						'key'         => 'contexts',
						'label'       => __( 'Background paragraphs (one per line)', 'design-project-generator' ),
						'type'        => 'lines',
						'description' => __( 'Use {client}, {product} and {industry} as placeholders.', 'design-project-generator' ),
					),
					array(
						'key'   => 'objectives',
						'label' => __( 'Objectives (one per line)', 'design-project-generator' ),
						'type'  => 'lines',
					),
					array(
						'key'   => 'competitors',
						'label' => __( 'Competitor descriptions (one per line)', 'design-project-generator' ),
						'type'  => 'lines',
					),
				);

			case 'styles':
				return array(
					array(
						'key'      => 'name',
						'label'    => __( 'Style name', 'design-project-generator' ),
						'type'     => 'text',
						'required' => true,
					),
					array(
						'key'   => 'direction',
						'label' => __( 'Direction shown in the brief', 'design-project-generator' ),
						'type'  => 'textarea',
					),
					array(
						'key'         => 'palette_tags',
						'label'       => __( 'Palette tags', 'design-project-generator' ),
						'type'        => 'tags',
						'description' => __( 'Comma separated, for example: warm, muted, mono.', 'design-project-generator' ),
					),
				);

			case 'palettes':
				return array(
					array(
						'key'      => 'name',
						'label'    => __( 'Palette name', 'design-project-generator' ),
						'type'     => 'text',
						'required' => true,
					),
					array(
						'key'   => 'colors',
						'label' => __( 'Colours', 'design-project-generator' ),
						'type'  => 'palette',
					),
					array(
						'key'   => 'tags',
						'label' => __( 'Tags', 'design-project-generator' ),
						'type'  => 'tags',
					),
				);

			case 'challenges':
				return array(
					array(
						'key'      => 'name',
						'label'    => __( 'Challenge name', 'design-project-generator' ),
						'type'     => 'text',
						'required' => true,
					),
					array(
						'key'      => 'prompt',
						'label'    => __( 'Prompt', 'design-project-generator' ),
						'type'     => 'textarea',
						'required' => true,
					),
					array(
						'key'   => 'type',
						'label' => __( 'Type', 'design-project-generator' ),
						'type'  => 'challenge_type',
					),
					array(
						'key'         => 'duration',
						'label'       => __( 'Duration in seconds', 'design-project-generator' ),
						'type'        => 'number',
						'default'     => 0,
						'description' => __( 'Leave at zero for challenges that are not timed.', 'design-project-generator' ),
					),
				);

			case 'restrictions':
				return array(
					array(
						'key'      => 'text',
						'label'    => __( 'Restriction', 'design-project-generator' ),
						'type'     => 'text',
						'required' => true,
					),
					array(
						'key'   => 'tags',
						'label' => __( 'Tags', 'design-project-generator' ),
						'type'  => 'tags',
					),
				);

			case 'hints':
				return array(
					array(
						'key'      => 'text',
						'label'    => __( 'Hint', 'design-project-generator' ),
						'type'     => 'text',
						'required' => true,
					),
					array(
						'key'   => 'tags',
						'label' => __( 'Tags', 'design-project-generator' ),
						'type'  => 'tags',
					),
				);
		}

		return array();
	}

	/**
	 * Datasets that can be extended from the admin.
	 *
	 * @return string[]
	 */
	private static function extendable() {
		return array( 'types', 'industries', 'styles', 'palettes', 'challenges', 'restrictions', 'hints' );
	}

	/**
	 * Add one custom record.
	 *
	 * @param string $dataset Dataset key.
	 * @return bool
	 */
	private function add_record( $dataset ) {
		$schema = self::schema( $dataset );

		if ( empty( $schema ) || ! in_array( $dataset, self::extendable(), true ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by handle_post().
		$raw    = isset( $_POST['record'] ) && is_array( $_POST['record'] ) ? wp_unslash( $_POST['record'] ) : array();
		$record = array();

		foreach ( $schema as $field ) {
			$value = isset( $raw[ $field['key'] ] ) ? $raw[ $field['key'] ] : '';
			$clean = self::sanitize_field( $field, $value );

			if ( ! empty( $field['required'] ) && ( '' === $clean || array() === $clean ) ) {
				return false;
			}

			$record[ $field['key'] ] = $clean;
		}

		$label            = isset( $record['name'] ) ? $record['name'] : ( isset( $record['text'] ) ? $record['text'] : $dataset );
		$record['id']     = self::unique_id( $dataset, $label );
		$record['custom'] = true;

		$existing   = DPG_Project_Database::custom( $dataset );
		$existing[] = $record;

		DPG_Project_Database::set_custom( $dataset, $existing );

		return true;
	}

	/**
	 * Sanitise one submitted field.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	private static function sanitize_field( array $field, $value ) {
		switch ( $field['type'] ) {
			case 'lines':
				// One item per line from the form, but already an array when
				// the value came back through an import.
				$lines = is_array( $value )
					? $value
					: preg_split( '/\r\n|\r|\n/', (string) $value );

				return DPG_Security::text_list( array_filter( array_map( 'trim', (array) $lines ) ), 40, 400 );

			case 'tags':
				$tags = is_array( $value ) ? $value : explode( ',', (string) $value );

				return array_values( array_filter( array_map( array( 'DPG_Security', 'key' ), $tags ) ) );

			case 'textarea':
				return DPG_Security::textarea( $value, 1000 );

			case 'number':
				return DPG_Security::int( $value, 0, 100000, isset( $field['default'] ) ? (int) $field['default'] : 0 );

			case 'palette':
				return DPG_Security::palette( array( 'colors' => (array) $value ) )['colors'];

			case 'category':
				$ids = wp_list_pluck( DPG_Project_Database::categories(), 'id' );

				return DPG_Security::one_of( $value, $ids, '' );

			case 'template':
				$ids = array_merge( array( 'none' ), array_keys( DPG_Project_Database::demo_templates() ) );

				return DPG_Security::one_of( $value, $ids, 'none' );

			case 'challenge_type':
				return DPG_Security::one_of(
					$value,
					array( 'timer', 'color', 'typography', 'layout', 'minimalism', 'concept', 'constraint' ),
					'concept'
				);
		}

		return DPG_Security::text( $value, 400 );
	}

	/**
	 * Build an identifier that does not clash with a bundled record.
	 *
	 * @param string $dataset Dataset key.
	 * @param string $label   Human label.
	 * @return string
	 */
	private static function unique_id( $dataset, $label ) {
		$base = sanitize_title( $label );
		$base = '' !== $base ? $base : $dataset;
		$base = 'x-' . substr( $base, 0, 40 );

		$taken = array();

		foreach ( DPG_Project_Database::custom( $dataset ) as $record ) {
			if ( isset( $record['id'] ) ) {
				$taken[] = $record['id'];
			}
		}

		$id    = $base;
		$index = 2;

		while ( in_array( $id, $taken, true ) ) {
			$id = $base . '-' . $index;
			++$index;
		}

		return $id;
	}

	/**
	 * Remove one custom record.
	 *
	 * @param string $dataset Dataset key.
	 * @param string $id      Record identifier.
	 * @return void
	 */
	private function delete_record( $dataset, $id ) {
		if ( '' === $id || ! in_array( $dataset, self::extendable(), true ) ) {
			return;
		}

		$records = array_values(
			array_filter(
				DPG_Project_Database::custom( $dataset ),
				static function ( $record ) use ( $id ) {
					return ! isset( $record['id'] ) || $record['id'] !== $id;
				}
			)
		);

		DPG_Project_Database::set_custom( $dataset, $records );
	}

	/**
	 * Save the enabled/disabled state for a dataset.
	 *
	 * @param string $dataset Dataset key.
	 * @return void
	 */
	private function save_toggles( $dataset ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by handle_post().
		$enabled = isset( $_POST['enabled'] ) && is_array( $_POST['enabled'] )
			? array_map( array( 'DPG_Security', 'key' ), wp_unslash( $_POST['enabled'] ) )
			: array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by handle_post().
		$known = isset( $_POST['known'] ) && is_array( $_POST['known'] )
			? array_map( array( 'DPG_Security', 'key' ), wp_unslash( $_POST['known'] ) )
			: array();

		DPG_Project_Database::set_disabled( $dataset, array_values( array_diff( $known, $enabled ) ) );
	}

	/**
	 * Save the settings screen.
	 *
	 * @return void
	 */
	private function save_settings() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by handle_post().
		$raw = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();

		$categories = wp_list_pluck( DPG_Project_Database::categories(), 'id' );

		update_option(
			self::SETTINGS,
			array(
				'default_category'   => DPG_Security::one_of( isset( $raw['default_category'] ) ? $raw['default_category'] : '', $categories, '' ),
				'default_difficulty' => DPG_Security::one_of( isset( $raw['default_difficulty'] ) ? $raw['default_difficulty'] : '', DPG_Security::$difficulties, '' ),
				'guest_saving'       => empty( $raw['guest_saving'] ) ? 0 : 1,
				'daily_challenge'    => empty( $raw['daily_challenge'] ) ? 0 : 1,
				'pdf_size'           => DPG_Security::one_of( isset( $raw['pdf_size'] ) ? $raw['pdf_size'] : '', array( 'a4', 'a3', 'letter' ), 'a4' ),
				'png_width'          => DPG_Security::int( isset( $raw['png_width'] ) ? $raw['png_width'] : 0, 320, 4096, 1920 ),
				'png_quality'        => DPG_Security::one_of( isset( $raw['png_quality'] ) ? $raw['png_quality'] : '', array( 'standard', 'high' ), 'standard' ),
			)
		);
	}

	/**
	 * Import a previously exported data bundle.
	 *
	 * @return bool
	 */
	private function import() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by handle_post().
		$raw     = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : '';
		$decoded = json_decode( (string) $raw, true );

		// Importing replaces the site's customisations, so anything that is
		// not recognisably one of our bundles is refused rather than treated
		// as an empty one — that would quietly wipe the lot.
		if ( ! is_array( $decoded ) ) {
			return false;
		}

		$has_custom   = isset( $decoded['custom'] ) && is_array( $decoded['custom'] );
		$has_disabled = isset( $decoded['disabled'] ) && is_array( $decoded['disabled'] );

		if ( ! $has_custom && ! $has_disabled ) {
			return false;
		}

		$custom = $has_custom ? $decoded['custom'] : array();
		$clean  = array();

		foreach ( self::extendable() as $dataset ) {
			if ( empty( $custom[ $dataset ] ) || ! is_array( $custom[ $dataset ] ) ) {
				continue;
			}

			$schema  = self::schema( $dataset );
			$records = array();

			foreach ( $custom[ $dataset ] as $record ) {
				if ( ! is_array( $record ) ) {
					continue;
				}

				$row     = array();
				$missing = false;

				foreach ( $schema as $field ) {
					$value = self::sanitize_field(
						$field,
						isset( $record[ $field['key'] ] ) ? $record[ $field['key'] ] : ''
					);

					if ( ! empty( $field['required'] ) && ( '' === $value || array() === $value ) ) {
						$missing = true;
					}

					$row[ $field['key'] ] = $value;
				}

				// A record without its required fields would generate broken
				// briefs, so it is dropped rather than stored.
				if ( $missing ) {
					continue;
				}

				$label = isset( $row['name'] ) ? $row['name'] : ( isset( $row['text'] ) ? $row['text'] : $dataset );
				$id    = isset( $record['id'] ) ? DPG_Security::key( $record['id'] ) : '';

				// Custom identifiers always carry the x- prefix, so an import
				// can never shadow or collide with a bundled record.
				$row['id']     = ( $id && 0 === strpos( $id, 'x-' ) ) ? $id : self::unique_id( $dataset, $label );
				$row['custom'] = true;
				$records[]     = $row;
			}

			$clean[ $dataset ] = $records;
		}

		update_option( DPG_Project_Database::CUSTOM_OPTION, $clean, false );

		if ( isset( $decoded['disabled'] ) && is_array( $decoded['disabled'] ) ) {
			$disabled = array();

			foreach ( $decoded['disabled'] as $dataset => $ids ) {
				$disabled[ DPG_Security::key( $dataset ) ] = array_values(
					array_filter( array_map( array( 'DPG_Security', 'key' ), (array) $ids ) )
				);
			}

			update_option( DPG_Project_Database::DISABLED_OPTION, $disabled, false );
		}

		return true;
	}

	/* --------------------------------------------------------------------- *
	 * Screens
	 * --------------------------------------------------------------------- */

	/**
	 * Print the notice for the current request.
	 *
	 * @return void
	 */
	private function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a redirect flag.
		$notice = isset( $_GET['dpg_notice'] ) ? sanitize_key( wp_unslash( $_GET['dpg_notice'] ) ) : '';

		if ( '' === $notice ) {
			return;
		}

		$messages = array(
			'saved'    => __( 'Changes saved.', 'design-project-generator' ),
			'added'    => __( 'Record added.', 'design-project-generator' ),
			'deleted'  => __( 'Record deleted.', 'design-project-generator' ),
			'imported' => __( 'Data imported.', 'design-project-generator' ),
			'reset'    => __( 'Custom data cleared. The bundled database is untouched.', 'design-project-generator' ),
			'invalid'  => __( 'That could not be saved. Check the required fields.', 'design-project-generator' ),
		);

		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			'invalid' === $notice ? 'error' : 'success',
			esc_html( $messages[ $notice ] )
		);
	}

	/**
	 * Screen wrapper.
	 *
	 * @param string   $title Page title.
	 * @param callable $body  Body renderer.
	 * @return void
	 */
	private function wrap( $title, $body ) {
		echo '<div class="wrap dpg-admin">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		$this->notice();
		call_user_func( $body );
		echo '</div>';
	}

	/**
	 * Dashboard screen.
	 *
	 * @return void
	 */
	public function screen_dashboard() {
		$this->wrap(
			__( 'Design Project Generator', 'design-project-generator' ),
			function () {
				$stats = DPG_Project_Database::stats();
				$saved = wp_count_posts( DPG_Post_Types::PROJECT );
				?>
				<p class="dpg-admin__lead">
					<?php esc_html_e( 'Everything below runs locally. The generator makes no external requests and needs no API key.', 'design-project-generator' ); ?>
				</p>

				<div class="dpg-admin__cards">
					<?php
					$cards = array(
						__( 'Categories', 'design-project-generator' )   => $stats['categories'],
						__( 'Project types', 'design-project-generator' ) => $stats['types'],
						__( 'Industries', 'design-project-generator' )   => $stats['industries'],
						__( 'Styles', 'design-project-generator' )       => $stats['styles'],
						__( 'Palettes', 'design-project-generator' )     => $stats['palettes'],
						__( 'Challenges', 'design-project-generator' )   => $stats['challenges'],
						__( 'Restrictions', 'design-project-generator' ) => $stats['restrictions'],
						__( 'Demo templates', 'design-project-generator' ) => $stats['templates'],
						__( 'Saved projects', 'design-project-generator' ) => isset( $saved->publish ) ? (int) $saved->publish : 0,
					);

					foreach ( $cards as $label => $value ) :
						?>
						<div class="dpg-admin__card">
							<span class="dpg-admin__card-value"><?php echo esc_html( number_format_i18n( $value ) ); ?></span>
							<span class="dpg-admin__card-label"><?php echo esc_html( $label ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="dpg-admin__card dpg-admin__card--wide">
					<span class="dpg-admin__card-value"><?php echo esc_html( number_format_i18n( DPG_Project_Database::combination_count() ) ); ?></span>
					<span class="dpg-admin__card-label"><?php esc_html_e( 'Distinct brief combinations available right now', 'design-project-generator' ); ?></span>
				</div>

				<h2><?php esc_html_e( 'Adding the generator to a page', 'design-project-generator' ); ?></h2>
				<p><?php esc_html_e( 'Use the block called “Design Project Generator”, or paste this shortcode:', 'design-project-generator' ); ?></p>
				<p><code>[design_project_generator]</code></p>

				<h3><?php esc_html_e( 'Useful variations', 'design-project-generator' ); ?></h3>
				<table class="widefat striped dpg-admin__table">
					<tbody>
						<tr>
							<td><code>[design_project_generator category="branding" difficulty="beginner"]</code></td>
							<td><?php esc_html_e( 'Lock the generator to beginner branding briefs.', 'design-project-generator' ); ?></td>
						</tr>
						<tr>
							<td><code>[design_project_generator daily="yes" show_filters="no"]</code></td>
							<td><?php esc_html_e( 'Show today’s challenge — the same brief for every visitor, all day.', 'design-project-generator' ); ?></td>
						</tr>
						<tr>
							<td><code>[design_project_generator demo_only="yes"]</code></td>
							<td><?php esc_html_e( 'Only generate project types that come with an interactive demo.', 'design-project-generator' ); ?></td>
						</tr>
						<tr>
							<td><code>[design_project_generator teacher="yes"]</code></td>
							<td><?php esc_html_e( 'Add the assignment builder for users who can edit posts.', 'design-project-generator' ); ?></td>
						</tr>
						<tr>
							<td><code>[design_project_generator show_export="no" show_save="no"]</code></td>
							<td><?php esc_html_e( 'A stripped-back brief viewer with no export or save buttons.', 'design-project-generator' ); ?></td>
						</tr>
					</tbody>
				</table>
				<?php
			}
		);
	}

	/**
	 * Categories and project types screen.
	 *
	 * @return void
	 */
	public function screen_categories() {
		$this->wrap(
			__( 'Categories and project types', 'design-project-generator' ),
			function () {
				$categories = DPG_Project_Database::categories();
				$disabled   = DPG_Project_Database::disabled( 'types' );
				?>
				<p class="dpg-admin__lead"><?php esc_html_e( 'Untick a project type to keep it out of the generator. Bundled records are never edited in place, so plugin updates cannot overwrite your choices.', 'design-project-generator' ); ?></p>

				<form method="post" class="dpg-admin__form">
					<?php wp_nonce_field( 'dpg_admin_toggle' ); ?>
					<input type="hidden" name="dpg_action" value="toggle" />
					<input type="hidden" name="dataset" value="types" />

					<?php foreach ( $categories as $category ) : ?>
						<h2><?php echo esc_html( $category['name'] ); ?></h2>
						<?php if ( ! empty( $category['description'] ) ) : ?>
							<p class="description"><?php echo esc_html( $category['description'] ); ?></p>
						<?php endif; ?>
						<ul class="dpg-admin__toggles">
							<?php foreach ( $category['types'] as $type ) : ?>
								<li>
									<label>
										<input type="checkbox" name="enabled[]" value="<?php echo esc_attr( $type['id'] ); ?>" <?php checked( ! in_array( $type['id'], $disabled, true ) ); ?> />
										<span><?php echo esc_html( $type['name'] ); ?></span>
										<?php if ( ! empty( $type['demo'] ) && 'none' !== $type['demo'] ) : ?>
											<em class="dpg-admin__badge"><?php echo esc_html( $type['demo'] ); ?></em>
										<?php endif; ?>
										<?php if ( ! empty( $type['custom'] ) ) : ?>
											<em class="dpg-admin__badge dpg-admin__badge--custom"><?php esc_html_e( 'custom', 'design-project-generator' ); ?></em>
										<?php endif; ?>
									</label>
									<input type="hidden" name="known[]" value="<?php echo esc_attr( $type['id'] ); ?>" />
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endforeach; ?>

					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save enabled types', 'design-project-generator' ); ?></button></p>
				</form>

				<?php $this->render_add_form( 'types', __( 'Add a project type', 'design-project-generator' ) ); ?>
				<?php $this->render_custom_list( 'types', 'name' ); ?>
				<?php
			}
		);
	}

	/**
	 * Industries screen.
	 *
	 * @return void
	 */
	public function screen_industries() {
		$this->wrap(
			__( 'Industries', 'design-project-generator' ),
			function () {
				$this->render_toggle_form( 'industries', DPG_Project_Database::industries(), 'name' );
				$this->render_add_form( 'industries', __( 'Add an industry', 'design-project-generator' ) );
				$this->render_custom_list( 'industries', 'name' );
			}
		);
	}

	/**
	 * Styles screen.
	 *
	 * @return void
	 */
	public function screen_styles() {
		$this->wrap(
			__( 'Design styles', 'design-project-generator' ),
			function () {
				$this->render_toggle_form( 'styles', DPG_Project_Database::styles(), 'name' );
				$this->render_add_form( 'styles', __( 'Add a style', 'design-project-generator' ) );
				$this->render_custom_list( 'styles', 'name' );
			}
		);
	}

	/**
	 * Palettes screen.
	 *
	 * @return void
	 */
	public function screen_palettes() {
		$this->wrap(
			__( 'Colour palettes', 'design-project-generator' ),
			function () {
				$palettes = DPG_Project_Database::palettes();
				$disabled = DPG_Project_Database::disabled( 'palettes' );
				?>
				<form method="post" class="dpg-admin__form">
					<?php wp_nonce_field( 'dpg_admin_toggle' ); ?>
					<input type="hidden" name="dpg_action" value="toggle" />
					<input type="hidden" name="dataset" value="palettes" />

					<ul class="dpg-admin__palettes">
						<?php foreach ( $palettes as $palette ) : ?>
							<li>
								<label>
									<input type="checkbox" name="enabled[]" value="<?php echo esc_attr( $palette['id'] ); ?>" <?php checked( ! in_array( $palette['id'], $disabled, true ) ); ?> />
									<span class="dpg-admin__palette-name"><?php echo esc_html( $palette['name'] ); ?></span>
								</label>
								<span class="dpg-admin__palette-strip">
									<?php foreach ( (array) $palette['colors'] as $role => $hex ) : ?>
										<span title="<?php echo esc_attr( $role . ' ' . $hex ); ?>" style="background:<?php echo esc_attr( DPG_Security::hex( $hex ) ); ?>"></span>
									<?php endforeach; ?>
								</span>
								<input type="hidden" name="known[]" value="<?php echo esc_attr( $palette['id'] ); ?>" />
							</li>
						<?php endforeach; ?>
					</ul>

					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save enabled palettes', 'design-project-generator' ); ?></button></p>
				</form>

				<?php
				$this->render_add_form( 'palettes', __( 'Add a palette', 'design-project-generator' ) );
				$this->render_custom_list( 'palettes', 'name' );
			}
		);
	}

	/**
	 * Challenges, restrictions and hints screen.
	 *
	 * @return void
	 */
	public function screen_challenges() {
		$this->wrap(
			__( 'Challenges, restrictions and hints', 'design-project-generator' ),
			function () {
				echo '<h2>' . esc_html__( 'Challenges', 'design-project-generator' ) . '</h2>';
				$this->render_toggle_form( 'challenges', DPG_Project_Database::challenges(), 'name' );
				$this->render_add_form( 'challenges', __( 'Add a challenge', 'design-project-generator' ) );
				$this->render_custom_list( 'challenges', 'name' );

				echo '<hr />';
				echo '<h2>' . esc_html__( 'Restrictions', 'design-project-generator' ) . '</h2>';
				$this->render_toggle_form( 'restrictions', DPG_Project_Database::restrictions(), 'text' );
				$this->render_add_form( 'restrictions', __( 'Add a restriction', 'design-project-generator' ) );
				$this->render_custom_list( 'restrictions', 'text' );

				echo '<hr />';
				echo '<h2>' . esc_html__( 'Hints', 'design-project-generator' ) . '</h2>';
				$this->render_toggle_form( 'hints', DPG_Project_Database::hints(), 'text' );
				$this->render_add_form( 'hints', __( 'Add a hint', 'design-project-generator' ) );
				$this->render_custom_list( 'hints', 'text' );
			}
		);
	}

	/**
	 * Demo templates screen.
	 *
	 * @return void
	 */
	public function screen_templates() {
		$this->wrap(
			__( 'Demo templates', 'design-project-generator' ),
			function () {
				$templates = DPG_Project_Database::demo_templates();
				?>
				<p class="dpg-admin__lead">
					<?php esc_html_e( 'Demo templates live in the plugin’s templates folder. Each one needs a template.html file; CSS and JavaScript files are optional. Demos always run inside a sandboxed iframe, never in the page itself.', 'design-project-generator' ); ?>
				</p>

				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Template', 'design-project-generator' ); ?></th>
							<th><?php esc_html_e( 'Identifier', 'design-project-generator' ); ?></th>
							<th><?php esc_html_e( 'CSS', 'design-project-generator' ); ?></th>
							<th><?php esc_html_e( 'JavaScript', 'design-project-generator' ); ?></th>
							<th><?php esc_html_e( 'Used by', 'design-project-generator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $templates ) ) : ?>
							<tr><td colspan="5"><?php esc_html_e( 'No demo templates found.', 'design-project-generator' ); ?></td></tr>
						<?php endif; ?>
						<?php
						foreach ( $templates as $template ) :
							$used = array();

							foreach ( DPG_Project_Database::types() as $type ) {
								if ( isset( $type['demo'] ) && $type['demo'] === $template['id'] ) {
									$used[] = $type['name'];
								}
							}
							?>
							<tr>
								<td><strong><?php echo esc_html( $template['name'] ); ?></strong></td>
								<td><code><?php echo esc_html( $template['id'] ); ?></code></td>
								<td><?php echo $template['has_css'] ? '&#10003;' : '&mdash;'; ?></td>
								<td><?php echo $template['has_js'] ? '&#10003;' : '&mdash;'; ?></td>
								<td><?php echo esc_html( $used ? implode( ', ', $used ) : __( 'Not used yet', 'design-project-generator' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php
			}
		);
	}

	/**
	 * Settings screen.
	 *
	 * @return void
	 */
	public function screen_settings() {
		$this->wrap(
			__( 'Settings', 'design-project-generator' ),
			function () {
				$settings = self::settings();
				?>
				<form method="post">
					<?php wp_nonce_field( 'dpg_admin_save_settings' ); ?>
					<input type="hidden" name="dpg_action" value="save_settings" />

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="dpg-default-category"><?php esc_html_e( 'Default category', 'design-project-generator' ); ?></label></th>
							<td>
								<select id="dpg-default-category" name="settings[default_category]">
									<option value=""><?php esc_html_e( 'Any category', 'design-project-generator' ); ?></option>
									<?php foreach ( DPG_Project_Database::categories() as $category ) : ?>
										<option value="<?php echo esc_attr( $category['id'] ); ?>" <?php selected( $settings['default_category'], $category['id'] ); ?>>
											<?php echo esc_html( $category['name'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Used when a shortcode or block does not set one.', 'design-project-generator' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dpg-default-difficulty"><?php esc_html_e( 'Default difficulty', 'design-project-generator' ); ?></label></th>
							<td>
								<select id="dpg-default-difficulty" name="settings[default_difficulty]">
									<option value=""><?php esc_html_e( 'Any difficulty', 'design-project-generator' ); ?></option>
									<?php foreach ( DPG_Security::$difficulties as $difficulty ) : ?>
										<option value="<?php echo esc_attr( $difficulty ); ?>" <?php selected( $settings['default_difficulty'], $difficulty ); ?>>
											<?php echo esc_html( DPG_REST_API::difficulty_label( $difficulty ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Guests', 'design-project-generator' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="settings[guest_saving]" value="1" <?php checked( $settings['guest_saving'], 1 ); ?> />
									<?php esc_html_e( 'Let logged-out visitors save projects in their own browser', 'design-project-generator' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Guest saves use localStorage and never reach the server.', 'design-project-generator' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Daily challenge', 'design-project-generator' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="settings[daily_challenge]" value="1" <?php checked( $settings['daily_challenge'], 1 ); ?> />
									<?php esc_html_e( 'Enable the daily challenge mode', 'design-project-generator' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dpg-pdf-size"><?php esc_html_e( 'Default PDF size', 'design-project-generator' ); ?></label></th>
							<td>
								<select id="dpg-pdf-size" name="settings[pdf_size]">
									<?php
									$sizes = array(
										'a4'     => 'A4 (210 × 297 mm)',
										'a3'     => 'A3 (297 × 420 mm)',
										'letter' => 'Letter (216 × 279 mm)',
									);

									foreach ( $sizes as $key => $label ) :
										?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['pdf_size'], $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dpg-png-width"><?php esc_html_e( 'Default PNG width', 'design-project-generator' ); ?></label></th>
							<td>
								<select id="dpg-png-width" name="settings[png_width]">
									<?php foreach ( array( 1080, 1920, 2560 ) as $width ) : ?>
										<option value="<?php echo esc_attr( $width ); ?>" <?php selected( (int) $settings['png_width'], $width ); ?>><?php echo esc_html( $width . 'px' ); ?></option>
									<?php endforeach; ?>
								</select>
								<label class="dpg-admin__inline">
									<input type="checkbox" name="settings[png_quality]" value="high" <?php checked( $settings['png_quality'], 'high' ); ?> />
									<?php esc_html_e( 'High quality (larger files)', 'design-project-generator' ); ?>
								</label>
							</td>
						</tr>
					</table>

					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'design-project-generator' ); ?></button></p>
				</form>
				<?php
			}
		);
	}

	/**
	 * Tools screen.
	 *
	 * @return void
	 */
	public function screen_tools() {
		$this->wrap(
			__( 'Tools', 'design-project-generator' ),
			function () {
				$bundle = wp_json_encode(
					array(
						'plugin'   => 'design-project-generator',
						'version'  => DPG_VERSION,
						'custom'   => get_option( DPG_Project_Database::CUSTOM_OPTION, array() ),
						'disabled' => get_option( DPG_Project_Database::DISABLED_OPTION, array() ),
					),
					JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
				);
				?>
				<h2><?php esc_html_e( 'Export your customisations', 'design-project-generator' ); ?></h2>
				<p class="description"><?php esc_html_e( 'This contains only what you have added or switched off. The bundled database is part of the plugin.', 'design-project-generator' ); ?></p>
				<textarea class="dpg-admin__json" readonly rows="10" onclick="this.select()"><?php echo esc_textarea( (string) $bundle ); ?></textarea>

				<h2><?php esc_html_e( 'Import', 'design-project-generator' ); ?></h2>
				<form method="post">
					<?php wp_nonce_field( 'dpg_admin_import' ); ?>
					<input type="hidden" name="dpg_action" value="import" />
					<p class="description"><?php esc_html_e( 'Paste a bundle exported from this plugin. This replaces your current customisations.', 'design-project-generator' ); ?></p>
					<textarea class="dpg-admin__json" name="payload" rows="8" placeholder="{ &quot;custom&quot;: { … } }"></textarea>
					<p><button type="submit" class="button"><?php esc_html_e( 'Import bundle', 'design-project-generator' ); ?></button></p>
				</form>

				<h2><?php esc_html_e( 'Reset', 'design-project-generator' ); ?></h2>
				<form method="post" onsubmit="return confirm( <?php echo esc_attr( wp_json_encode( __( 'Delete every custom record and re-enable everything bundled?', 'design-project-generator' ) ) ); ?> );">
					<?php wp_nonce_field( 'dpg_admin_reset' ); ?>
					<input type="hidden" name="dpg_action" value="reset" />
					<p class="description"><?php esc_html_e( 'Removes your custom records and re-enables every bundled record. Saved projects and assignments are not touched.', 'design-project-generator' ); ?></p>
					<p><button type="submit" class="button button-link-delete"><?php esc_html_e( 'Reset custom data', 'design-project-generator' ); ?></button></p>
				</form>
				<?php
			}
		);
	}

	/**
	 * Generic enable/disable form.
	 *
	 * @param string $dataset    Dataset key.
	 * @param array  $records    Records.
	 * @param string $label_key  Key holding the human label.
	 * @return void
	 */
	private function render_toggle_form( $dataset, array $records, $label_key ) {
		$disabled = DPG_Project_Database::disabled( $dataset );
		?>
		<form method="post" class="dpg-admin__form">
			<?php wp_nonce_field( 'dpg_admin_toggle' ); ?>
			<input type="hidden" name="dpg_action" value="toggle" />
			<input type="hidden" name="dataset" value="<?php echo esc_attr( $dataset ); ?>" />

			<ul class="dpg-admin__toggles">
				<?php foreach ( $records as $record ) : ?>
					<?php $id = isset( $record['id'] ) ? $record['id'] : ''; ?>
					<?php if ( '' === $id ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<li>
						<label>
							<input type="checkbox" name="enabled[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( ! in_array( $id, $disabled, true ) ); ?> />
							<span><?php echo esc_html( isset( $record[ $label_key ] ) ? $record[ $label_key ] : $id ); ?></span>
							<?php if ( ! empty( $record['custom'] ) ) : ?>
								<em class="dpg-admin__badge dpg-admin__badge--custom"><?php esc_html_e( 'custom', 'design-project-generator' ); ?></em>
							<?php endif; ?>
						</label>
						<input type="hidden" name="known[]" value="<?php echo esc_attr( $id ); ?>" />
					</li>
				<?php endforeach; ?>
			</ul>

			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save enabled records', 'design-project-generator' ); ?></button></p>
		</form>
		<?php
	}

	/**
	 * Generic "add a record" form built from the dataset schema.
	 *
	 * @param string $dataset Dataset key.
	 * @param string $title   Form heading.
	 * @return void
	 */
	private function render_add_form( $dataset, $title ) {
		$schema = self::schema( $dataset );

		if ( empty( $schema ) ) {
			return;
		}
		?>
		<details class="dpg-admin__add">
			<summary><?php echo esc_html( $title ); ?></summary>
			<form method="post" class="dpg-admin__form">
				<?php wp_nonce_field( 'dpg_admin_add_record' ); ?>
				<input type="hidden" name="dpg_action" value="add_record" />
				<input type="hidden" name="dataset" value="<?php echo esc_attr( $dataset ); ?>" />

				<table class="form-table" role="presentation">
					<?php foreach ( $schema as $field ) : ?>
						<?php $field_id = 'dpg-' . $dataset . '-' . $field['key']; ?>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $field_id ); ?>">
									<?php echo esc_html( $field['label'] ); ?>
									<?php if ( ! empty( $field['required'] ) ) : ?>
										<span class="dpg-admin__required">*</span>
									<?php endif; ?>
								</label>
							</th>
							<td><?php $this->render_field( $dataset, $field, $field_id ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>

				<p><button type="submit" class="button button-primary"><?php echo esc_html( $title ); ?></button></p>
			</form>
		</details>
		<?php
	}

	/**
	 * Render one form field.
	 *
	 * @param string $dataset  Dataset key.
	 * @param array  $field    Field definition.
	 * @param string $field_id Element identifier.
	 * @return void
	 */
	private function render_field( $dataset, array $field, $field_id ) {
		$name = 'record[' . $field['key'] . ']';

		switch ( $field['type'] ) {
			case 'lines':
			case 'textarea':
				printf(
					'<textarea id="%s" name="%s" rows="%d" class="large-text code"></textarea>',
					esc_attr( $field_id ),
					esc_attr( $name ),
					'lines' === $field['type'] ? 5 : 3
				);
				break;

			case 'number':
				printf(
					'<input type="number" id="%s" name="%s" value="%s" min="0" step="1" />',
					esc_attr( $field_id ),
					esc_attr( $name ),
					esc_attr( isset( $field['default'] ) ? $field['default'] : 0 )
				);
				break;

			case 'tags':
				printf(
					'<input type="text" id="%s" name="%s" class="regular-text" />',
					esc_attr( $field_id ),
					esc_attr( $name )
				);
				break;

			case 'palette':
				$roles = array( 'primary', 'secondary', 'accent', 'background', 'text' );
				echo '<span class="dpg-admin__colors">';

				foreach ( $roles as $role ) {
					printf(
						'<label><span>%s</span><input type="color" name="record[colors][%s]" value="%s" /></label>',
						esc_html( ucfirst( $role ) ),
						esc_attr( $role ),
						esc_attr( 'background' === $role ? '#ffffff' : '#333333' )
					);
				}

				echo '</span>';
				break;

			case 'category':
				echo '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '">';

				foreach ( DPG_Project_Database::categories() as $category ) {
					printf( '<option value="%s">%s</option>', esc_attr( $category['id'] ), esc_html( $category['name'] ) );
				}

				echo '</select>';
				break;

			case 'template':
				echo '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '">';
				printf( '<option value="none">%s</option>', esc_html__( 'No demo', 'design-project-generator' ) );

				foreach ( DPG_Project_Database::demo_templates() as $template ) {
					printf( '<option value="%s">%s</option>', esc_attr( $template['id'] ), esc_html( $template['name'] ) );
				}

				echo '</select>';
				break;

			case 'challenge_type':
				echo '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '">';

				foreach ( array( 'timer', 'color', 'typography', 'layout', 'minimalism', 'concept', 'constraint' ) as $type ) {
					printf( '<option value="%s">%s</option>', esc_attr( $type ), esc_html( ucfirst( $type ) ) );
				}

				echo '</select>';
				break;

			default:
				printf(
					'<input type="text" id="%s" name="%s" class="regular-text" maxlength="400" />',
					esc_attr( $field_id ),
					esc_attr( $name )
				);
		}

		if ( ! empty( $field['description'] ) ) {
			echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
		}
	}

	/**
	 * List the custom records for a dataset with delete buttons.
	 *
	 * @param string $dataset   Dataset key.
	 * @param string $label_key Key holding the human label.
	 * @return void
	 */
	private function render_custom_list( $dataset, $label_key ) {
		$records = DPG_Project_Database::custom( $dataset );

		if ( empty( $records ) ) {
			return;
		}
		?>
		<h3><?php esc_html_e( 'Your custom records', 'design-project-generator' ); ?></h3>
		<table class="widefat striped dpg-admin__table">
			<tbody>
				<?php foreach ( $records as $record ) : ?>
					<tr>
						<td><?php echo esc_html( isset( $record[ $label_key ] ) ? $record[ $label_key ] : $record['id'] ); ?></td>
						<td><code><?php echo esc_html( $record['id'] ); ?></code></td>
						<td class="dpg-admin__row-actions">
							<form method="post">
								<?php wp_nonce_field( 'dpg_admin_delete_record' ); ?>
								<input type="hidden" name="dpg_action" value="delete_record" />
								<input type="hidden" name="dataset" value="<?php echo esc_attr( $dataset ); ?>" />
								<input type="hidden" name="record" value="<?php echo esc_attr( $record['id'] ); ?>" />
								<button type="submit" class="button-link button-link-delete"><?php esc_html_e( 'Delete', 'design-project-generator' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
