<?php
/**
 * The randomisation engine.
 *
 * Briefs are assembled from structured parts rather than picked from a list of
 * pre-written paragraphs, which is what produces the very large number of
 * distinct combinations without any external service.
 *
 * Every generator run is driven by an explicit integer seed, so the same seed
 * always rebuilds the same brief. That is what makes the daily challenge, the
 * shareable project link and reproducible teacher assignments possible.
 *
 * @package DesignProjectGenerator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Project generator.
 */
class DPG_Project_Generator {

	/**
	 * Current PRNG state.
	 *
	 * @var int
	 */
	private $state;

	/**
	 * The seed this instance was created with.
	 *
	 * @var int
	 */
	private $seed;

	/**
	 * Number of deliverables per difficulty (minimum, maximum).
	 *
	 * @var array<string,int[]>
	 */
	private static $deliverable_counts = array(
		'beginner'     => array( 1, 1 ),
		'intermediate' => array( 2, 3 ),
		'advanced'     => array( 3, 5 ),
		'expert'       => array( 4, 7 ),
	);

	/**
	 * Number of restrictions per difficulty.
	 *
	 * @var array<string,int[]>
	 */
	private static $restriction_counts = array(
		'beginner'     => array( 1, 2 ),
		'intermediate' => array( 3, 3 ),
		'advanced'     => array( 3, 4 ),
		'expert'       => array( 4, 5 ),
	);

	/**
	 * Estimated-time multiplier per difficulty.
	 *
	 * @var array<string,float>
	 */
	private static $time_multiplier = array(
		'beginner'     => 0.6,
		'intermediate' => 1.0,
		'advanced'     => 1.6,
		'expert'       => 2.4,
	);

	/**
	 * Restriction tags that suit each category.
	 *
	 * @var array<string,string[]>
	 */
	private static $category_tags = array(
		'branding'     => array( 'brand', 'colour', 'type', 'production', 'flexibility', 'legibility' ),
		'social-media' => array( 'digital', 'copy', 'colour', 'flexibility', 'legibility' ),
		'marketing'    => array( 'print', 'copy', 'legibility', 'colour', 'layout' ),
		'ui-ux'        => array( 'digital', 'accessibility', 'layout', 'type', 'flexibility' ),
		'web-design'   => array( 'digital', 'accessibility', 'layout', 'colour', 'flexibility' ),
		'typography'   => array( 'type', 'layout', 'legibility', 'colour' ),
		'illustration' => array( 'style', 'colour', 'production', 'layout' ),
		'print'        => array( 'print', 'production', 'type', 'colour', 'legibility' ),
	);

	/**
	 * Constructor.
	 *
	 * @param int $seed Optional seed. A random one is chosen when omitted.
	 */
	public function __construct( $seed = 0 ) {
		$seed = (int) $seed;

		if ( $seed <= 0 ) {
			$seed = wp_rand( 1, 2147483646 );
		}

		$this->seed  = $seed;
		$this->state = $seed % 2147483647;

		if ( $this->state <= 0 ) {
			$this->state += 2147483646;
		}
	}

	/**
	 * The seed in use.
	 *
	 * @return int
	 */
	public function seed() {
		return $this->seed;
	}

	/**
	 * Lehmer / Park-Miller generator.
	 *
	 * Implemented here rather than using mt_rand() so that a seed produces the
	 * same brief on every server and PHP version.
	 *
	 * @return int Pseudo-random integer between 1 and 2147483646.
	 */
	private function next() {
		// Schrage's method keeps every intermediate value inside a signed
		// 32-bit range, so the sequence is identical on 32-bit builds.
		$hi = (int) ( $this->state / 127773 );
		$lo = $this->state % 127773;

		$this->state = ( 16807 * $lo ) - ( 2836 * $hi );

		if ( $this->state <= 0 ) {
			$this->state += 2147483647;
		}

		return $this->state;
	}

	/**
	 * Random integer within an inclusive range.
	 *
	 * @param int $min Minimum.
	 * @param int $max Maximum.
	 * @return int
	 */
	private function range( $min, $max ) {
		$min = (int) $min;
		$max = (int) $max;

		if ( $max <= $min ) {
			return $min;
		}

		return $min + ( $this->next() % ( $max - $min + 1 ) );
	}

	/**
	 * Pick one item from a list.
	 *
	 * @param array $items Items.
	 * @return mixed|null
	 */
	private function pick( $items ) {
		$items = array_values( is_array( $items ) ? $items : array() );

		if ( empty( $items ) ) {
			return null;
		}

		return $items[ $this->range( 0, count( $items ) - 1 ) ];
	}

	/**
	 * Shuffle a list deterministically.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	private function shuffle( $items ) {
		$items = array_values( is_array( $items ) ? $items : array() );

		for ( $i = count( $items ) - 1; $i > 0; $i-- ) {
			$j            = $this->range( 0, $i );
			$tmp          = $items[ $i ];
			$items[ $i ]  = $items[ $j ];
			$items[ $j ]  = $tmp;
		}

		return $items;
	}

	/**
	 * Take up to N items from a list, in random order.
	 *
	 * @param array $items Items.
	 * @param int   $count How many to take.
	 * @return array
	 */
	private function sample( $items, $count ) {
		return array_slice( $this->shuffle( $items ), 0, max( 0, (int) $count ) );
	}

	/**
	 * Generate one project.
	 *
	 * @param array $filters  Sanitised filters.
	 * @param array $previous Previously generated project, for "same as" modes.
	 * @return array|WP_Error
	 */
	public function generate( $filters = array(), $previous = array() ) {
		$filters  = wp_parse_args(
			is_array( $filters ) ? $filters : array(),
			array(
				'category'   => '',
				'type'       => '',
				'industry'   => '',
				'style'      => '',
				'difficulty' => '',
				'mode'       => 'random',
				'demo_only'  => false,
			)
		);
		$previous = is_array( $previous ) ? $previous : array();

		$filters = $this->apply_mode( $filters, $previous );

		$type = $this->choose_type( $filters );
		if ( ! $type ) {
			return new WP_Error(
				'dpg_no_match',
				__( 'No project type matches those filters. Try widening them.', 'design-project-generator' ),
				array( 'status' => 404 )
			);
		}

		// Every choice below draws from the generator exactly once whether or
		// not it is pinned by a filter. Keeping the draw sequence aligned is
		// what lets a seed plus the resolved filters rebuild a brief byte for
		// byte, which is what the share link and the daily challenge rely on.
		$difficulty = $this->pick( DPG_Security::$difficulties );
		$difficulty = $filters['difficulty'] ? $filters['difficulty'] : $difficulty;
		$industry   = $this->choose( DPG_Project_Database::industries(), $filters['industry'] );
		$style      = $this->choose( DPG_Project_Database::styles(), $filters['style'] );
		$palette    = $this->choose_palette( $style );
		$typography = $this->pick( DPG_Project_Database::typography() );

		if ( ! $industry || ! $style ) {
			return new WP_Error(
				'dpg_no_data',
				__( 'The project database is incomplete. Check the plugin data files.', 'design-project-generator' ),
				array( 'status' => 500 )
			);
		}

		$client  = (string) $this->pick( $industry['clients'] );
		$product = (string) $this->pick( isset( $industry['products'] ) ? $industry['products'] : array() );

		$tokens = array(
			'{client}'   => $client,
			'{product}'  => $product,
			'{industry}' => strtolower( $industry['name'] ),
		);

		// Industry audiences almost always win: a generic audience attached to
		// a specific client is the fastest way to make a brief feel fake.
		$audience = $this->range( 0, 100 ) < 92 && ! empty( $industry['audiences'] )
			? (string) $this->pick( $industry['audiences'] )
			: (string) $this->pick( DPG_Project_Database::pool( 'audiences' ) );

		$objective = $this->range( 0, 100 ) < 80 && ! empty( $industry['objectives'] )
			? (string) $this->pick( $industry['objectives'] )
			: (string) $this->pick( DPG_Project_Database::pool( 'objectives' ) );

		$background = (string) $this->pick( isset( $industry['contexts'] ) ? $industry['contexts'] : array() );

		$project = array(
			'id'              => $this->project_id(),
			'seed'            => $this->seed,
			'title'           => $this->title( $type, $product ),
			'category'        => $type['category_name'],
			'category_id'     => $type['category_id'],
			'project_type'    => $type['name'],
			'project_type_id' => $type['id'],
			'industry'        => $industry['name'],
			'industry_id'     => $industry['id'],
			'difficulty'      => $difficulty,
			'client'          => $client,
			'product'         => $product,
			'background'      => $this->fill( $background, $tokens ),
			'objective'       => $this->sentence( $this->fill( $objective, $tokens ) ),
			'audience'        => $audience,
			'style'           => $style['name'],
			'style_id'        => $style['id'],
			'style_direction' => isset( $style['direction'] ) ? $style['direction'] : '',
			'color_direction' => $this->color_direction( $palette ),
			'typography'      => $this->typography_direction( $typography ),
			'typography_note' => isset( $typography['note'] ) ? $typography['note'] : '',
			'palette'         => DPG_Security::palette( $palette ),
			'estimated_time'  => $this->estimated_time( $type, $difficulty ),
			'demo_type'       => $this->demo_type( $type ),
			'deliverables'    => $this->deliverables( $type, $difficulty ),
			'restrictions'    => $this->restrictions( $type, $difficulty ),
			'content'         => $this->content( $type, $difficulty ),
			'success'         => $this->success_criteria( $difficulty ),
			'challenge'       => $this->challenge( $difficulty ),
			'tags'            => array( $type['category_id'], $type['id'], $industry['id'], $style['id'], $difficulty ),
			'personality'     => '',
			'competitors'     => array(),
			'budget'          => '',
			'deadline'        => '',
		);

		// Advanced and expert briefs carry the extra client-simulation fields.
		if ( in_array( $difficulty, array( 'advanced', 'expert' ), true ) ) {
			$project['personality'] = (string) $this->pick( DPG_Project_Database::pool( 'personalities' ) );
			$project['competitors'] = $this->sample(
				isset( $industry['competitors'] ) ? $industry['competitors'] : array(),
				'expert' === $difficulty ? 3 : 2
			);
		}

		if ( 'expert' === $difficulty ) {
			$project['budget']   = (string) $this->pick( DPG_Project_Database::pool( 'budgets' ) );
			$project['deadline'] = (string) $this->pick( DPG_Project_Database::pool( 'deadlines' ) );
		}

		$project['typography_note'] = $this->fill( $project['typography_note'], $tokens );

		/**
		 * Filter a freshly generated project.
		 *
		 * @param array                 $project   Generated project.
		 * @param array                 $filters   Filters used.
		 * @param DPG_Project_Generator $generator Generator instance.
		 */
		return apply_filters( 'dpg_generated_project', $project, $filters, $this );
	}

	/**
	 * Translate a regeneration mode into concrete filters.
	 *
	 * @param array $filters  Filters.
	 * @param array $previous Previous project.
	 * @return array
	 */
	private function apply_mode( array $filters, array $previous ) {
		$mode = isset( $filters['mode'] ) ? $filters['mode'] : 'random';

		if ( 'random' === $mode || empty( $previous ) ) {
			return $filters;
		}

		switch ( $mode ) {
			case 'same-industry':
				$filters['industry'] = isset( $previous['industry_id'] ) ? $previous['industry_id'] : $filters['industry'];
				break;

			case 'same-difficulty':
				$filters['difficulty'] = isset( $previous['difficulty'] ) ? $previous['difficulty'] : $filters['difficulty'];
				break;

			case 'same-type':
				$filters['type'] = isset( $previous['project_type_id'] ) ? $previous['project_type_id'] : $filters['type'];
				break;

			case 'same-category':
				$filters['category'] = isset( $previous['category_id'] ) ? $previous['category_id'] : $filters['category'];
				break;

			case 'harder':
			case 'easier':
				$current = isset( $previous['difficulty'] ) ? $previous['difficulty'] : 'beginner';
				$index   = array_search( $current, DPG_Security::$difficulties, true );
				$index   = false === $index ? 0 : (int) $index;
				$index  += ( 'harder' === $mode ) ? 1 : -1;
				$index   = max( 0, min( count( DPG_Security::$difficulties ) - 1, $index ) );

				$filters['difficulty'] = DPG_Security::$difficulties[ $index ];
				break;
		}

		return $filters;
	}

	/**
	 * Choose a project type honouring the filters.
	 *
	 * @param array $filters Filters.
	 * @return array|null
	 */
	private function choose_type( array $filters ) {
		$types = DPG_Project_Database::types( $filters['category'] );

		if ( ! empty( $filters['demo_only'] ) && ! $filters['type'] ) {
			$types = array_values(
				array_filter(
					$types,
					static function ( $type ) {
						return ! empty( $type['demo'] ) && 'none' !== $type['demo'];
					}
				)
			);
		}

		// Draw first, then honour an explicit type, so the draw happens either
		// way. See the note in generate().
		$chosen = $this->pick( $types );

		if ( $filters['type'] ) {
			foreach ( $types as $type ) {
				if ( $type['id'] === $filters['type'] ) {
					return $type;
				}
			}

			return null;
		}

		return $chosen;
	}

	/**
	 * Choose a record from a dataset, honouring an explicit identifier.
	 *
	 * @param array  $records Records.
	 * @param string $id      Requested identifier.
	 * @return array|null
	 */
	private function choose( array $records, $id ) {
		$chosen = $this->pick( $records );

		if ( $id ) {
			foreach ( $records as $record ) {
				if ( isset( $record['id'] ) && $record['id'] === $id ) {
					return $record;
				}
			}
		}

		return $chosen;
	}

	/**
	 * Choose a palette that suits the chosen style where possible.
	 *
	 * @param array $style Style record.
	 * @return array
	 */
	private function choose_palette( $style ) {
		$palettes = DPG_Project_Database::palettes();
		$wanted   = isset( $style['palette_tags'] ) && is_array( $style['palette_tags'] ) ? $style['palette_tags'] : array();

		if ( ! empty( $wanted ) ) {
			$matching = array_values(
				array_filter(
					$palettes,
					static function ( $palette ) use ( $wanted ) {
						$tags = isset( $palette['tags'] ) ? (array) $palette['tags'] : array();

						return (bool) array_intersect( $tags, $wanted );
					}
				)
			);

			// Usually respect the style, occasionally allow a deliberate clash.
			if ( ! empty( $matching ) && $this->range( 0, 100 ) < 85 ) {
				return (array) $this->pick( $matching );
			}
		}

		return (array) $this->pick( $palettes );
	}

	/**
	 * Build the project identifier.
	 *
	 * @return string
	 */
	private function project_id() {
		return 'DG-' . str_pad( (string) ( $this->seed % 100000 ), 5, '0', STR_PAD_LEFT );
	}

	/**
	 * Build the project title.
	 *
	 * @param array  $type    Project type.
	 * @param string $product Product or initiative name.
	 * @return string
	 */
	private function title( array $type, $product ) {
		$product = trim( (string) $product );

		if ( '' === $product ) {
			return $type['name'];
		}

		$label = $this->title_case( $product );

		$patterns = array(
			$label . ' — ' . $type['name'],
			$type['name'] . ': ' . $label,
			$label . ' ' . $type['name'],
		);

		// The third pattern reads badly when both halves are long.
		if ( str_word_count( $label ) + str_word_count( $type['name'] ) > 5 ) {
			array_pop( $patterns );
		}

		return (string) $this->pick( $patterns );
	}

	/**
	 * Title-case a phrase, leaving short joining words lowercase.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function title_case( $text ) {
		$small = array( 'a', 'an', 'and', 'the', 'of', 'for', 'to', 'in', 'on', 'with' );
		$words = preg_split( '/\s+/', trim( $text ) );
		$out   = array();

		foreach ( (array) $words as $index => $word ) {
			$lower = strtolower( $word );
			$out[] = ( $index > 0 && in_array( $lower, $small, true ) )
				? $lower
				: ucfirst( $lower );
		}

		return implode( ' ', $out );
	}

	/**
	 * Replace {client}, {product} and {industry} tokens.
	 *
	 * @param string $text   Text.
	 * @param array  $tokens Token map.
	 * @return string
	 */
	private function fill( $text, array $tokens ) {
		return trim( strtr( (string) $text, $tokens ) );
	}

	/**
	 * Make a fragment read as a sentence.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function sentence( $text ) {
		$text = trim( (string) $text );

		if ( '' === $text ) {
			return '';
		}

		$text = ucfirst( $text );

		if ( ! preg_match( '/[.!?]$/', $text ) ) {
			$text .= '.';
		}

		return $text;
	}

	/**
	 * Human-readable colour direction.
	 *
	 * @param array $palette Palette record.
	 * @return string
	 */
	private function color_direction( $palette ) {
		$name = isset( $palette['name'] ) ? $palette['name'] : __( 'Designer’s choice', 'design-project-generator' );

		$openers = array(
			/* translators: %s: palette name. */
			__( '%s. Use the accent on one element only.', 'design-project-generator' ),
			/* translators: %s: palette name. */
			__( '%s. Keep the background dominant and let the accent do the pointing.', 'design-project-generator' ),
			/* translators: %s: palette name. */
			__( '%s, applied roughly 60 / 30 / 10.', 'design-project-generator' ),
			/* translators: %s: palette name. */
			__( '%s. Substitutions are allowed if you can justify them.', 'design-project-generator' ),
		);

		return sprintf( (string) $this->pick( $openers ), $name );
	}

	/**
	 * Human-readable typography direction.
	 *
	 * @param array $pairing Typography pairing.
	 * @return string
	 */
	private function typography_direction( $pairing ) {
		if ( empty( $pairing['heading']['label'] ) ) {
			return __( 'Designer’s choice, but no more than two typefaces.', 'design-project-generator' );
		}

		return sprintf(
			/* translators: 1: heading typeface description, 2: body typeface description. */
			__( '%1$s for headings with %2$s for supporting copy.', 'design-project-generator' ),
			$pairing['heading']['label'],
			strtolower( $pairing['body']['label'] )
		);
	}

	/**
	 * Estimated working time in minutes.
	 *
	 * @param array  $type       Project type.
	 * @param string $difficulty Difficulty.
	 * @return int
	 */
	private function estimated_time( array $type, $difficulty ) {
		$base       = isset( $type['base_time'] ) ? (int) $type['base_time'] : 60;
		$multiplier = isset( self::$time_multiplier[ $difficulty ] ) ? self::$time_multiplier[ $difficulty ] : 1.0;
		$minutes    = (int) round( ( $base * $multiplier ) / 15 ) * 15;

		return max( 15, $minutes );
	}

	/**
	 * Demo template identifier for a project type.
	 *
	 * @param array $type Project type.
	 * @return string
	 */
	private function demo_type( array $type ) {
		$demo = isset( $type['demo'] ) ? DPG_Security::key( $type['demo'] ) : 'none';

		if ( '' === $demo || 'none' === $demo ) {
			return 'none';
		}

		$templates = DPG_Project_Database::demo_templates();

		return isset( $templates[ $demo ] ) ? $demo : 'none';
	}

	/**
	 * Build the deliverables list.
	 *
	 * @param array  $type       Project type.
	 * @param string $difficulty Difficulty.
	 * @return string[]
	 */
	private function deliverables( array $type, $difficulty ) {
		$base   = isset( $type['deliverables'] ) ? array_values( (array) $type['deliverables'] ) : array();
		$extras = isset( $type['extras'] ) ? array_values( (array) $type['extras'] ) : array();
		$bounds = isset( self::$deliverable_counts[ $difficulty ] ) ? self::$deliverable_counts[ $difficulty ] : array( 2, 3 );
		$want   = $this->range( $bounds[0], $bounds[1] );

		// Core deliverables come first and in their authored order: the first
		// item of a type is always the one that defines the piece.
		$out = array_slice( $base, 0, $want );

		if ( count( $out ) < $want && ! empty( $extras ) ) {
			$out = array_merge( $out, $this->sample( $extras, $want - count( $out ) ) );
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Build the restrictions list.
	 *
	 * @param array  $type       Project type.
	 * @param string $difficulty Difficulty.
	 * @return string[]
	 */
	private function restrictions( array $type, $difficulty ) {
		$pool     = DPG_Project_Database::restrictions();
		$category = $type['category_id'];
		$affinity = isset( self::$category_tags[ $category ] ) ? self::$category_tags[ $category ] : array();
		$bounds   = isset( self::$restriction_counts[ $difficulty ] ) ? self::$restriction_counts[ $difficulty ] : array( 2, 3 );
		$want     = $this->range( $bounds[0], $bounds[1] );

		// An illustration brief that forbids illustration is not a challenge,
		// it is a contradiction.
		if ( 'illustration' === $category ) {
			$pool = array_values(
				array_filter(
					$pool,
					static function ( $item ) {
						return ! in_array( 'image', (array) ( isset( $item['tags'] ) ? $item['tags'] : array() ), true );
					}
				)
			);
		}

		$relevant = array();
		$rest     = array();

		foreach ( $this->shuffle( $pool ) as $item ) {
			$tags = isset( $item['tags'] ) ? (array) $item['tags'] : array();
			if ( $affinity && array_intersect( $tags, $affinity ) ) {
				$relevant[] = $item;
			} else {
				$rest[] = $item;
			}
		}

		$ordered = array_merge( $relevant, $rest );
		$out     = array();
		$used    = array();

		foreach ( $ordered as $item ) {
			if ( count( $out ) >= $want ) {
				break;
			}

			$tag = isset( $item['tags'][0] ) ? $item['tags'][0] : 'general';

			// At most two restrictions from the same family, so a brief does
			// not end up being four different rules about colour.
			if ( isset( $used[ $tag ] ) && $used[ $tag ] >= 2 ) {
				continue;
			}

			$used[ $tag ] = isset( $used[ $tag ] ) ? $used[ $tag ] + 1 : 1;
			$out[]        = $item['text'];
		}

		return $out;
	}

	/**
	 * Build the required-content list.
	 *
	 * @param array  $type       Project type.
	 * @param string $difficulty Difficulty.
	 * @return string[]
	 */
	private function content( array $type, $difficulty ) {
		$items = isset( $type['content'] ) ? array_values( (array) $type['content'] ) : array();

		if ( empty( $items ) ) {
			return array();
		}

		switch ( $difficulty ) {
			case 'beginner':
				return array_slice( $items, 0, 2 );

			case 'intermediate':
				return array_slice( $items, 0, max( 3, (int) ceil( count( $items ) * 0.75 ) ) );

			default:
				return $items;
		}
	}

	/**
	 * Build the success criteria checklist.
	 *
	 * @param string $difficulty Difficulty.
	 * @return string[]
	 */
	private function success_criteria( $difficulty ) {
		$counts = array(
			'beginner'     => 3,
			'intermediate' => 4,
			'advanced'     => 5,
			'expert'       => 6,
		);
		$want   = isset( $counts[ $difficulty ] ) ? $counts[ $difficulty ] : 4;
		$pool   = DPG_Project_Database::success_criteria();
		$always = __( 'Every restriction in the brief is respected.', 'design-project-generator' );

		$picked = $this->sample(
			array_values(
				array_filter(
					$pool,
					static function ( $item ) {
						return false === strpos( strtolower( $item ), 'restrictions are all respected' );
					}
				)
			),
			$want - 1
		);

		return array_merge( array( $always ), $picked );
	}

	/**
	 * Choose a challenge, weighted by difficulty.
	 *
	 * @param string $difficulty Difficulty.
	 * @return array
	 */
	private function challenge( $difficulty ) {
		$pool = DPG_Project_Database::challenges();

		if ( empty( $pool ) ) {
			return DPG_Security::challenge( array() );
		}

		// Beginners get a timer or a single clear constraint; the harder the
		// brief, the more likely a conceptual challenge becomes.
		$preferred = array(
			'beginner'     => array( 'timer', 'color', 'minimalism' ),
			'intermediate' => array( 'timer', 'color', 'typography', 'layout' ),
			'advanced'     => array( 'layout', 'typography', 'concept', 'constraint' ),
			'expert'       => array( 'concept', 'constraint', 'layout' ),
		);

		$wanted   = isset( $preferred[ $difficulty ] ) ? $preferred[ $difficulty ] : array();
		$filtered = array_values(
			array_filter(
				$pool,
				static function ( $item ) use ( $wanted ) {
					return empty( $wanted ) || in_array( isset( $item['type'] ) ? $item['type'] : '', $wanted, true );
				}
			)
		);

		$choice = $this->pick( ! empty( $filtered ) ? $filtered : $pool );

		return DPG_Security::challenge( (array) $choice );
	}

	/**
	 * Generate several distinct projects, as used by teacher assignments.
	 *
	 * @param array $filters Filters.
	 * @param int   $count   How many projects.
	 * @return array
	 */
	public function generate_set( array $filters, $count ) {
		$count = DPG_Security::int( $count, 1, 100, 5 );
		$out   = array();
		$seen  = array();

		for ( $i = 0; $i < $count * 4 && count( $out ) < $count; $i++ ) {
			$generator = new self( $this->next() );
			$project   = $generator->generate( $filters );

			if ( is_wp_error( $project ) ) {
				return $out;
			}

			// Try to give every student a different industry and project type.
			$signature = $project['project_type_id'] . '|' . $project['industry_id'];

			if ( isset( $seen[ $signature ] ) ) {
				continue;
			}

			$seen[ $signature ] = true;
			$out[]              = $project;
		}

		// If the filters are too narrow to give everyone something unique,
		// fill the remainder rather than returning a short list.
		while ( count( $out ) < $count ) {
			$generator = new self( $this->next() );
			$project   = $generator->generate( $filters );

			if ( is_wp_error( $project ) ) {
				break;
			}

			$out[] = $project;
		}

		return $out;
	}

	/**
	 * The deterministic daily challenge.
	 *
	 * Everyone visiting on the same day sees the same brief.
	 *
	 * @param string $date Optional Y-m-d date. Defaults to today in site time.
	 * @return array|WP_Error
	 */
	public static function daily( $date = '' ) {
		$date = $date ? $date : current_time( 'Y-m-d' );
		$seed = abs( (int) sprintf( '%u', crc32( 'dpg-daily-' . $date ) ) ) % 2147483646;
		$seed = $seed > 0 ? $seed : 1;

		$generator  = new self( $seed );
		$difficulty = DPG_Security::$difficulties[ (int) gmdate( 'z', strtotime( $date ) ) % 3 ];

		$project = $generator->generate( array( 'difficulty' => $difficulty ) );

		if ( ! is_wp_error( $project ) ) {
			$project['is_daily']   = true;
			$project['daily_date'] = $date;
		}

		return $project;
	}
}
