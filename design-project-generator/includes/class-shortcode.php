<?php
/**
 * Shortcode and front-end rendering.
 *
 * The project card is rendered here, in PHP, and nowhere else. The browser asks
 * the REST endpoint for the next project and receives both the data (for
 * exports, the demo and saving) and the finished markup, so there is only one
 * template to keep correct and only one place where output is escaped.
 *
 * @package DesignProjectGenerator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode handler and renderer.
 */
class DPG_Shortcode {

	/**
	 * Shortcode tag.
	 */
	const TAG = 'design_project_generator';

	/**
	 * Instance counter, used for unique element identifiers.
	 *
	 * @var int
	 */
	private static $instance = 0;

	/**
	 * Whether the shared assets have been queued for this request.
	 *
	 * @var bool
	 */
	private static $assets_done = false;

	/**
	 * Whether the icon sprite has been printed for this request.
	 *
	 * @var bool
	 */
	private static $sprite_done = false;

	/**
	 * Hook into WordPress.
	 */
	public function __construct() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Default shortcode attributes.
	 *
	 * @return array<string,string>
	 */
	public static function defaults() {
		return array(
			'category'       => '',
			'type'           => '',
			'industry'       => '',
			'style'          => '',
			'difficulty'     => '',
			'demo_only'      => 'no',
			'daily'          => 'no',
			'teacher'        => 'no',
			'prerender'      => 'yes',
			'show_filters'   => 'yes',
			'show_timer'     => 'yes',
			'show_demo'      => 'yes',
			'show_export'    => 'yes',
			'show_save'      => 'yes',
			'show_palette'   => 'yes',
			'show_hints'     => 'yes',
			'show_case'      => 'yes',
			'heading'        => '',
			'tagline'        => '',
		);
	}

	/**
	 * Interpret a yes/no attribute.
	 *
	 * @param mixed $value   Attribute value.
	 * @param bool  $default_value Default.
	 * @return bool
	 */
	public static function bool( $value, $default_value = true ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( null === $value || '' === $value ) {
			return $default_value;
		}

		return in_array( strtolower( (string) $value ), array( 'yes', 'true', '1', 'on' ), true );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array|string $atts Raw attributes.
	 * @return string
	 */
	public static function render( $atts = array() ) {
		$atts = shortcode_atts( self::defaults(), (array) $atts, self::TAG );

		return self::render_instance( $atts );
	}

	/**
	 * Render one generator instance.
	 *
	 * @param array $atts Attributes, already merged with the defaults.
	 * @return string
	 */
	public static function render_instance( array $atts ) {
		$atts     = array_merge( self::defaults(), $atts );
		$defaults = DPG_Admin::settings();

		// Site defaults fill in only where the shortcode or block is silent.
		if ( '' === $atts['category'] ) {
			$atts['category'] = $defaults['default_category'];
		}

		if ( '' === $atts['difficulty'] ) {
			$atts['difficulty'] = $defaults['default_difficulty'];
		}

		$settings = array(
			'showFilters' => self::bool( $atts['show_filters'] ),
			'showTimer'   => self::bool( $atts['show_timer'] ),
			'showDemo'    => self::bool( $atts['show_demo'] ),
			'showExport'  => self::bool( $atts['show_export'] ),
			'showSave'    => self::bool( $atts['show_save'] ),
			'showPalette' => self::bool( $atts['show_palette'] ),
			'showHints'   => self::bool( $atts['show_hints'] ),
			'showCase'    => self::bool( $atts['show_case'] ),
			'daily'       => self::bool( $atts['daily'], false ) && ! empty( $defaults['daily_challenge'] ),
			'teacher'     => self::bool( $atts['teacher'], false ) && DPG_Security::can_teach(),
		);

		// Guests only get a save button when browser saving is switched on.
		if ( ! is_user_logged_in() && empty( $defaults['guest_saving'] ) ) {
			$settings['showSave'] = false;
			$settings['showCase'] = false;
		}

		$filters = DPG_Security::filters(
			array(
				'category'   => $atts['category'],
				'type'       => $atts['type'],
				'industry'   => $atts['industry'],
				'style'      => $atts['style'],
				'difficulty' => $atts['difficulty'],
				'demo_only'  => self::bool( $atts['demo_only'], false ),
			)
		);

		self::enqueue_assets( $settings );

		++self::$instance;
		$uid = 'dpg-' . self::$instance;

		$project = null;

		if ( self::bool( $atts['prerender'] ) ) {
			$project = $settings['daily']
				? DPG_Project_Generator::daily()
				: ( new DPG_Project_Generator() )->generate( $filters );

			if ( is_wp_error( $project ) ) {
				$project = null;
			}
		}

		$heading = '' !== $atts['heading'] ? $atts['heading'] : __( 'Design Project Generator', 'design-project-generator' );
		$tagline = '' !== $atts['tagline'] ? $atts['tagline'] : __( 'Generate. Design. Practice. Build your portfolio.', 'design-project-generator' );

		$config = array(
			'uid'      => $uid,
			'settings' => $settings,
			'filters'  => $filters,
			'restUrl'  => esc_url_raw( rest_url( DPG_REST_API::NAMESPACE_V1 ) ),
			'loggedIn' => is_user_logged_in(),
			// The brief on screen was rendered above; its data travels with it
			// so the export, demo and save tools work without a second request.
			'project'  => $project,
		);

		ob_start();
		self::sprite();
		?>
		<div class="dpg" id="<?php echo esc_attr( $uid ); ?>" data-dpg>
			<script type="application/json" data-dpg-config>
				<?php echo wp_json_encode( $config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON inside a non-executable script block. ?>
			</script>

			<header class="dpg__masthead">
				<div class="dpg__masthead-text">
					<h2 class="dpg__heading">
						<?php self::icon( 'sparkles', 'dpg__heading-icon' ); ?>
						<?php echo esc_html( $heading ); ?>
					</h2>
					<p class="dpg__tagline"><?php echo esc_html( $tagline ); ?></p>
				</div>
			</header>

			<?php if ( $settings['showFilters'] ) : ?>
				<?php self::render_filters( $uid, $filters ); ?>
			<?php else : ?>
				<div class="dpg__generate-only">
					<button type="button" class="dpg-btn dpg-btn--primary dpg-btn--lg" data-dpg-generate>
						<?php self::icon( 'dice' ); ?>
						<?php esc_html_e( 'Generate project', 'design-project-generator' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<p class="dpg__live" data-dpg-live role="status" aria-live="polite"></p>

			<noscript>
				<p class="dpg__notice">
					<?php esc_html_e( 'The brief below is ready to work from. Turn JavaScript on for the timer, the colour tools, the interactive demos and the export buttons.', 'design-project-generator' ); ?>
				</p>
			</noscript>

			<div class="dpg__stage">
				<div class="dpg__card-wrap dpg-export-area" id="<?php echo esc_attr( $uid ); ?>-export-area" data-dpg-export-area>
					<div class="dpg__card" data-dpg-card aria-busy="false">
						<?php
						if ( $project ) {
							self::render_project( $project, $settings );
						} else {
							self::render_placeholder();
						}
						?>
					</div>
				</div>

				<?php self::render_sidebar( $uid, $settings, $project ); ?>
			</div>

			<?php self::render_actions( $settings ); ?>
			<?php self::render_panels( $uid, $settings ); ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * The filter bar.
	 *
	 * @param string $uid     Instance identifier.
	 * @param array  $filters Current filters.
	 * @return void
	 */
	private static function render_filters( $uid, array $filters ) {
		$categories = DPG_Project_Database::categories();
		$industries = DPG_Project_Database::industries();
		$styles     = DPG_Project_Database::styles();
		?>
		<form class="dpg__filters" data-dpg-filters>
			<div class="dpg__filter">
				<label for="<?php echo esc_attr( $uid ); ?>-category"><?php esc_html_e( 'Category', 'design-project-generator' ); ?></label>
				<select id="<?php echo esc_attr( $uid ); ?>-category" name="category" data-dpg-filter="category">
					<option value=""><?php esc_html_e( 'Any category', 'design-project-generator' ); ?></option>
					<?php foreach ( $categories as $category ) : ?>
						<option value="<?php echo esc_attr( $category['id'] ); ?>" <?php selected( $filters['category'], $category['id'] ); ?>>
							<?php echo esc_html( $category['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="dpg__filter">
				<label for="<?php echo esc_attr( $uid ); ?>-difficulty"><?php esc_html_e( 'Difficulty', 'design-project-generator' ); ?></label>
				<select id="<?php echo esc_attr( $uid ); ?>-difficulty" name="difficulty" data-dpg-filter="difficulty">
					<option value=""><?php esc_html_e( 'Any difficulty', 'design-project-generator' ); ?></option>
					<?php foreach ( DPG_Security::$difficulties as $difficulty ) : ?>
						<option value="<?php echo esc_attr( $difficulty ); ?>" <?php selected( $filters['difficulty'], $difficulty ); ?>>
							<?php echo esc_html( DPG_REST_API::difficulty_label( $difficulty ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<details class="dpg__filter-more">
				<summary><?php esc_html_e( 'More filters', 'design-project-generator' ); ?></summary>
				<div class="dpg__filter-more-grid">
					<div class="dpg__filter">
						<label for="<?php echo esc_attr( $uid ); ?>-industry"><?php esc_html_e( 'Industry', 'design-project-generator' ); ?></label>
						<select id="<?php echo esc_attr( $uid ); ?>-industry" name="industry" data-dpg-filter="industry">
							<option value=""><?php esc_html_e( 'Any industry', 'design-project-generator' ); ?></option>
							<?php foreach ( $industries as $industry ) : ?>
								<option value="<?php echo esc_attr( $industry['id'] ); ?>" <?php selected( $filters['industry'], $industry['id'] ); ?>>
									<?php echo esc_html( $industry['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="dpg__filter">
						<label for="<?php echo esc_attr( $uid ); ?>-style"><?php esc_html_e( 'Style', 'design-project-generator' ); ?></label>
						<select id="<?php echo esc_attr( $uid ); ?>-style" name="style" data-dpg-filter="style">
							<option value=""><?php esc_html_e( 'Any style', 'design-project-generator' ); ?></option>
							<?php foreach ( $styles as $style ) : ?>
								<option value="<?php echo esc_attr( $style['id'] ); ?>" <?php selected( $filters['style'], $style['id'] ); ?>>
									<?php echo esc_html( $style['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="dpg__filter dpg__filter--check">
						<input type="checkbox" id="<?php echo esc_attr( $uid ); ?>-demo-only" data-dpg-filter="demo_only" <?php checked( ! empty( $filters['demo_only'] ) ); ?> />
						<label for="<?php echo esc_attr( $uid ); ?>-demo-only"><?php esc_html_e( 'Only projects with an interactive demo', 'design-project-generator' ); ?></label>
					</div>
				</div>
			</details>

			<button type="submit" class="dpg-btn dpg-btn--primary dpg-btn--lg" data-dpg-generate>
				<?php self::icon( 'dice' ); ?>
				<?php esc_html_e( 'Generate project', 'design-project-generator' ); ?>
			</button>
		</form>
		<?php
	}

	/**
	 * Placeholder shown when nothing has been generated yet.
	 *
	 * @return void
	 */
	private static function render_placeholder() {
		?>
		<div class="dpg__placeholder">
			<?php self::icon( 'dice', 'dpg__placeholder-icon' ); ?>
			<p><?php esc_html_e( 'Choose your filters and generate a brief to get started.', 'design-project-generator' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render a full project brief.
	 *
	 * @param array $project  Validated project.
	 * @param array $settings Instance settings.
	 * @return void
	 */
	public static function render_project( array $project, array $settings = array() ) {
		$get = static function ( $key, $fallback = '' ) use ( $project ) {
			return isset( $project[ $key ] ) && '' !== $project[ $key ] ? $project[ $key ] : $fallback;
		};
		?>
		<article class="dpg-brief dpg-brief--<?php echo esc_attr( $get( 'difficulty', 'beginner' ) ); ?>">
			<header class="dpg-brief__head">
				<div class="dpg-brief__chips">
					<span class="dpg-chip dpg-chip--category"><?php echo esc_html( $get( 'category' ) ); ?></span>
					<span class="dpg-chip dpg-chip--type"><?php echo esc_html( $get( 'project_type' ) ); ?></span>
					<span class="dpg-chip dpg-chip--difficulty" data-level="<?php echo esc_attr( $get( 'difficulty' ) ); ?>">
						<?php echo esc_html( DPG_REST_API::difficulty_label( $get( 'difficulty' ) ) ); ?>
					</span>
					<?php if ( 'none' !== $get( 'demo_type', 'none' ) ) : ?>
						<span class="dpg-chip dpg-chip--demo">
							<?php self::icon( 'monitor' ); ?>
							<?php esc_html_e( 'Demo available', 'design-project-generator' ); ?>
						</span>
					<?php endif; ?>
					<?php if ( ! empty( $project['is_daily'] ) ) : ?>
						<span class="dpg-chip dpg-chip--daily"><?php esc_html_e( 'Today’s challenge', 'design-project-generator' ); ?></span>
					<?php endif; ?>
				</div>

				<div class="dpg-brief__meta-line">
					<span class="dpg-brief__id"><?php echo esc_html( $get( 'id' ) ); ?></span>
					<span class="dpg-brief__time">
						<?php self::icon( 'clock' ); ?>
						<?php echo esc_html( DPG_Export::format_minutes( (int) $get( 'estimated_time', 60 ) ) ); ?>
					</span>
				</div>

				<p class="dpg-brief__client"><?php echo esc_html( $get( 'client' ) ); ?></p>
				<h3 class="dpg-brief__title"><?php echo esc_html( $get( 'title' ) ); ?></h3>
			</header>

			<?php if ( $get( 'background' ) ) : ?>
				<section class="dpg-brief__section">
					<h4 class="dpg-brief__label"><?php esc_html_e( 'Background', 'design-project-generator' ); ?></h4>
					<p class="dpg-brief__prose"><?php echo esc_html( $get( 'background' ) ); ?></p>
				</section>
			<?php endif; ?>

			<?php if ( $get( 'objective' ) ) : ?>
				<section class="dpg-brief__section">
					<h4 class="dpg-brief__label"><?php esc_html_e( 'Objective', 'design-project-generator' ); ?></h4>
					<p class="dpg-brief__prose dpg-brief__prose--lead"><?php echo esc_html( $get( 'objective' ) ); ?></p>
				</section>
			<?php endif; ?>

			<section class="dpg-brief__section">
				<h4 class="dpg-brief__label"><?php esc_html_e( 'Direction', 'design-project-generator' ); ?></h4>
				<dl class="dpg-brief__facts">
					<?php
					self::fact( 'target', __( 'Audience', 'design-project-generator' ), $get( 'audience' ) );
					self::fact( 'sparkles', __( 'Style', 'design-project-generator' ), trim( $get( 'style' ) . ( $get( 'style_direction' ) ? '. ' . $get( 'style_direction' ) : '' ) ) );
					self::fact( 'palette', __( 'Colour', 'design-project-generator' ), $get( 'color_direction' ) );
					self::fact( 'type', __( 'Typography', 'design-project-generator' ), trim( $get( 'typography' ) . ' ' . $get( 'typography_note' ) ) );
					self::fact( 'heart', __( 'Personality', 'design-project-generator' ), $get( 'personality' ) );
					self::fact( 'clock', __( 'Deadline', 'design-project-generator' ), $get( 'deadline' ) );
					self::fact( 'folder', __( 'Budget', 'design-project-generator' ), $get( 'budget' ) );
					?>
				</dl>
			</section>

			<?php
			self::list_section( __( 'Deliverables', 'design-project-generator' ), $get( 'deliverables', array() ), 'deliverables', true );
			self::list_section( __( 'Required content', 'design-project-generator' ), $get( 'content', array() ), 'content', false );
			self::list_section( __( 'Restrictions', 'design-project-generator' ), $get( 'restrictions', array() ), 'restrictions', false );
			self::list_section( __( 'Competitor context', 'design-project-generator' ), $get( 'competitors', array() ), 'competitors', false );
			?>

			<?php if ( ! empty( $project['challenge']['prompt'] ) ) : ?>
				<section class="dpg-brief__section dpg-brief__challenge">
					<h4 class="dpg-brief__label">
						<?php self::icon( 'target' ); ?>
						<?php echo esc_html( $project['challenge']['name'] ); ?>
					</h4>
					<p class="dpg-brief__prose"><?php echo esc_html( $project['challenge']['prompt'] ); ?></p>
					<?php if ( ! empty( $project['challenge']['duration'] ) ) : ?>
						<p class="dpg-brief__challenge-time">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: formatted duration. */
									__( 'Suggested limit: %s', 'design-project-generator' ),
									DPG_Export::format_minutes( (int) round( $project['challenge']['duration'] / 60 ) )
								)
							);
							?>
						</p>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php self::list_section( __( 'Success criteria', 'design-project-generator' ), $get( 'success', array() ), 'success', true ); ?>
		</article>
		<?php
	}

	/**
	 * Render a project brief and return it as a string.
	 *
	 * Used by the REST endpoint so the browser never has to build this markup
	 * itself: there is one template, and escaping happens in one place.
	 *
	 * @param array $project  Validated project.
	 * @param array $settings Instance settings.
	 * @return string
	 */
	public static function render_project_html( array $project, array $settings = array() ) {
		ob_start();
		self::render_project( $project, $settings );

		return (string) ob_get_clean();
	}

	/**
	 * One definition-list fact row.
	 *
	 * @param string $icon  Icon name.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	private static function fact( $icon, $label, $value ) {
		if ( '' === trim( (string) $value ) ) {
			return;
		}
		?>
		<div class="dpg-brief__fact">
			<dt><?php self::icon( $icon ); ?><span><?php echo esc_html( $label ); ?></span></dt>
			<dd><?php echo esc_html( $value ); ?></dd>
		</div>
		<?php
	}

	/**
	 * A titled list block.
	 *
	 * @param string   $title     Section title.
	 * @param string[] $items     Items.
	 * @param string   $modifier  CSS modifier.
	 * @param bool     $checkable Whether to render tick boxes.
	 * @return void
	 */
	private static function list_section( $title, $items, $modifier, $checkable = false ) {
		$items = array_values( array_filter( (array) $items ) );

		if ( empty( $items ) ) {
			return;
		}
		?>
		<section class="dpg-brief__section dpg-brief__section--<?php echo esc_attr( $modifier ); ?>">
			<h4 class="dpg-brief__label"><?php echo esc_html( $title ); ?></h4>
			<ul class="dpg-brief__list <?php echo $checkable ? 'dpg-brief__list--check' : ''; ?>">
				<?php foreach ( $items as $item ) : ?>
					<li>
						<?php if ( $checkable ) : ?>
							<?php self::icon( 'check', 'dpg-brief__tick' ); ?>
						<?php endif; ?>
						<span><?php echo esc_html( $item ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
	}

	/**
	 * The tools column: palette, timer and hints.
	 *
	 * @param string     $uid      Instance identifier.
	 * @param array      $settings Settings.
	 * @param array|null $project  Current project.
	 * @return void
	 */
	private static function render_sidebar( $uid, array $settings, $project ) {
		if ( ! $settings['showPalette'] && ! $settings['showTimer'] && ! $settings['showHints'] ) {
			return;
		}

		$palette = isset( $project['palette']['colors'] ) ? $project['palette']['colors'] : array();
		?>
		<aside class="dpg__tools">
			<?php if ( $settings['showPalette'] ) : ?>
				<section class="dpg-tool dpg-tool--palette" data-dpg-palette-tool>
					<h4 class="dpg-tool__title">
						<?php self::icon( 'palette' ); ?>
						<?php esc_html_e( 'Colour palette', 'design-project-generator' ); ?>
					</h4>
					<ul class="dpg-palette" data-dpg-palette>
						<?php
						$roles = array(
							'primary'    => __( 'Primary', 'design-project-generator' ),
							'secondary'  => __( 'Secondary', 'design-project-generator' ),
							'accent'     => __( 'Accent', 'design-project-generator' ),
							'background' => __( 'Background', 'design-project-generator' ),
							'text'       => __( 'Text', 'design-project-generator' ),
						);

						foreach ( $roles as $role => $label ) :
							$hex = isset( $palette[ $role ] ) ? $palette[ $role ] : '#cccccc';
							?>
							<li class="dpg-swatch" data-dpg-swatch="<?php echo esc_attr( $role ); ?>" data-locked="false">
								<span class="dpg-swatch__chip" style="background:<?php echo esc_attr( $hex ); ?>"></span>
								<span class="dpg-swatch__meta">
									<span class="dpg-swatch__role"><?php echo esc_html( $label ); ?></span>
									<button type="button" class="dpg-swatch__hex" data-dpg-copy-hex title="<?php esc_attr_e( 'Copy this hex value', 'design-project-generator' ); ?>">
										<?php echo esc_html( strtoupper( $hex ) ); ?>
									</button>
								</span>
								<button type="button" class="dpg-swatch__lock" data-dpg-lock aria-pressed="false">
									<span class="screen-reader-text"><?php esc_html_e( 'Lock this colour', 'design-project-generator' ); ?></span>
									<?php self::icon( 'unlock', 'dpg-swatch__lock-open' ); ?>
									<?php self::icon( 'lock', 'dpg-swatch__lock-closed' ); ?>
								</button>
							</li>
						<?php endforeach; ?>
					</ul>
					<div class="dpg-tool__actions">
						<button type="button" class="dpg-btn dpg-btn--sm" data-dpg-palette-shuffle>
							<?php self::icon( 'refresh' ); ?>
							<?php esc_html_e( 'Randomise', 'design-project-generator' ); ?>
						</button>
						<button type="button" class="dpg-btn dpg-btn--sm dpg-btn--quiet" data-dpg-palette-reset>
							<?php self::icon( 'rotate' ); ?>
							<?php esc_html_e( 'Reset', 'design-project-generator' ); ?>
						</button>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $settings['showTimer'] ) : ?>
				<section class="dpg-tool dpg-tool--timer" data-dpg-timer-tool>
					<h4 class="dpg-tool__title">
						<?php self::icon( 'clock' ); ?>
						<?php esc_html_e( 'Challenge timer', 'design-project-generator' ); ?>
					</h4>
					<p class="dpg-timer__display" data-dpg-timer-display role="timer" aria-live="off">00:00</p>
					<div class="dpg-timer__presets" data-dpg-timer-presets>
						<?php foreach ( DPG_Project_Database::timer_presets() as $preset ) : ?>
							<button type="button" class="dpg-btn dpg-btn--sm dpg-btn--quiet" data-dpg-timer-preset="<?php echo esc_attr( (int) $preset['seconds'] ); ?>">
								<?php echo esc_html( $preset['label'] ); ?>
							</button>
						<?php endforeach; ?>
						<label class="dpg-timer__custom">
							<span class="screen-reader-text"><?php esc_html_e( 'Custom minutes', 'design-project-generator' ); ?></span>
							<input type="number" min="1" max="600" step="1" placeholder="<?php esc_attr_e( 'Custom', 'design-project-generator' ); ?>" data-dpg-timer-custom />
						</label>
					</div>
					<div class="dpg-tool__actions">
						<button type="button" class="dpg-btn dpg-btn--sm dpg-btn--primary" data-dpg-timer-start>
							<?php self::icon( 'play' ); ?>
							<?php esc_html_e( 'Start', 'design-project-generator' ); ?>
						</button>
						<button type="button" class="dpg-btn dpg-btn--sm" data-dpg-timer-pause disabled>
							<?php self::icon( 'pause' ); ?>
							<?php esc_html_e( 'Pause', 'design-project-generator' ); ?>
						</button>
						<button type="button" class="dpg-btn dpg-btn--sm dpg-btn--quiet" data-dpg-timer-stop disabled>
							<?php self::icon( 'stop' ); ?>
							<?php esc_html_e( 'Stop', 'design-project-generator' ); ?>
						</button>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $settings['showHints'] ) : ?>
				<section class="dpg-tool dpg-tool--hints">
					<h4 class="dpg-tool__title">
						<?php self::icon( 'bulb' ); ?>
						<?php esc_html_e( 'Stuck?', 'design-project-generator' ); ?>
					</h4>
					<p class="dpg-tool__hint" data-dpg-hint><?php esc_html_e( 'Press the button for a direction to try.', 'design-project-generator' ); ?></p>
					<div class="dpg-tool__actions">
						<button type="button" class="dpg-btn dpg-btn--sm" data-dpg-hint-button>
							<?php self::icon( 'bulb' ); ?>
							<?php esc_html_e( 'I’m stuck', 'design-project-generator' ); ?>
						</button>
					</div>
				</section>
			<?php endif; ?>
		</aside>
		<?php
	}

	/**
	 * The action button row.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private static function render_actions( array $settings ) {
		?>
		<div class="dpg__actions">
			<div class="dpg__actions-main">
				<div class="dpg-split">
					<button type="button" class="dpg-btn dpg-btn--primary" data-dpg-generate>
						<?php self::icon( 'refresh' ); ?>
						<?php esc_html_e( 'Generate another', 'design-project-generator' ); ?>
					</button>
					<button type="button" class="dpg-btn dpg-btn--primary dpg-split__toggle" data-dpg-menu-toggle="regenerate" aria-expanded="false" aria-haspopup="true">
						<span class="screen-reader-text"><?php esc_html_e( 'Regeneration options', 'design-project-generator' ); ?></span>
						<?php self::icon( 'chevron' ); ?>
					</button>
					<ul class="dpg-menu" data-dpg-menu="regenerate" hidden>
						<?php
						$modes = array(
							'random'          => __( 'Completely random', 'design-project-generator' ),
							'same-industry'   => __( 'Same industry', 'design-project-generator' ),
							'same-category'   => __( 'Same category', 'design-project-generator' ),
							'same-type'       => __( 'Same project type', 'design-project-generator' ),
							'same-difficulty' => __( 'Same difficulty', 'design-project-generator' ),
							'harder'          => __( 'Harder challenge', 'design-project-generator' ),
							'easier'          => __( 'Easier challenge', 'design-project-generator' ),
						);

						foreach ( $modes as $mode => $label ) :
							?>
							<li><button type="button" data-dpg-mode="<?php echo esc_attr( $mode ); ?>"><?php echo esc_html( $label ); ?></button></li>
						<?php endforeach; ?>
					</ul>
				</div>

				<?php if ( $settings['showSave'] ) : ?>
					<button type="button" class="dpg-btn" data-dpg-save>
						<?php self::icon( 'heart' ); ?>
						<?php esc_html_e( 'Save project', 'design-project-generator' ); ?>
					</button>
				<?php endif; ?>

				<?php if ( $settings['showExport'] ) : ?>
					<button type="button" class="dpg-btn" data-dpg-export="pdf">
						<?php self::icon( 'file-pdf' ); ?>
						<?php esc_html_e( 'PDF', 'design-project-generator' ); ?>
					</button>
					<button type="button" class="dpg-btn" data-dpg-export="png">
						<?php self::icon( 'image' ); ?>
						<?php esc_html_e( 'PNG', 'design-project-generator' ); ?>
					</button>
					<button type="button" class="dpg-btn" data-dpg-export="txt">
						<?php self::icon( 'file-text' ); ?>
						<?php esc_html_e( 'TXT', 'design-project-generator' ); ?>
					</button>
				<?php endif; ?>

				<?php if ( $settings['showDemo'] ) : ?>
					<button type="button" class="dpg-btn" data-dpg-open-demo hidden>
						<?php self::icon( 'monitor' ); ?>
						<?php esc_html_e( 'Open demo', 'design-project-generator' ); ?>
					</button>
				<?php endif; ?>

				<div class="dpg-split">
					<button type="button" class="dpg-btn dpg-btn--quiet" data-dpg-menu-toggle="more" aria-expanded="false" aria-haspopup="true">
						<?php self::icon( 'more' ); ?>
						<?php esc_html_e( 'More', 'design-project-generator' ); ?>
					</button>
					<ul class="dpg-menu dpg-menu--right" data-dpg-menu="more" hidden>
						<li><button type="button" data-dpg-action="copy"><?php esc_html_e( 'Copy brief', 'design-project-generator' ); ?></button></li>
						<li><button type="button" data-dpg-action="print"><?php esc_html_e( 'Print', 'design-project-generator' ); ?></button></li>
						<li><button type="button" data-dpg-action="share"><?php esc_html_e( 'Share project link', 'design-project-generator' ); ?></button></li>
						<li><button type="button" data-dpg-action="solution"><?php esc_html_e( 'Show solution hint', 'design-project-generator' ); ?></button></li>
						<?php if ( $settings['showCase'] ) : ?>
							<li><button type="button" data-dpg-action="case"><?php esc_html_e( 'Create portfolio case study', 'design-project-generator' ); ?></button></li>
						<?php endif; ?>
						<?php if ( $settings['showSave'] ) : ?>
							<li><button type="button" data-dpg-action="my-projects"><?php esc_html_e( 'My projects', 'design-project-generator' ); ?></button></li>
						<?php endif; ?>
						<li><button type="button" data-dpg-action="reset"><?php esc_html_e( 'Reset filters', 'design-project-generator' ); ?></button></li>
					</ul>
				</div>
			</div>

			<?php if ( $settings['showSave'] ) : ?>
				<div class="dpg__progress" data-dpg-progress hidden>
					<h4 class="dpg-tool__title"><?php esc_html_e( 'Project check', 'design-project-generator' ); ?></h4>
					<p class="dpg__progress-note"><?php esc_html_e( 'This tracks how much of the brief you have worked through. It does not judge the design itself.', 'design-project-generator' ); ?></p>
					<ul class="dpg-check">
						<?php
						$checks = array(
							'content'      => __( 'Required content added', 'design-project-generator' ),
							'cta'          => __( 'Call to action included', 'design-project-generator' ),
							'palette'      => __( 'Colour palette chosen', 'design-project-generator' ),
							'deliverables' => __( 'Deliverables completed', 'design-project-generator' ),
							'exported'     => __( 'Project exported', 'design-project-generator' ),
						);

						foreach ( $checks as $key => $label ) :
							?>
							<li>
								<label>
									<input type="checkbox" data-dpg-check="<?php echo esc_attr( $key ); ?>" />
									<span><?php echo esc_html( $label ); ?></span>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="dpg-check__score">
						<span data-dpg-score>0</span><span class="dpg-check__score-total"> / 100</span>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Modal panels used by the demo editor, case study and saved projects.
	 *
	 * @param string $uid      Instance identifier.
	 * @param array  $settings Settings.
	 * @return void
	 */
	private static function render_panels( $uid, array $settings ) {
		?>
		<?php if ( $settings['showDemo'] ) : ?>
		<div class="dpg-modal" data-dpg-modal="demo" hidden>
			<div class="dpg-modal__backdrop" data-dpg-modal-close></div>
			<div class="dpg-modal__box dpg-modal__box--wide" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $uid ); ?>-demo-title">
				<header class="dpg-modal__head">
					<h3 id="<?php echo esc_attr( $uid ); ?>-demo-title"><?php esc_html_e( 'Mini demo', 'design-project-generator' ); ?></h3>
					<button type="button" class="dpg-modal__close" data-dpg-modal-close>
						<span class="screen-reader-text"><?php esc_html_e( 'Close', 'design-project-generator' ); ?></span>
						<?php self::icon( 'x' ); ?>
					</button>
				</header>
				<div class="dpg-demo" data-dpg-demo>
					<div class="dpg-demo__controls" data-dpg-demo-controls></div>
					<div class="dpg-demo__preview">
						<iframe title="<?php esc_attr_e( 'Interactive demo preview', 'design-project-generator' ); ?>" data-dpg-demo-frame sandbox="allow-scripts" referrerpolicy="no-referrer" loading="lazy"></iframe>
					</div>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( $settings['showCase'] ) : ?>
			<div class="dpg-modal" data-dpg-modal="case" hidden>
				<div class="dpg-modal__backdrop" data-dpg-modal-close></div>
				<div class="dpg-modal__box" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $uid ); ?>-case-title">
					<header class="dpg-modal__head">
						<h3 id="<?php echo esc_attr( $uid ); ?>-case-title"><?php esc_html_e( 'Portfolio case study', 'design-project-generator' ); ?></h3>
						<button type="button" class="dpg-modal__close" data-dpg-modal-close>
							<span class="screen-reader-text"><?php esc_html_e( 'Close', 'design-project-generator' ); ?></span>
							<?php self::icon( 'x' ); ?>
						</button>
					</header>
					<form class="dpg-case" data-dpg-case-form>
						<p class="dpg-case__intro"><?php esc_html_e( 'Write up what you did. The answers are saved with the project and included in the case-study PDF.', 'design-project-generator' ); ?></p>
						<?php
						$fields = array(
							'concept'    => __( 'My concept', 'design-project-generator' ),
							'process'    => __( 'How I approached it', 'design-project-generator' ),
							'learned'    => __( 'What did you learn?', 'design-project-generator' ),
							'improve'    => __( 'What would you improve?', 'design-project-generator' ),
							'final_note' => __( 'Notes on the final design', 'design-project-generator' ),
						);

						foreach ( $fields as $key => $label ) :
							?>
							<p class="dpg-case__field">
								<label for="<?php echo esc_attr( $uid . '-case-' . $key ); ?>"><?php echo esc_html( $label ); ?></label>
								<textarea id="<?php echo esc_attr( $uid . '-case-' . $key ); ?>" rows="3" data-dpg-case="<?php echo esc_attr( $key ); ?>"></textarea>
							</p>
						<?php endforeach; ?>
						<div class="dpg-modal__actions">
							<button type="button" class="dpg-btn dpg-btn--primary" data-dpg-case-export>
								<?php self::icon( 'file-pdf' ); ?>
								<?php esc_html_e( 'Export case study PDF', 'design-project-generator' ); ?>
							</button>
							<button type="submit" class="dpg-btn"><?php esc_html_e( 'Save answers', 'design-project-generator' ); ?></button>
						</div>
					</form>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $settings['showSave'] ) : ?>
			<div class="dpg-modal" data-dpg-modal="my-projects" hidden>
				<div class="dpg-modal__backdrop" data-dpg-modal-close></div>
				<div class="dpg-modal__box" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $uid ); ?>-saved-title">
					<header class="dpg-modal__head">
						<h3 id="<?php echo esc_attr( $uid ); ?>-saved-title"><?php esc_html_e( 'My projects', 'design-project-generator' ); ?></h3>
						<button type="button" class="dpg-modal__close" data-dpg-modal-close>
							<span class="screen-reader-text"><?php esc_html_e( 'Close', 'design-project-generator' ); ?></span>
							<?php self::icon( 'x' ); ?>
						</button>
					</header>
					<div class="dpg-saved" data-dpg-saved-list></div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $settings['teacher'] ) : ?>
			<?php self::render_teacher( $uid ); ?>
		<?php endif; ?>

		<div class="dpg-export-stage" data-dpg-export-stage aria-hidden="true"></div>
		<?php
	}

	/**
	 * Teacher assignment builder.
	 *
	 * @param string $uid Instance identifier.
	 * @return void
	 */
	private static function render_teacher( $uid ) {
		?>
		<section class="dpg-teacher" data-dpg-teacher>
			<h3 class="dpg-teacher__title">
				<?php self::icon( 'users' ); ?>
				<?php esc_html_e( 'Teacher mode — create an assignment', 'design-project-generator' ); ?>
			</h3>
			<p class="dpg-teacher__intro"><?php esc_html_e( 'Generate a different brief for every student in one go. The set is saved to your account and can be exported as a text file.', 'design-project-generator' ); ?></p>
			<form class="dpg-teacher__form" data-dpg-teacher-form>
				<p class="dpg-teacher__field">
					<label for="<?php echo esc_attr( $uid ); ?>-a-title"><?php esc_html_e( 'Assignment title', 'design-project-generator' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $uid ); ?>-a-title" data-dpg-assignment="title" maxlength="120" />
				</p>
				<p class="dpg-teacher__field">
					<label for="<?php echo esc_attr( $uid ); ?>-a-category"><?php esc_html_e( 'Category', 'design-project-generator' ); ?></label>
					<select id="<?php echo esc_attr( $uid ); ?>-a-category" data-dpg-assignment="category">
						<option value=""><?php esc_html_e( 'Any category', 'design-project-generator' ); ?></option>
						<?php foreach ( DPG_Project_Database::categories() as $category ) : ?>
							<option value="<?php echo esc_attr( $category['id'] ); ?>"><?php echo esc_html( $category['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="dpg-teacher__field">
					<label for="<?php echo esc_attr( $uid ); ?>-a-difficulty"><?php esc_html_e( 'Difficulty', 'design-project-generator' ); ?></label>
					<select id="<?php echo esc_attr( $uid ); ?>-a-difficulty" data-dpg-assignment="difficulty">
						<option value=""><?php esc_html_e( 'Any difficulty', 'design-project-generator' ); ?></option>
						<?php foreach ( DPG_Security::$difficulties as $difficulty ) : ?>
							<option value="<?php echo esc_attr( $difficulty ); ?>"><?php echo esc_html( DPG_REST_API::difficulty_label( $difficulty ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="dpg-teacher__field">
					<label for="<?php echo esc_attr( $uid ); ?>-a-students"><?php esc_html_e( 'Number of students', 'design-project-generator' ); ?></label>
					<input type="number" id="<?php echo esc_attr( $uid ); ?>-a-students" min="1" max="100" value="10" data-dpg-assignment="students" />
				</p>
				<p class="dpg-teacher__field">
					<label for="<?php echo esc_attr( $uid ); ?>-a-due"><?php esc_html_e( 'Deadline', 'design-project-generator' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $uid ); ?>-a-due" placeholder="<?php esc_attr_e( '7 days', 'design-project-generator' ); ?>" data-dpg-assignment="due" />
				</p>
				<p class="dpg-teacher__actions">
					<button type="submit" class="dpg-btn dpg-btn--primary">
						<?php self::icon( 'users' ); ?>
						<?php esc_html_e( 'Generate assignment', 'design-project-generator' ); ?>
					</button>
				</p>
			</form>
			<div class="dpg-teacher__result" data-dpg-teacher-result></div>
		</section>
		<?php
	}

	/**
	 * Print an icon from the bundled sprite.
	 *
	 * @param string $name  Icon name without the prefix.
	 * @param string $class Extra CSS classes.
	 * @return void
	 */
	public static function icon( $name, $class = '' ) {
		printf(
			'<svg class="dpg-icon %s" aria-hidden="true" focusable="false"><use href="#dpg-i-%s"></use></svg>',
			esc_attr( $class ),
			esc_attr( DPG_Security::key( $name ) )
		);
	}

	/**
	 * Print the icon sprite once per request.
	 *
	 * @return void
	 */
	public static function sprite() {
		if ( self::$sprite_done ) {
			return;
		}

		self::$sprite_done = true;

		$path = DPG_PLUGIN_DIR . 'assets/icons/sprite.svg';

		if ( ! is_readable( $path ) ) {
			return;
		}

		// The sprite is a bundled plugin file, not user input. It is printed
		// inline so the icons need no extra request and no remote host.
		echo file_get_contents( $path ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Trusted bundled asset.
	}

	/**
	 * Queue the front-end assets. Only ever runs on pages that render the
	 * generator, and only queues the modules the instance actually needs.
	 *
	 * @param array $settings Instance settings.
	 * @return void
	 */
	public static function enqueue_assets( array $settings ) {
		wp_enqueue_style( 'dpg-frontend' );
		wp_enqueue_script( 'dpg-generator' );

		if ( $settings['showTimer'] ) {
			wp_enqueue_script( 'dpg-timer' );
		}

		if ( $settings['showExport'] || $settings['showCase'] ) {
			wp_enqueue_script( 'dpg-export' );
		}

		if ( $settings['showDemo'] ) {
			wp_enqueue_script( 'dpg-demo-editor' );
		}

		if ( self::$assets_done ) {
			return;
		}

		self::$assets_done = true;

		wp_localize_script( 'dpg-generator', 'DPGData', self::script_data() );
	}

	/**
	 * Data handed to the front-end scripts.
	 *
	 * @return array
	 */
	public static function script_data() {
		$settings = DPG_Admin::settings();

		return array(
			'restUrl' => esc_url_raw( rest_url( DPG_REST_API::NAMESPACE_V1 ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'options' => DPG_REST_API::options_payload(),
			'user'    => array(
				'loggedIn' => is_user_logged_in(),
				'canTeach' => DPG_Security::can_teach(),
			),
			'site'    => array(
				'name' => get_bloginfo( 'name' ),
				'url'  => home_url( '/' ),
			),
			'exports' => array(
				'pdfSize'    => $settings['pdf_size'],
				'pngWidth'   => (int) $settings['png_width'],
				'pngQuality' => $settings['png_quality'],
			),
			'i18n'    => self::strings(),
		);
	}

	/**
	 * Translatable strings used by the scripts.
	 *
	 * @return array<string,string>
	 */
	public static function strings() {
		return array(
			'generating'      => __( 'Generating a new brief…', 'design-project-generator' ),
			'generated'       => __( 'New brief ready.', 'design-project-generator' ),
			'error'           => __( 'Something went wrong. Please try again.', 'design-project-generator' ),
			'noMatch'         => __( 'No project matches those filters. Try widening them.', 'design-project-generator' ),
			'saved'           => __( 'Project saved.', 'design-project-generator' ),
			'savedLocal'      => __( 'Project saved in this browser. Log in to keep it with your account.', 'design-project-generator' ),
			'deleted'         => __( 'Project removed.', 'design-project-generator' ),
			'copied'          => __( 'Copied to the clipboard.', 'design-project-generator' ),
			'copyFailed'      => __( 'Copying failed. Select the text and copy it manually.', 'design-project-generator' ),
			'linkCopied'      => __( 'Shareable link copied. It rebuilds this exact brief.', 'design-project-generator' ),
			'exporting'       => __( 'Preparing your export…', 'design-project-generator' ),
			'exported'        => __( 'Export ready.', 'design-project-generator' ),
			'exportFailed'    => __( 'The export could not be created in this browser.', 'design-project-generator' ),
			'exportTooLarge'  => __( 'This project is very tall. The image was scaled down so it fits your browser’s limits.', 'design-project-generator' ),
			'timeUp'          => __( 'Time is up.', 'design-project-generator' ),
			'timerStarted'    => __( 'Timer started.', 'design-project-generator' ),
			'timerPaused'     => __( 'Timer paused.', 'design-project-generator' ),
			'timerStopped'    => __( 'Timer stopped.', 'design-project-generator' ),
			'noSaved'         => __( 'You have not saved any projects yet.', 'design-project-generator' ),
			'confirmDelete'   => __( 'Remove this saved project?', 'design-project-generator' ),
			'noDemo'          => __( 'This project type does not have an interactive demo.', 'design-project-generator' ),
			'demoLoadFailed'  => __( 'The demo could not be loaded.', 'design-project-generator' ),
			'statusNotStarted' => __( 'Not started', 'design-project-generator' ),
			'statusInProgress' => __( 'In progress', 'design-project-generator' ),
			'statusCompleted'  => __( 'Completed', 'design-project-generator' ),
			'assignmentDone'  => __( 'Assignment created.', 'design-project-generator' ),
			'open'            => __( 'Open', 'design-project-generator' ),
			'delete'          => __( 'Delete', 'design-project-generator' ),
			'reset'           => __( 'Filters cleared.', 'design-project-generator' ),
			'student'         => __( 'Student', 'design-project-generator' ),
			'headline'        => __( 'Headline', 'design-project-generator' ),
			'subheadline'     => __( 'Subheadline', 'design-project-generator' ),
			'description'     => __( 'Description', 'design-project-generator' ),
			'buttonText'      => __( 'Button text', 'design-project-generator' ),
			'price'           => __( 'Price', 'design-project-generator' ),
			'contact'         => __( 'Contact information', 'design-project-generator' ),
			'primaryColor'    => __( 'Primary colour', 'design-project-generator' ),
			'secondaryColor'  => __( 'Secondary colour', 'design-project-generator' ),
			'backgroundColor' => __( 'Background colour', 'design-project-generator' ),
			'textColor'       => __( 'Text colour', 'design-project-generator' ),
			'fontSize'        => __( 'Font size', 'design-project-generator' ),
			'radius'          => __( 'Corner radius', 'design-project-generator' ),
			'spacing'         => __( 'Spacing', 'design-project-generator' ),
			'content'         => __( 'Content', 'design-project-generator' ),
			'design'          => __( 'Design', 'design-project-generator' ),
			'resetDemo'       => __( 'Reset demo', 'design-project-generator' ),
			'downloadDemo'    => __( 'Download demo HTML', 'design-project-generator' ),
		);
	}
}
