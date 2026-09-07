/**
 * JavaScript port of the PHP brief renderer.
 *
 * Mirrors DPG_Shortcode::render_project() so the static demo produces the same
 * card markup the plugin serves. demo/parity-test.php diffs this against the
 * PHP output for hundreds of projects.
 *
 * Every value goes through escapeHtml before it reaches the string, exactly as
 * the PHP passes everything through esc_html().
 */

( function () {
	'use strict';

	var DIFFICULTY_LABELS = {
		beginner: 'Beginner',
		intermediate: 'Intermediate',
		advanced: 'Advanced',
		expert: 'Expert'
	};

	function escapeHtml( value ) {
		return String( value == null ? '' : value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	/** Mirrors DPG_Export::format_minutes(). */
	function formatMinutes( minutes ) {
		minutes = Math.max( 0, parseInt( minutes, 10 ) || 0 );

		if ( minutes < 60 ) {
			return minutes + ( minutes === 1 ? ' minute' : ' minutes' );
		}

		var hours = Math.floor( minutes / 60 );
		var rest = minutes % 60;

		if ( rest === 0 ) {
			return hours + ( hours === 1 ? ' hour' : ' hours' );
		}

		return hours + 'h ' + rest + 'm';
	}

	function icon( name ) {
		return '<svg class="dpg-icon " aria-hidden="true" focusable="false"><use href="#dpg-i-' +
			name + '"></use></svg>';
	}

	function fact( iconName, label, value ) {
		if ( ! String( value || '' ).trim() ) {
			return '';
		}

		return '<div class="dpg-brief__fact">' +
			'<dt>' + icon( iconName ) + '<span>' + escapeHtml( label ) + '</span></dt>' +
			'<dd>' + escapeHtml( value ) + '</dd>' +
			'</div>';
	}

	function listSection( title, items, modifier, checkable ) {
		items = ( items || [] ).filter( Boolean );

		if ( ! items.length ) {
			return '';
		}

		var rows = items.map( function ( item ) {
			return '<li>' +
				( checkable ? '<svg class="dpg-icon dpg-brief__tick" aria-hidden="true" focusable="false"><use href="#dpg-i-check"></use></svg>' : '' ) +
				'<span>' + escapeHtml( item ) + '</span>' +
				'</li>';
		} ).join( '' );

		return '<section class="dpg-brief__section dpg-brief__section--' + modifier + '">' +
			'<h4 class="dpg-brief__label">' + escapeHtml( title ) + '</h4>' +
			'<ul class="dpg-brief__list ' + ( checkable ? 'dpg-brief__list--check' : '' ) + '">' +
			rows + '</ul></section>';
	}

	/**
	 * Render a full brief.
	 *
	 * @param {Object} project Project data.
	 * @return {string} HTML.
	 */
	function renderProject( project ) {
		function get( key, fallback ) {
			var value = project[ key ];

			return ( value !== undefined && value !== '' ) ? value : ( fallback === undefined ? '' : fallback );
		}

		var difficulty = get( 'difficulty', 'beginner' );
		var out = '<article class="dpg-brief dpg-brief--' + escapeHtml( difficulty ) + '">';

		/* --- header --- */
		out += '<header class="dpg-brief__head"><div class="dpg-brief__chips">';
		out += '<span class="dpg-chip dpg-chip--category">' + escapeHtml( get( 'category' ) ) + '</span>';
		out += '<span class="dpg-chip dpg-chip--type">' + escapeHtml( get( 'project_type' ) ) + '</span>';
		out += '<span class="dpg-chip dpg-chip--difficulty" data-level="' + escapeHtml( get( 'difficulty' ) ) + '">' +
			escapeHtml( DIFFICULTY_LABELS[ get( 'difficulty' ) ] || get( 'difficulty' ) ) + '</span>';

		if ( get( 'demo_type', 'none' ) !== 'none' ) {
			out += '<span class="dpg-chip dpg-chip--demo">' + icon( 'monitor' ) + 'Demo available</span>';
		}

		if ( project.is_daily ) {
			out += '<span class="dpg-chip dpg-chip--daily">Today’s challenge</span>';
		}

		out += '</div><div class="dpg-brief__meta-line">';
		out += '<span class="dpg-brief__id">' + escapeHtml( get( 'id' ) ) + '</span>';
		out += '<span class="dpg-brief__time">' + icon( 'clock' ) +
			escapeHtml( formatMinutes( get( 'estimated_time', 60 ) ) ) + '</span>';
		out += '</div>';
		out += '<p class="dpg-brief__client">' + escapeHtml( get( 'client' ) ) + '</p>';
		out += '<h3 class="dpg-brief__title">' + escapeHtml( get( 'title' ) ) + '</h3>';
		out += '</header>';

		/* --- prose sections --- */
		if ( get( 'background' ) ) {
			out += '<section class="dpg-brief__section">' +
				'<h4 class="dpg-brief__label">Background</h4>' +
				'<p class="dpg-brief__prose">' + escapeHtml( get( 'background' ) ) + '</p></section>';
		}

		if ( get( 'objective' ) ) {
			out += '<section class="dpg-brief__section">' +
				'<h4 class="dpg-brief__label">Objective</h4>' +
				'<p class="dpg-brief__prose dpg-brief__prose--lead">' + escapeHtml( get( 'objective' ) ) + '</p></section>';
		}

		/* --- direction --- */
		var styleValue = ( get( 'style' ) + ( get( 'style_direction' ) ? '. ' + get( 'style_direction' ) : '' ) ).trim();
		var typeValue = ( get( 'typography' ) + ' ' + get( 'typography_note' ) ).trim();

		out += '<section class="dpg-brief__section">' +
			'<h4 class="dpg-brief__label">Direction</h4><dl class="dpg-brief__facts">';
		out += fact( 'target', 'Audience', get( 'audience' ) );
		out += fact( 'sparkles', 'Style', styleValue );
		out += fact( 'palette', 'Colour', get( 'color_direction' ) );
		out += fact( 'type', 'Typography', typeValue );
		out += fact( 'heart', 'Personality', get( 'personality' ) );
		out += fact( 'clock', 'Deadline', get( 'deadline' ) );
		out += fact( 'folder', 'Budget', get( 'budget' ) );
		out += '</dl></section>';

		/* --- lists --- */
		out += listSection( 'Deliverables', get( 'deliverables', [] ), 'deliverables', true );
		out += listSection( 'Required content', get( 'content', [] ), 'content', false );
		out += listSection( 'Restrictions', get( 'restrictions', [] ), 'restrictions', false );
		out += listSection( 'Competitor context', get( 'competitors', [] ), 'competitors', false );

		/* --- challenge --- */
		var challenge = project.challenge;

		if ( challenge && challenge.prompt ) {
			out += '<section class="dpg-brief__section dpg-brief__challenge">' +
				'<h4 class="dpg-brief__label">' + icon( 'target' ) + escapeHtml( challenge.name ) + '</h4>' +
				'<p class="dpg-brief__prose">' + escapeHtml( challenge.prompt ) + '</p>';

			if ( challenge.duration ) {
				out += '<p class="dpg-brief__challenge-time">' +
					escapeHtml( 'Suggested limit: ' + formatMinutes( Math.round( challenge.duration / 60 ) ) ) +
					'</p>';
			}

			out += '</section>';
		}

		out += listSection( 'Success criteria', get( 'success', [] ), 'success', true );
		out += '</article>';

		return out;
	}

	window.DPGRender = {
		project: renderProject,
		formatMinutes: formatMinutes,
		escapeHtml: escapeHtml,
		difficultyLabel: function ( value ) {
			return DIFFICULTY_LABELS[ value ] || value;
		}
	};
}() );
