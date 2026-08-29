<?php
/**
 * Sanitising, escaping and validation helpers.
 *
 * Every value that arrives from a request — and every value that is read back
 * out of storage before it is rendered — passes through this class.
 *
 * @package DesignProjectGenerator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Security helpers.
 */
class DPG_Security {

	/**
	 * Capability required to manage plugin data and settings.
	 */
	const MANAGE_CAP = 'manage_options';

	/**
	 * Capability required to use teacher / assignment features.
	 */
	const TEACH_CAP = 'edit_posts';

	/**
	 * Allowed difficulty identifiers.
	 *
	 * @var string[]
	 */
	public static $difficulties = array( 'beginner', 'intermediate', 'advanced', 'expert' );

	/**
	 * Allowed saved-project statuses.
	 *
	 * @var string[]
	 */
	public static $statuses = array( 'not-started', 'in-progress', 'completed' );

	/**
	 * Allowed regeneration modes.
	 *
	 * @var string[]
	 */
	public static $modes = array( 'random', 'same-industry', 'same-difficulty', 'same-type', 'same-category', 'harder', 'easier' );

	/**
	 * Whether the current user may manage plugin data.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( self::MANAGE_CAP );
	}

	/**
	 * Whether the current user may create assignments.
	 *
	 * @return bool
	 */
	public static function can_teach() {
		return current_user_can( self::TEACH_CAP );
	}

	/**
	 * Sanitise a slug-like identifier.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function key( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = strtolower( (string) $value );
		$value = preg_replace( '/[^a-z0-9_\-]/', '', $value );

		return (string) substr( (string) $value, 0, 64 );
	}

	/**
	 * Sanitise a plain-text string of bounded length.
	 *
	 * @param mixed $value  Raw value.
	 * @param int   $length Maximum length.
	 * @return string
	 */
	public static function text( $value, $length = 300 ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = sanitize_text_field( wp_unslash( (string) $value ) );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $length );
		}

		return substr( $value, 0, $length );
	}

	/**
	 * Sanitise multi-line user prose (case study notes and similar).
	 *
	 * @param mixed $value  Raw value.
	 * @param int   $length Maximum length.
	 * @return string
	 */
	public static function textarea( $value, $length = 4000 ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = sanitize_textarea_field( wp_unslash( (string) $value ) );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $length );
		}

		return substr( $value, 0, $length );
	}

	/**
	 * Sanitise a list of plain-text strings.
	 *
	 * @param mixed $value  Raw value.
	 * @param int   $max    Maximum number of items.
	 * @param int   $length Maximum length of each item.
	 * @return string[]
	 */
	public static function text_list( $value, $max = 24, $length = 300 ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();
		foreach ( $value as $item ) {
			if ( count( $out ) >= $max ) {
				break;
			}
			$clean = self::text( $item, $length );
			if ( '' !== $clean ) {
				$out[] = $clean;
			}
		}

		return $out;
	}

	/**
	 * Sanitise a six-digit hex colour, falling back to a default.
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $fallback Fallback colour.
	 * @return string
	 */
	public static function hex( $value, $fallback = '#000000' ) {
		if ( is_string( $value ) && preg_match( '/^#?([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/', trim( $value ), $m ) ) {
			$hex = strtolower( $m[1] );
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}

			return '#' . $hex;
		}

		return $fallback;
	}

	/**
	 * Constrain an integer to a range.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Minimum.
	 * @param int   $max   Maximum.
	 * @param int   $default_value Default when not numeric.
	 * @return int
	 */
	public static function int( $value, $min, $max, $default_value = 0 ) {
		if ( ! is_numeric( $value ) ) {
			return $default_value;
		}

		return (int) max( $min, min( $max, (int) $value ) );
	}

	/**
	 * Constrain a value to a whitelist.
	 *
	 * @param mixed    $value   Raw value.
	 * @param string[] $allowed Allowed values.
	 * @param string   $default_value Fallback.
	 * @return string
	 */
	public static function one_of( $value, array $allowed, $default_value = '' ) {
		$value = self::key( $value );

		return in_array( $value, $allowed, true ) ? $value : $default_value;
	}

	/**
	 * Sanitise the generator filter payload.
	 *
	 * @param array $raw Raw request data.
	 * @return array
	 */
	public static function filters( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();

		return array(
			'category'   => self::key( isset( $raw['category'] ) ? $raw['category'] : '' ),
			'type'       => self::key( isset( $raw['type'] ) ? $raw['type'] : '' ),
			'industry'   => self::key( isset( $raw['industry'] ) ? $raw['industry'] : '' ),
			'style'      => self::key( isset( $raw['style'] ) ? $raw['style'] : '' ),
			'difficulty' => self::one_of( isset( $raw['difficulty'] ) ? $raw['difficulty'] : '', self::$difficulties, '' ),
			'mode'       => self::one_of( isset( $raw['mode'] ) ? $raw['mode'] : '', self::$modes, 'random' ),
			'seed'       => isset( $raw['seed'] ) && is_numeric( $raw['seed'] ) ? abs( (int) $raw['seed'] ) : 0,
			'demo_only'  => ! empty( $raw['demo_only'] ),
		);
	}

	/**
	 * Validate and normalise a generated project structure.
	 *
	 * Projects arrive back from the browser when they are saved, so the whole
	 * structure is rebuilt field by field rather than trusted.
	 *
	 * @param mixed $project Raw project.
	 * @return array|WP_Error
	 */
	public static function validate_project( $project ) {
		if ( is_string( $project ) ) {
			$project = json_decode( $project, true );
		}

		if ( ! is_array( $project ) || empty( $project['id'] ) || empty( $project['title'] ) ) {
			return new WP_Error(
				'dpg_invalid_project',
				__( 'The project data was missing or malformed.', 'design-project-generator' ),
				array( 'status' => 400 )
			);
		}

		$clean = array(
			'id'              => self::text( $project['id'], 32 ),
			'title'           => self::text( $project['title'], 160 ),
			'category'        => self::text( self::pick( $project, 'category' ), 80 ),
			'category_id'     => self::key( self::pick( $project, 'category_id' ) ),
			'project_type'    => self::text( self::pick( $project, 'project_type' ), 80 ),
			'project_type_id' => self::key( self::pick( $project, 'project_type_id' ) ),
			'industry'        => self::text( self::pick( $project, 'industry' ), 80 ),
			'industry_id'     => self::key( self::pick( $project, 'industry_id' ) ),
			'difficulty'      => self::one_of( self::pick( $project, 'difficulty' ), self::$difficulties, 'beginner' ),
			'client'          => self::text( self::pick( $project, 'client' ), 120 ),
			'product'         => self::text( self::pick( $project, 'product' ), 160 ),
			'background'      => self::textarea( self::pick( $project, 'background' ), 1200 ),
			'objective'       => self::textarea( self::pick( $project, 'objective' ), 600 ),
			'audience'        => self::text( self::pick( $project, 'audience' ), 200 ),
			'style'           => self::text( self::pick( $project, 'style' ), 120 ),
			'style_id'        => self::key( self::pick( $project, 'style_id' ) ),
			'style_direction' => self::textarea( self::pick( $project, 'style_direction' ), 600 ),
			'color_direction' => self::text( self::pick( $project, 'color_direction' ), 200 ),
			'typography'      => self::text( self::pick( $project, 'typography' ), 200 ),
			'typography_note' => self::textarea( self::pick( $project, 'typography_note' ), 400 ),
			'personality'     => self::text( self::pick( $project, 'personality' ), 160 ),
			'budget'          => self::text( self::pick( $project, 'budget' ), 120 ),
			'deadline'        => self::text( self::pick( $project, 'deadline' ), 120 ),
			'estimated_time'  => self::int( self::pick( $project, 'estimated_time' ), 5, 6000, 60 ),
			'demo_type'       => self::key( self::pick( $project, 'demo_type' ) ),
			'seed'            => self::int( self::pick( $project, 'seed' ), 0, PHP_INT_MAX, 0 ),
			'deliverables'    => self::text_list( self::pick( $project, 'deliverables', array() ), 16, 160 ),
			'restrictions'    => self::text_list( self::pick( $project, 'restrictions', array() ), 12, 240 ),
			'content'         => self::text_list( self::pick( $project, 'content', array() ), 16, 160 ),
			'success'         => self::text_list( self::pick( $project, 'success', array() ), 12, 240 ),
			'competitors'     => self::text_list( self::pick( $project, 'competitors', array() ), 8, 200 ),
			'tags'            => array_values( array_filter( array_map( array( __CLASS__, 'key' ), (array) self::pick( $project, 'tags', array() ) ) ) ),
		);

		$clean['palette']   = self::palette( self::pick( $project, 'palette', array() ) );
		$clean['challenge'] = self::challenge( self::pick( $project, 'challenge', array() ) );

		if ( '' === $clean['demo_type'] ) {
			$clean['demo_type'] = 'none';
		}

		return $clean;
	}

	/**
	 * Read a key from an array with a default.
	 *
	 * @param array  $source  Source array.
	 * @param string $key     Key.
	 * @param mixed  $default_value Default.
	 * @return mixed
	 */
	private static function pick( $source, $key, $default_value = '' ) {
		return isset( $source[ $key ] ) ? $source[ $key ] : $default_value;
	}

	/**
	 * Validate a palette structure.
	 *
	 * @param mixed $palette Raw palette.
	 * @return array
	 */
	public static function palette( $palette ) {
		$defaults = array(
			'primary'    => '#1f2937',
			'secondary'  => '#4b5563',
			'accent'     => '#f59e0b',
			'background' => '#f9fafb',
			'text'       => '#111827',
		);

		$palette = is_array( $palette ) ? $palette : array();
		$colors  = isset( $palette['colors'] ) && is_array( $palette['colors'] ) ? $palette['colors'] : $palette;

		$clean = array();
		foreach ( $defaults as $role => $fallback ) {
			$clean[ $role ] = self::hex( isset( $colors[ $role ] ) ? $colors[ $role ] : '', $fallback );
		}

		return array(
			'id'     => self::key( isset( $palette['id'] ) ? $palette['id'] : '' ),
			'name'   => self::text( isset( $palette['name'] ) ? $palette['name'] : '', 80 ),
			'colors' => $clean,
		);
	}

	/**
	 * Validate a challenge structure.
	 *
	 * @param mixed $challenge Raw challenge.
	 * @return array
	 */
	public static function challenge( $challenge ) {
		$challenge = is_array( $challenge ) ? $challenge : array();

		return array(
			'id'       => self::key( isset( $challenge['id'] ) ? $challenge['id'] : '' ),
			'type'     => self::one_of(
				isset( $challenge['type'] ) ? $challenge['type'] : '',
				array( 'timer', 'color', 'typography', 'layout', 'minimalism', 'concept', 'constraint' ),
				'concept'
			),
			'name'     => self::text( isset( $challenge['name'] ) ? $challenge['name'] : '', 80 ),
			'prompt'   => self::text( isset( $challenge['prompt'] ) ? $challenge['prompt'] : '', 400 ),
			'duration' => self::int( isset( $challenge['duration'] ) ? $challenge['duration'] : 0, 0, 86400, 0 ),
		);
	}

	/**
	 * Validate the free-text case study payload.
	 *
	 * @param mixed $case_study Raw case study.
	 * @return array
	 */
	public static function case_study( $case_study ) {
		$case_study = is_array( $case_study ) ? $case_study : array();

		return array(
			'concept'    => self::textarea( isset( $case_study['concept'] ) ? $case_study['concept'] : '' ),
			'process'    => self::textarea( isset( $case_study['process'] ) ? $case_study['process'] : '' ),
			'learned'    => self::textarea( isset( $case_study['learned'] ) ? $case_study['learned'] : '' ),
			'improve'    => self::textarea( isset( $case_study['improve'] ) ? $case_study['improve'] : '' ),
			'final_note' => self::textarea( isset( $case_study['final_note'] ) ? $case_study['final_note'] : '' ),
		);
	}

	/**
	 * Validate the checklist used for the completion score.
	 *
	 * @param mixed $checklist Raw checklist.
	 * @return array<string,bool>
	 */
	public static function checklist( $checklist ) {
		$allowed = array( 'content', 'cta', 'palette', 'deliverables', 'exported' );
		$clean   = array();

		if ( is_array( $checklist ) ) {
			foreach ( $allowed as $item ) {
				$clean[ $item ] = ! empty( $checklist[ $item ] );
			}
		}

		return $clean;
	}
}
