<?php
/**
 * Custom post types used for logged-in storage.
 *
 * Guests keep their projects in localStorage; logged-in users get real
 * WordPress posts so their work survives a change of browser.
 *
 * @package DesignProjectGenerator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post type registration and saved-project storage.
 */
class DPG_Post_Types {

	/**
	 * Saved project post type.
	 */
	const PROJECT = 'dpg_project';

	/**
	 * Teacher assignment post type.
	 */
	const ASSIGNMENT = 'dpg_assignment';

	/**
	 * Meta key holding the project JSON.
	 */
	const META_PROJECT = '_dpg_project';

	/**
	 * Meta key holding the working status.
	 */
	const META_STATUS = '_dpg_status';

	/**
	 * Meta key holding the completion checklist.
	 */
	const META_CHECKLIST = '_dpg_checklist';

	/**
	 * Meta key holding the portfolio case study.
	 */
	const META_CASE_STUDY = '_dpg_case_study';

	/**
	 * Meta key holding the completion timestamp.
	 */
	const META_COMPLETED = '_dpg_completed';

	/**
	 * Meta key holding the assignment payload.
	 */
	const META_ASSIGNMENT = '_dpg_assignment';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register both post types.
	 *
	 * @return void
	 */
	public function register() {
		register_post_type(
			self::PROJECT,
			array(
				'labels'          => array(
					'name'          => __( 'Saved Projects', 'design-project-generator' ),
					'singular_name' => __( 'Saved Project', 'design-project-generator' ),
					'search_items'  => __( 'Search saved projects', 'design-project-generator' ),
					'not_found'     => __( 'No saved projects yet.', 'design-project-generator' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'show_in_rest'    => false,
				'supports'        => array( 'title', 'author' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'has_archive'     => false,
				'rewrite'         => false,
				'query_var'       => false,
				'menu_icon'       => 'dashicons-art',
			)
		);

		register_post_type(
			self::ASSIGNMENT,
			array(
				'labels'          => array(
					'name'          => __( 'Assignments', 'design-project-generator' ),
					'singular_name' => __( 'Assignment', 'design-project-generator' ),
					'not_found'     => __( 'No assignments yet.', 'design-project-generator' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'show_in_rest'    => false,
				'supports'        => array( 'title', 'author' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'has_archive'     => false,
				'rewrite'         => false,
				'query_var'       => false,
				'menu_icon'       => 'dashicons-welcome-learn-more',
			)
		);
	}

	/**
	 * Persist a project for a user.
	 *
	 * @param int   $user_id User identifier.
	 * @param array $project Validated project.
	 * @param array $extra   Status, checklist and case study.
	 * @param int   $post_id Existing post to update, or 0 to insert.
	 * @return int|WP_Error
	 */
	public static function save_project( $user_id, array $project, array $extra = array(), $post_id = 0 ) {
		$user_id = (int) $user_id;
		$post_id = (int) $post_id;

		if ( $user_id <= 0 ) {
			return new WP_Error(
				'dpg_not_logged_in',
				__( 'You must be logged in to save a project to your account.', 'design-project-generator' ),
				array( 'status' => 401 )
			);
		}

		$status = isset( $extra['status'] )
			? DPG_Security::one_of( $extra['status'], DPG_Security::$statuses, 'not-started' )
			: 'not-started';

		$postarr = array(
			'post_type'   => self::PROJECT,
			'post_title'  => $project['id'] . ' — ' . $project['title'],
			'post_status' => 'publish',
			'post_author' => $user_id,
		);

		if ( $post_id > 0 ) {
			$existing = get_post( $post_id );

			if ( ! $existing || self::PROJECT !== $existing->post_type ) {
				return new WP_Error(
					'dpg_not_found',
					__( 'That saved project no longer exists.', 'design-project-generator' ),
					array( 'status' => 404 )
				);
			}

			if ( ! self::user_owns( $existing, $user_id ) ) {
				return new WP_Error(
					'dpg_forbidden',
					__( 'You cannot edit a project that belongs to somebody else.', 'design-project-generator' ),
					array( 'status' => 403 )
				);
			}

			$postarr['ID'] = $post_id;
			unset( $postarr['post_author'] );
		}

		$result = wp_insert_post( $postarr, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$post_id = (int) $result;

		update_post_meta( $post_id, self::META_PROJECT, wp_slash( wp_json_encode( $project ) ) );
		update_post_meta( $post_id, self::META_STATUS, $status );

		if ( isset( $extra['checklist'] ) ) {
			update_post_meta( $post_id, self::META_CHECKLIST, DPG_Security::checklist( $extra['checklist'] ) );
		}

		if ( isset( $extra['case_study'] ) ) {
			update_post_meta( $post_id, self::META_CASE_STUDY, DPG_Security::case_study( $extra['case_study'] ) );
		}

		if ( 'completed' === $status ) {
			update_post_meta( $post_id, self::META_COMPLETED, current_time( 'mysql' ) );
		} else {
			delete_post_meta( $post_id, self::META_COMPLETED );
		}

		return $post_id;
	}

	/**
	 * Whether a user owns a post (or may manage everything).
	 *
	 * @param WP_Post $post    Post object.
	 * @param int     $user_id User identifier.
	 * @return bool
	 */
	public static function user_owns( $post, $user_id ) {
		return ( (int) $post->post_author === (int) $user_id ) || DPG_Security::can_manage();
	}

	/**
	 * Read one saved project into a plain array.
	 *
	 * @param WP_Post|int $post Post.
	 * @return array|null
	 */
	public static function read_project( $post ) {
		$post = get_post( $post );

		if ( ! $post || self::PROJECT !== $post->post_type ) {
			return null;
		}

		$raw     = get_post_meta( $post->ID, self::META_PROJECT, true );
		$project = DPG_Security::validate_project( $raw );

		if ( is_wp_error( $project ) ) {
			return null;
		}

		return array(
			'post_id'    => (int) $post->ID,
			'status'     => (string) get_post_meta( $post->ID, self::META_STATUS, true ),
			'checklist'  => DPG_Security::checklist( get_post_meta( $post->ID, self::META_CHECKLIST, true ) ),
			'case_study' => DPG_Security::case_study( get_post_meta( $post->ID, self::META_CASE_STUDY, true ) ),
			'created'    => get_the_date( 'c', $post ),
			'completed'  => (string) get_post_meta( $post->ID, self::META_COMPLETED, true ),
			'project'    => $project,
		);
	}

	/**
	 * List a user's saved projects.
	 *
	 * @param int $user_id User identifier.
	 * @param int $limit   Maximum rows.
	 * @return array
	 */
	public static function list_projects( $user_id, $limit = 50 ) {
		$limit = DPG_Security::int( $limit, 1, 200, 50 );

		$posts = get_posts(
			array(
				'post_type'        => self::PROJECT,
				'author'           => (int) $user_id,
				'posts_per_page'   => $limit,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		$out = array();

		foreach ( $posts as $post ) {
			$row = self::read_project( $post );

			if ( $row ) {
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * Delete a saved project.
	 *
	 * @param int $post_id Post identifier.
	 * @param int $user_id User identifier.
	 * @return true|WP_Error
	 */
	public static function delete_project( $post_id, $user_id ) {
		$post = get_post( (int) $post_id );

		if ( ! $post || self::PROJECT !== $post->post_type ) {
			return new WP_Error(
				'dpg_not_found',
				__( 'That saved project no longer exists.', 'design-project-generator' ),
				array( 'status' => 404 )
			);
		}

		if ( ! self::user_owns( $post, $user_id ) ) {
			return new WP_Error(
				'dpg_forbidden',
				__( 'You cannot delete a project that belongs to somebody else.', 'design-project-generator' ),
				array( 'status' => 403 )
			);
		}

		wp_delete_post( $post->ID, true );

		return true;
	}

	/**
	 * Store a generated assignment.
	 *
	 * @param int    $user_id  Teacher user identifier.
	 * @param string $title    Assignment title.
	 * @param array  $payload  Settings and generated projects.
	 * @return int|WP_Error
	 */
	public static function save_assignment( $user_id, $title, array $payload ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::ASSIGNMENT,
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_author' => (int) $user_id,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, self::META_ASSIGNMENT, wp_slash( wp_json_encode( $payload ) ) );

		return (int) $post_id;
	}

	/**
	 * Read a stored assignment.
	 *
	 * @param WP_Post|int $post Post.
	 * @return array|null
	 */
	public static function read_assignment( $post ) {
		$post = get_post( $post );

		if ( ! $post || self::ASSIGNMENT !== $post->post_type ) {
			return null;
		}

		$payload = json_decode( (string) get_post_meta( $post->ID, self::META_ASSIGNMENT, true ), true );

		if ( ! is_array( $payload ) ) {
			return null;
		}

		$projects = array();

		foreach ( (array) ( isset( $payload['projects'] ) ? $payload['projects'] : array() ) as $project ) {
			$clean = DPG_Security::validate_project( $project );

			if ( ! is_wp_error( $clean ) ) {
				$projects[] = $clean;
			}
		}

		return array(
			'post_id'  => (int) $post->ID,
			'title'    => get_the_title( $post ),
			'created'  => get_the_date( 'c', $post ),
			'settings' => isset( $payload['settings'] ) ? (array) $payload['settings'] : array(),
			'projects' => $projects,
		);
	}
}
