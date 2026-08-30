/**
 * Editor script for the Design Project Generator block.
 *
 * Written without JSX so the plugin needs no build step: what ships is what
 * runs. The preview comes from ServerSideRender, which calls the same PHP
 * renderer the front end uses.
 *
 * @package DesignProjectGenerator
 */

( function ( blocks, element, blockEditor, components, i18n, serverSideRender ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var ToggleControl = components.ToggleControl;
	var ServerSideRender = serverSideRender;
	var data = window.DPGBlockData || {};

	/**
	 * Turn a { id: label } map into select options with an "any" entry.
	 *
	 * @param {Object} map       Identifier to label map.
	 * @param {string} anyLabel  Label for the empty option.
	 * @return {Array}
	 */
	function options( map, anyLabel ) {
		var out = [ { label: anyLabel, value: '' } ];

		Object.keys( map || {} ).forEach( function ( key ) {
			out.push( { label: map[ key ], value: key } );
		} );

		return out;
	}

	blocks.registerBlockType( 'dpg/generator', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			function toggle( key, label, help ) {
				return el( ToggleControl, {
					key: key,
					label: label,
					help: help,
					checked: !! attributes[ key ],
					onChange: function ( value ) {
						var update = {};

						update[ key ] = value;
						setAttributes( update );
					}
				} );
			}

			var inspector = el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __( 'Defaults', 'design-project-generator' ), initialOpen: true },
					el( TextControl, {
						label: __( 'Heading', 'design-project-generator' ),
						value: attributes.heading,
						placeholder: __( 'Design Project Generator', 'design-project-generator' ),
						onChange: function ( value ) {
							setAttributes( { heading: value } );
						}
					} ),
					el( TextControl, {
						label: __( 'Tagline', 'design-project-generator' ),
						value: attributes.tagline,
						placeholder: __( 'Generate. Design. Practice.', 'design-project-generator' ),
						onChange: function ( value ) {
							setAttributes( { tagline: value } );
						}
					} ),
					el( SelectControl, {
						label: __( 'Default category', 'design-project-generator' ),
						value: attributes.category,
						options: options( data.categories, __( 'Any category', 'design-project-generator' ) ),
						onChange: function ( value ) {
							setAttributes( { category: value } );
						}
					} ),
					el( SelectControl, {
						label: __( 'Default difficulty', 'design-project-generator' ),
						value: attributes.difficulty,
						options: options( data.difficulties, __( 'Any difficulty', 'design-project-generator' ) ),
						onChange: function ( value ) {
							setAttributes( { difficulty: value } );
						}
					} ),
					toggle(
						'daily',
						__( 'Daily challenge mode', 'design-project-generator' ),
						__( 'Show one brief that is the same for every visitor, all day.', 'design-project-generator' )
					),
					toggle(
						'demoOnly',
						__( 'Only projects with a demo', 'design-project-generator' ),
						__( 'Restrict generation to project types that include an interactive demo.', 'design-project-generator' )
					)
				),
				el(
					PanelBody,
					{ title: __( 'Visible features', 'design-project-generator' ), initialOpen: false },
					toggle( 'showFilters', __( 'Filters', 'design-project-generator' ) ),
					toggle( 'showTimer', __( 'Challenge timer', 'design-project-generator' ) ),
					toggle( 'showPalette', __( 'Colour palette tools', 'design-project-generator' ) ),
					toggle( 'showHints', __( 'I’m stuck hints', 'design-project-generator' ) ),
					toggle( 'showDemo', __( 'Interactive demo', 'design-project-generator' ) ),
					toggle( 'showExport', __( 'Export buttons', 'design-project-generator' ) ),
					toggle( 'showSave', __( 'Save button', 'design-project-generator' ) ),
					toggle(
						'teacher',
						__( 'Assignment builder', 'design-project-generator' ),
						__( 'Only shown to users who can edit posts.', 'design-project-generator' )
					)
				)
			);

			var preview = el( ServerSideRender, {
				block: 'dpg/generator',
				attributes: attributes
			} );

			var wrapper = useBlockProps
				? useBlockProps( { className: 'dpg-block-preview' } )
				: { className: 'dpg-block-preview' };

			return el(
				element.Fragment,
				{},
				inspector,
				el( 'div', wrapper, el( 'div', { className: 'dpg-block-preview__shield' }, preview ) )
			);
		},

		// Rendering happens in PHP, so nothing is stored in post content.
		save: function () {
			return null;
		}
	} );
}(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.serverSideRender
) );
