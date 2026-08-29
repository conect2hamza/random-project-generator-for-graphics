<?php
/**
 * REST endpoints.
 *
 * Generation happens on the server so that the whole project database does not
 * have to be shipped to every visitor, and so there is exactly one copy of the
 * randomisation logic. Everything else the interface does — the timer, the
 * palette tools, the demo editor and all three export formats — runs entirely
 * in the browser and never calls these routes.
 *
 * @package DesignProjectGenerator
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller.
 */
class DPG_REST_API {

	/**
	 * Route namespace.
	 */
	const NAMESPACE_V1 = 'dpg/v1';

	/**
	 * Hook into WordPress.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register every route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/options',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_options' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'category'   => array( 'type' => 'string' ),
					'type'       => array( 'type' => 'string' ),
					'industry'   => array( 'type' => 'string' ),
					'style'      => array( 'type' => 'string' ),
					'difficulty' => array( 'type' => 'string' ),
					'mode'       => array( 'type' => 'string' ),
					'seed'       => array( 'type' => 'integer' ),
					'demo_only'  => array( 'type' => 'boolean' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/daily',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_daily' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/demo/(?P<id>[a-z0-9\-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_demo' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/projects',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_projects' ),
					'permission_callback' => array( $this, 'require_login' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_project' ),
					'permission_callback' => array( $this, 'require_login' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/projects/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_project' ),
					'permission_callback' => array( $this, 'require_login' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_project' ),
					'permission_callback' => array( $this, 'require_login' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/assignments',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_assignment' ),
				'permission_callback' => array( $this, 'require_teacher' ),
			)
		);
	}

	/**
	 * Permission callback: any logged-in user.
	 *
	 * @return true|WP_Error
	 */
	public function require_login() {
		if ( is_user_logged_in() ) {
			return true;
		}

		return new WP_Error(
			'dpg_not_logged_in',
			__( 'You must be logged in to do that.', 'design-project-generator' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Permission callback: users who may create assignments.
	 *
	 * @return true|WP_Error
	 */
	public function require_teacher() {
		if ( DPG_Security::can_teach() ) {
			return true;
		}

		return new WP_Error(
			'dpg_forbidden',
			__( 'You do not have permission to create assignments.', 'design-project-generator' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Everything the filter controls need.
	 *
	 * @return WP_REST_Response
	 */
	public function get_options() {
		return rest_ensure_response( self::options_payload() );
	}

	/**
	 * Build the options payload. Also used to bootstrap the shortcode markup.
	 *
	 * @return array
	 */
	public static function options_payload() {
		$categories = array();

		foreach ( DPG_Project_Database::categories() as $category ) {
			$types = array();

			foreach ( $category['types'] as $type ) {
				$types[] = array(
					'id'   => $type['id'],
					'name' => $type['name'],
					'demo' => isset( $type['demo'] ) ? $type['demo'] : 'none',
				);
			}

			$categories[] = array(
				'id'          => $category['id'],
				'name'        => $category['name'],
				'description' => isset( $category['description'] ) ? $category['description'] : '',
				'types'       => $types,
			);
		}

		$industries = array();

		foreach ( DPG_Project_Database::industries() as $industry ) {
			$industries[] = array(
				'id'   => $industry['id'],
				'name' => $industry['name'],
			);
		}

		$styles = array();

		foreach ( DPG_Project_Database::styles() as $style ) {
			$styles[] = array(
				'id'   => $style['id'],
				'name' => $style['name'],
			);
		}

		$difficulties = array();

		foreach ( DPG_Security::$difficulties as $difficulty ) {
			$difficulties[] = array(
				'id'   => $difficulty,
				'name' => self::difficulty_label( $difficulty ),
			);
		}

		return array(
			'categories'    => $categories,
			'industries'    => $industries,
			'styles'        => $styles,
			'difficulties'  => $difficulties,
			'timerPresets'  => DPG_Project_Database::timer_presets(),
			'palettes'      => DPG_Project_Database::palettes(),
			'hints'         => wp_list_pluck( DPG_Project_Database::hints(), 'text' ),
			'solutionHints' => DPG_Project_Database::solution_hints(),
			'templates'     => array_keys( DPG_Project_Database::demo_templates() ),
			'combinations'  => DPG_Project_Database::combination_count(),
		);
	}

	/**
	 * Translated difficulty label.
	 *
	 * @param string $difficulty Difficulty identifier.
	 * @return string
	 */
	public static function difficulty_label( $difficulty ) {
		switch ( $difficulty ) {
			case 'beginner':
				return __( 'Beginner', 'design-project-generator' );

			case 'intermediate':
				return __( 'Intermediate', 'design-project-generator' );

			case 'advanced':
				return __( 'Advanced', 'design-project-generator' );

			case 'expert':
				return __( 'Expert', 'design-project-generator' );
		}

		return ucfirst( (string) $difficulty );
	}

	/**
	 * Generate a project.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate( WP_REST_Request $request ) {
		$filters  = DPG_Security::filters( $request->get_json_params() ? $request->get_json_params() : $request->get_params() );
		$previous = $request->get_param( 'previous' );

		if ( is_array( $previous ) ) {
			$previous = DPG_Security::validate_project( $previous );
			$previous = is_wp_error( $previous ) ? array() : $previous;
		} else {
			$previous = array();
		}

		$generator = new DPG_Project_Generator( $filters['seed'] );
		$project   = $generator->generate( $filters, $previous );

		if ( is_wp_error( $project ) ) {
			return $project;
		}

		return rest_ensure_response(
			array(
				'project' => $project,
				'html'    => DPG_Shortcode::render_project_html( $project ),
			)
		);
	}

	/**
	 * Today's challenge.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_daily() {
		$project = DPG_Project_Generator::daily();

		if ( is_wp_error( $project ) ) {
			return $project;
		}

		return rest_ensure_response(
			array(
				'project' => $project,
				'html'    => DPG_Shortcode::render_project_html( $project ),
			)
		);
	}

	/**
	 * Return one demo template's source files.
	 *
	 * The files are bundled with the plugin and are never user supplied. The
	 * browser drops them into a sandboxed iframe rather than the page itself.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_demo( WP_REST_Request $request ) {
		$template = DPG_Project_Database::demo_template( (string) $request->get_param( 'id' ) );

		if ( ! $template ) {
			return new WP_Error(
				'dpg_no_template',
				__( 'That demo template does not exist.', 'design-project-generator' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $template );
	}

	/**
	 * List the current user's saved projects.
	 *
	 * @return WP_REST_Response
	 */
	public function list_projects() {
		return rest_ensure_response(
			array( 'projects' => DPG_Post_Types::list_projects( get_current_user_id() ) )
		);
	}

	/**
	 * Create or update a saved project.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_project( WP_REST_Request $request ) {
		$project = DPG_Security::validate_project( $request->get_param( 'project' ) );

		if ( is_wp_error( $project ) ) {
			return $project;
		}

		$extra = array(
			'status'     => $request->get_param( 'status' ),
			'checklist'  => $request->get_param( 'checklist' ),
			'case_study' => $request->get_param( 'case_study' ),
		);

		$post_id = $request->get_param( 'id' ) ? absint( $request->get_param( 'id' ) ) : 0;
		$saved   = DPG_Post_Types::save_project( get_current_user_id(), $project, $extra, $post_id );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return rest_ensure_response(
			array(
				'saved'   => true,
				'project' => DPG_Post_Types::read_project( $saved ),
			)
		);
	}

	/**
	 * Delete a saved project.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_project( WP_REST_Request $request ) {
		$result = DPG_Post_Types::delete_project( absint( $request->get_param( 'id' ) ), get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * Generate and store a teacher assignment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_assignment( WP_REST_Request $request ) {
		$params  = $request->get_json_params() ? $request->get_json_params() : $request->get_params();
		$filters = DPG_Security::filters( $params );
		$count   = DPG_Security::int( isset( $params['students'] ) ? $params['students'] : 0, 1, 100, 10 );
		$title   = DPG_Security::text( isset( $params['title'] ) ? $params['title'] : '', 120 );
		$notes   = DPG_Security::textarea( isset( $params['notes'] ) ? $params['notes'] : '', 1000 );
		$due     = DPG_Security::text( isset( $params['due'] ) ? $params['due'] : '', 60 );

		if ( '' === $title ) {
			$title = sprintf(
				/* translators: %s: date. */
				__( 'Assignment — %s', 'design-project-generator' ),
				current_time( get_option( 'date_format' ) )
			);
		}

		$generator = new DPG_Project_Generator( $filters['seed'] );
		$projects  = $generator->generate_set( $filters, $count );

		if ( empty( $projects ) ) {
			return new WP_Error(
				'dpg_no_match',
				__( 'No projects could be generated with those filters.', 'design-project-generator' ),
				array( 'status' => 400 )
			);
		}

		$payload = array(
			'settings' => array(
				'category'   => $filters['category'],
				'difficulty' => $filters['difficulty'],
				'students'   => $count,
				'due'        => $due,
				'notes'      => $notes,
				'seed'       => $generator->seed(),
			),
			'projects' => $projects,
		);

		$post_id = DPG_Post_Types::save_assignment( get_current_user_id(), $title, $payload );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return rest_ensure_response(
			array(
				'created'    => true,
				'assignment' => DPG_Post_Types::read_assignment( $post_id ),
			)
		);
	}
}
