<?php
/**
 * Local project database.
 *
 * Reads the bundled JSON data files, merges in anything the site owner has
 * added through the admin screens, and hides anything they have disabled.
 * No network access and no external service is involved at any point.
 *
 * @package DesignProjectGenerator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Data access layer.
 */
class DPG_Project_Database {

	/**
	 * Option holding site-authored additions.
	 */
	const CUSTOM_OPTION = 'dpg_custom_data';

	/**
	 * Option holding disabled record identifiers.
	 */
	const DISABLED_OPTION = 'dpg_disabled_data';

	/**
	 * Datasets that may be extended from the admin screens.
	 *
	 * @var string[]
	 */
	const EXTENDABLE = array( 'industries', 'styles', 'palettes', 'challenges', 'restrictions', 'hints' );

	/**
	 * Runtime cache of decoded files.
	 *
	 * @var array<string,array>
	 */
	private static $cache = array();

	/**
	 * Read and decode one bundled data file.
	 *
	 * @param string $name File name without extension.
	 * @return array
	 */
	public static function file( $name ) {
		$name = DPG_Security::key( $name );

		if ( isset( self::$cache[ $name ] ) ) {
			return self::$cache[ $name ];
		}

		$path = DPG_PLUGIN_DIR . 'data/' . $name . '.json';
		$data = array();

		if ( is_readable( $path ) ) {
			$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local bundled file.
			if ( false !== $raw ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					$data = $decoded;
				}
			}
		}

		self::$cache[ $name ] = $data;

		return $data;
	}

	/**
	 * Site-authored additions.
	 *
	 * @param string $dataset Dataset key.
	 * @return array
	 */
	public static function custom( $dataset ) {
		$all = get_option( self::CUSTOM_OPTION, array() );

		return isset( $all[ $dataset ] ) && is_array( $all[ $dataset ] ) ? array_values( $all[ $dataset ] ) : array();
	}

	/**
	 * Replace the site-authored additions for one dataset.
	 *
	 * @param string $dataset Dataset key.
	 * @param array  $records Records to store.
	 * @return void
	 */
	public static function set_custom( $dataset, array $records ) {
		$all             = get_option( self::CUSTOM_OPTION, array() );
		$all             = is_array( $all ) ? $all : array();
		$all[ $dataset ] = array_values( $records );

		update_option( self::CUSTOM_OPTION, $all, false );
	}

	/**
	 * Identifiers hidden by the site owner.
	 *
	 * @param string $dataset Dataset key.
	 * @return string[]
	 */
	public static function disabled( $dataset ) {
		$all = get_option( self::DISABLED_OPTION, array() );

		return isset( $all[ $dataset ] ) && is_array( $all[ $dataset ] ) ? array_map( 'strval', $all[ $dataset ] ) : array();
	}

	/**
	 * Store the hidden identifiers for one dataset.
	 *
	 * @param string   $dataset Dataset key.
	 * @param string[] $ids     Identifiers.
	 * @return void
	 */
	public static function set_disabled( $dataset, array $ids ) {
		$all             = get_option( self::DISABLED_OPTION, array() );
		$all             = is_array( $all ) ? $all : array();
		$all[ $dataset ] = array_values( array_unique( array_map( array( 'DPG_Security', 'key' ), $ids ) ) );

		update_option( self::DISABLED_OPTION, $all, false );
	}

	/**
	 * Merge bundled records with custom ones and drop disabled records.
	 *
	 * @param string $dataset Dataset key.
	 * @param array  $records Bundled records.
	 * @return array
	 */
	private static function apply_overrides( $dataset, array $records ) {
		$records  = array_merge( $records, self::custom( $dataset ) );
		$disabled = self::disabled( $dataset );

		if ( ! empty( $disabled ) ) {
			$records = array_values(
				array_filter(
					$records,
					static function ( $record ) use ( $disabled ) {
						return empty( $record['id'] ) || ! in_array( (string) $record['id'], $disabled, true );
					}
				)
			);
		}

		return array_values( $records );
	}

	/**
	 * Every category with its project types.
	 *
	 * @return array
	 */
	public static function categories() {
		$data       = self::file( 'projects' );
		$categories = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : array();
		$categories = self::apply_overrides( 'categories', $categories );

		$disabled_types = self::disabled( 'types' );
		$custom_types   = self::custom( 'types' );

		foreach ( $categories as $index => $category ) {
			$types = isset( $category['types'] ) && is_array( $category['types'] ) ? $category['types'] : array();

			// Site-authored project types are filed under the category they
			// were created in.
			foreach ( $custom_types as $custom ) {
				if ( isset( $custom['category_id'] ) && $custom['category_id'] === $category['id'] ) {
					$types[] = $custom;
				}
			}

			if ( ! empty( $disabled_types ) ) {
				$types = array_values(
					array_filter(
						$types,
						static function ( $type ) use ( $disabled_types ) {
							return empty( $type['id'] ) || ! in_array( (string) $type['id'], $disabled_types, true );
						}
					)
				);
			}

			$categories[ $index ]['types'] = $types;
		}

		return array_values(
			array_filter(
				$categories,
				static function ( $category ) {
					return ! empty( $category['types'] );
				}
			)
		);
	}

	/**
	 * One category by identifier.
	 *
	 * @param string $id Category identifier.
	 * @return array|null
	 */
	public static function category( $id ) {
		foreach ( self::categories() as $category ) {
			if ( isset( $category['id'] ) && $category['id'] === $id ) {
				return $category;
			}
		}

		return null;
	}

	/**
	 * Flat list of project types, each carrying its category.
	 *
	 * @param string $category_id Optional category filter.
	 * @return array
	 */
	public static function types( $category_id = '' ) {
		$out = array();

		foreach ( self::categories() as $category ) {
			if ( $category_id && $category['id'] !== $category_id ) {
				continue;
			}
			foreach ( $category['types'] as $type ) {
				$type['category_id']   = $category['id'];
				$type['category_name'] = $category['name'];
				$out[]                 = $type;
			}
		}

		return $out;
	}

	/**
	 * One project type by identifier.
	 *
	 * @param string $id Type identifier.
	 * @return array|null
	 */
	public static function type( $id ) {
		foreach ( self::types() as $type ) {
			if ( isset( $type['id'] ) && $type['id'] === $id ) {
				return $type;
			}
		}

		return null;
	}

	/**
	 * Industries.
	 *
	 * @return array
	 */
	public static function industries() {
		$data = self::file( 'industries' );

		return self::apply_overrides( 'industries', isset( $data['industries'] ) ? $data['industries'] : array() );
	}

	/**
	 * One industry by identifier.
	 *
	 * @param string $id Industry identifier.
	 * @return array|null
	 */
	public static function industry( $id ) {
		foreach ( self::industries() as $industry ) {
			if ( isset( $industry['id'] ) && $industry['id'] === $id ) {
				return $industry;
			}
		}

		return null;
	}

	/**
	 * Design styles.
	 *
	 * @return array
	 */
	public static function styles() {
		$data = self::file( 'styles' );

		return self::apply_overrides( 'styles', isset( $data['styles'] ) ? $data['styles'] : array() );
	}

	/**
	 * Colour palettes.
	 *
	 * @return array
	 */
	public static function palettes() {
		$data = self::file( 'palettes' );

		return self::apply_overrides( 'palettes', isset( $data['palettes'] ) ? $data['palettes'] : array() );
	}

	/**
	 * Typography pairings.
	 *
	 * @return array
	 */
	public static function typography() {
		$data = self::file( 'typography' );

		return self::apply_overrides( 'typography', isset( $data['pairings'] ) ? $data['pairings'] : array() );
	}

	/**
	 * Challenges.
	 *
	 * @return array
	 */
	public static function challenges() {
		$data = self::file( 'challenges' );

		return self::apply_overrides( 'challenges', isset( $data['challenges'] ) ? $data['challenges'] : array() );
	}

	/**
	 * Timer presets.
	 *
	 * @return array
	 */
	public static function timer_presets() {
		$data = self::file( 'challenges' );

		return isset( $data['timer_presets'] ) ? $data['timer_presets'] : array();
	}

	/**
	 * Restrictions.
	 *
	 * @return array
	 */
	public static function restrictions() {
		$data = self::file( 'restrictions' );

		return self::apply_overrides( 'restrictions', isset( $data['restrictions'] ) ? $data['restrictions'] : array() );
	}

	/**
	 * Success criteria sentences.
	 *
	 * @return string[]
	 */
	public static function success_criteria() {
		$data = self::file( 'restrictions' );

		return isset( $data['success_criteria'] ) ? $data['success_criteria'] : array();
	}

	/**
	 * "I'm stuck" hints.
	 *
	 * @return array
	 */
	public static function hints() {
		$data = self::file( 'hints' );

		return self::apply_overrides( 'hints', isset( $data['hints'] ) ? $data['hints'] : array() );
	}

	/**
	 * Solution hints shown from the More menu.
	 *
	 * @return string[]
	 */
	public static function solution_hints() {
		$data = self::file( 'hints' );

		return isset( $data['solution_hints'] ) ? $data['solution_hints'] : array();
	}

	/**
	 * Generic brief ingredients (objectives, audiences, budgets and so on).
	 *
	 * @param string $key Key inside styles.json.
	 * @return array
	 */
	public static function pool( $key ) {
		$data = self::file( 'styles' );

		return isset( $data[ $key ] ) && is_array( $data[ $key ] ) ? $data[ $key ] : array();
	}

	/**
	 * Demo templates present on disk.
	 *
	 * @return array<string,array>
	 */
	public static function demo_templates() {
		$dir = DPG_PLUGIN_DIR . 'templates';
		$out = array();

		if ( ! is_dir( $dir ) ) {
			return $out;
		}

		$entries = scandir( $dir );
		if ( false === $entries ) {
			return $out;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$id = DPG_Security::key( $entry );
			if ( '' === $id || ! is_dir( $dir . '/' . $entry ) ) {
				continue;
			}
			if ( ! is_readable( $dir . '/' . $id . '/template.html' ) ) {
				continue;
			}

			$out[ $id ] = array(
				'id'    => $id,
				'name'  => ucwords( str_replace( '-', ' ', $id ) ),
				'has_css' => is_readable( $dir . '/' . $id . '/template.css' ),
				'has_js'  => is_readable( $dir . '/' . $id . '/template.js' ),
			);
		}

		return $out;
	}

	/**
	 * Read a demo template's three source files.
	 *
	 * @param string $id Template identifier.
	 * @return array|null
	 */
	public static function demo_template( $id ) {
		$id = DPG_Security::key( $id );

		if ( '' === $id || 'none' === $id ) {
			return null;
		}

		$base = DPG_PLUGIN_DIR . 'templates/' . $id . '/';
		$real = realpath( $base );
		$root = realpath( DPG_PLUGIN_DIR . 'templates' );

		if ( ! $real || ! $root || 0 !== strpos( $real, $root ) || ! is_readable( $base . 'template.html' ) ) {
			return null;
		}

		$read = static function ( $file ) {
			return is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local bundled file.
		};

		return array(
			'id'   => $id,
			'html' => $read( $base . 'template.html' ),
			'css'  => $read( $base . 'template.css' ),
			'js'   => $read( $base . 'template.js' ),
		);
	}

	/**
	 * Summary counts used by the admin dashboard.
	 *
	 * @return array<string,int>
	 */
	public static function stats() {
		$types = self::types();

		return array(
			'categories'   => count( self::categories() ),
			'types'        => count( $types ),
			'industries'   => count( self::industries() ),
			'styles'       => count( self::styles() ),
			'palettes'     => count( self::palettes() ),
			'typography'   => count( self::typography() ),
			'challenges'   => count( self::challenges() ),
			'restrictions' => count( self::restrictions() ),
			'hints'        => count( self::hints() ),
			'templates'    => count( self::demo_templates() ),
		);
	}

	/**
	 * Rough count of distinct briefs the engine can assemble.
	 *
	 * @return int
	 */
	public static function combination_count() {
		$stats = self::stats();
		$count = max( 1, $stats['types'] )
			* max( 1, $stats['industries'] )
			* max( 1, $stats['styles'] )
			* max( 1, $stats['palettes'] )
			* count( DPG_Security::$difficulties );

		return (int) $count;
	}

	/**
	 * Clear the runtime cache. Used by tools and tests.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = array();
	}
}
