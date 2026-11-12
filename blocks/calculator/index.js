/**
 * BoxMetric Volume Calculator block (editor). Plain JavaScript using the editor's
 * global packages, so no build step is needed. The front end is rendered by
 * PHP (MSVCalc_Frontend::render_block).
 *
 * @package MSVCalc
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';

	const el = element.createElement;
	const __ = i18n.__;
	const InspectorControls = blockEditor.InspectorControls;
	const useBlockProps = blockEditor.useBlockProps;
	const PanelBody = components.PanelBody;
	const TextControl = components.TextControl;
	const TextareaControl = components.TextareaControl;
	const BaseControl = components.BaseControl;
	const ColorPalette = components.ColorPalette;
	const SelectControl = components.SelectControl;
	const ToggleControl = components.ToggleControl;

	// The plugin's own cube mark.
	const mark = el(
		'svg',
		{ viewBox: '0 0 24 24', width: 24, height: 24, fill: 'none', stroke: 'currentColor', strokeWidth: 1.75, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': true, focusable: false },
		el( 'path', { d: 'M12 2.75 20.25 7.4v9.2L12 21.25 3.75 16.6V7.4z' } ),
		el( 'path', { d: 'm3.75 7.4 8.25 4.6 8.25-4.6' } ),
		el( 'path', { d: 'M12 12v9.25' } ),
		el( 'path', { d: 'm7.9 5.1 8.2 4.6' } )
	);

	// A few starting points; any colour can be picked.
	const palette = [
		{ name: __( 'Teal', 'boxmetric-moving-storage-volume-calculator' ), color: '#0fb5ba' },
		{ name: __( 'Blue', 'boxmetric-moving-storage-volume-calculator' ), color: '#2563eb' },
		{ name: __( 'Green', 'boxmetric-moving-storage-volume-calculator' ), color: '#16a34a' },
		{ name: __( 'Orange', 'boxmetric-moving-storage-volume-calculator' ), color: '#ea580c' },
		{ name: __( 'Purple', 'boxmetric-moving-storage-volume-calculator' ), color: '#7c3aed' },
		{ name: __( 'Charcoal', 'boxmetric-moving-storage-volume-calculator' ), color: '#1f2937' },
	];

	const steps = [
		__( 'Visitors add items room by room', 'boxmetric-moving-storage-volume-calculator' ),
		__( 'The total updates live in m³ or ft³', 'boxmetric-moving-storage-volume-calculator' ),
		__( 'Van, container and storage unit suggestions', 'boxmetric-moving-storage-volume-calculator' ),
		__( 'Quote requests land in your inbox', 'boxmetric-moving-storage-volume-calculator' ),
	];

	blocks.registerBlockType( 'msvcalc/calculator', {
		icon: mark,
		edit: function ( props ) {
			const a = props.attributes;
			const set = props.setAttributes;
			const blockProps = useBlockProps( { className: 'msvcalc-block-preview' } );

			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Calculator', 'boxmetric-moving-storage-volume-calculator' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Heading', 'boxmetric-moving-storage-volume-calculator' ),
							help: __( 'Leave empty to use the heading from the plugin settings.', 'boxmetric-moving-storage-volume-calculator' ),
							value: a.title,
							onChange: function ( value ) {
								set( { title: value } );
							},
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} ),
						el( TextareaControl, {
							label: __( 'Introduction', 'boxmetric-moving-storage-volume-calculator' ),
							help: __( 'Leave empty to use the introduction from the plugin settings.', 'boxmetric-moving-storage-volume-calculator' ),
							value: a.intro,
							rows: 3,
							onChange: function ( value ) {
								set( { intro: value } );
							},
							__nextHasNoMarginBottom: true,
						} ),
						el( SelectControl, {
							label: __( 'Unit', 'boxmetric-moving-storage-volume-calculator' ),
							value: a.units,
							options: [
								{ label: __( 'Plugin setting', 'boxmetric-moving-storage-volume-calculator' ), value: '' },
								{ label: __( 'Cubic metres (m³)', 'boxmetric-moving-storage-volume-calculator' ), value: 'm3' },
								{ label: __( 'Cubic feet (ft³)', 'boxmetric-moving-storage-volume-calculator' ), value: 'ft3' },
							],
							onChange: function ( value ) {
								set( { units: value } );
							},
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} ),
						el( SelectControl, {
							label: __( 'Theme', 'boxmetric-moving-storage-volume-calculator' ),
							value: a.theme,
							options: [
								{ label: __( 'Plugin setting', 'boxmetric-moving-storage-volume-calculator' ), value: '' },
								{ label: __( 'Light', 'boxmetric-moving-storage-volume-calculator' ), value: 'light' },
								{ label: __( 'Dark', 'boxmetric-moving-storage-volume-calculator' ), value: 'dark' },
								{ label: __( 'Follow the visitor\'s device', 'boxmetric-moving-storage-volume-calculator' ), value: 'auto' },
							],
							onChange: function ( value ) {
								set( { theme: value } );
							},
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} ),
						el( ToggleControl, {
							label: __( 'Show the quote form', 'boxmetric-moving-storage-volume-calculator' ),
							checked: !! a.showQuote,
							onChange: function ( value ) {
								set( { showQuote: value } );
							},
							__nextHasNoMarginBottom: true,
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Colour', 'boxmetric-moving-storage-volume-calculator' ), initialOpen: false },
						el(
							BaseControl,
							{
								id: 'msvcalc-block-accent',
								label: __( 'Accent colour', 'boxmetric-moving-storage-volume-calculator' ),
								help: __( 'Clear it to use the colour from the plugin settings.', 'boxmetric-moving-storage-volume-calculator' ),
								__nextHasNoMarginBottom: true,
							},
							el( ColorPalette, {
								colors: palette,
								value: a.accent || undefined,
								disableCustomColors: false,
								clearable: true,
								onChange: function ( value ) {
									set( { accent: value || '' } );
								},
							} )
						)
					)
				),
				el(
					'div',
					blockProps,
					el(
						'div',
						{ className: 'msvcalc-block-preview__card' },
						el( 'span', { className: 'msvcalc-block-preview__mark', style: a.accent ? { background: a.accent } : undefined }, mark ),
						el(
							'div',
							null,
							el( 'p', { className: 'msvcalc-block-preview__eyebrow' }, 'BoxMetric' ),
							el( 'p', { className: 'msvcalc-block-preview__title' }, a.title || __( 'How much are you moving?', 'boxmetric-moving-storage-volume-calculator' ) ),
							el(
								'ul',
								{ className: 'msvcalc-block-preview__list' },
								steps.map( function ( step, index ) {
									return el( 'li', { key: index }, step );
								} )
							),
							el( 'p', { className: 'msvcalc-block-preview__note' }, __( 'The live calculator shows on the published page.', 'boxmetric-moving-storage-volume-calculator' ) )
						)
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n ) );
