/**
 * Icon controls for core/button.
 *
 * An icon button draws from one of two sources. `hmIconName` points at an icon
 * in a collection registered with the Icons API, which is the path that keeps
 * buttons on a design system. `hmIconId` points at an uploaded SVG, for the
 * one-off that does not justify a deploy. The two are kept mutually exclusive
 * here — picking one clears the other — so `render_block` never has to resolve
 * a precedence.
 *
 * The picker reads collections through the Icons REST API rather than bundling
 * SVGs into this script, so registering an icon is all it takes for it to
 * appear. Core has no reusable icon picker to import: the one in the core Icon
 * block is private to `@wordpress/block-library`, so the grid below is our own,
 * over the same `root`/`icon` entity that block reads.
 */

import {
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import { registerBlockVariation } from '@wordpress/blocks';
import {
	Button,
	Flex,
	Modal,
	PanelBody,
	RadioControl,
	SearchControl,
	SelectControl,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useMemo, useState } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';

import './editor.scss';
import './style.scss';

const BLOCK = 'core/button';

/**
 * Settings printed by `inc/assets.php`.
 *
 * The attribute definitions come from PHP so the two registrations cannot
 * drift. The fallbacks are only here so a stale cached script cannot take the
 * editor down with it.
 */
const SETTINGS = window.hmButtonIcon ?? {};
const ATTRIBUTES = SETTINGS.attributes ?? {};
const COLLECTIONS = SETTINGS.collections ?? [];
const SIZES = SETTINGS.sizes?.length ? SETTINGS.sizes : [ 16, 24, 32 ];

/** Attribute values that clear whichever source is not in use. */
const NO_ICON = {
	hmIconName: '',
	hmIconId: 0,
	hmIconUrl: '',
};

/**
 * The queries that cover the offered collections.
 *
 * An empty `collections` list means every registered collection, which is one
 * unfiltered query rather than none.
 *
 * @return {Array<Object>} Entity queries.
 */
function iconQueries() {
	if ( ! COLLECTIONS.length ) {
		return [ {} ];
	}

	return COLLECTIONS.map( ( collection ) => ( { collection } ) );
}

const QUERIES = iconQueries();

/**
 * The size control's options.
 *
 * Built on call rather than at module scope so the labels are translated after
 * the editor has loaded its locale data.
 *
 * @return {Array<{label: string, value: string}>} Select options.
 */
function sizeOptions() {
	return SIZES.map( ( size ) => ( {
		label: sprintf(
			/* translators: %d: icon size in pixels. */
			__( '%d px', 'button-block-icon' ),
			size
		),
		value: String( size ),
	} ) );
}

/**
 * Give a registered icon's markup an explicit size.
 *
 * Registered SVGs ship without `width`/`height` so that the render filter can
 * size them, which leaves them to fill their container in the editor. Setting
 * the attributes directly is more predictable than sizing the wrapper and
 * hoping the SVG scales into it.
 *
 * @param {string} content Icon SVG markup from the REST API.
 * @param {number} size    Size in pixels.
 * @return {string} Sized SVG markup.
 */
function sizedIcon( content, size ) {
	return content.replace(
		'<svg',
		`<svg width="${ size }" height="${ size }"`
	);
}

/**
 * Declare the icon attributes on core/button.
 *
 * @param {Object} settings Block settings.
 * @param {string} name     Block name.
 * @return {Object} Filtered block settings.
 */
function addAttributes( settings, name ) {
	if ( name !== BLOCK ) {
		return settings;
	}

	return {
		...settings,
		attributes: { ...settings.attributes, ...ATTRIBUTES },
	};
}

addFilter(
	'blocks.registerBlockType',
	'hm-button-icon/attributes',
	addAttributes
);

registerBlockVariation( BLOCK, {
	name: 'hm-button-icon/icon-button',
	title: __( 'Icon Button', 'button-block-icon' ),
	description: __(
		'A button with an icon beside its label.',
		'button-block-icon'
	),
	scope: [ 'inserter' ],
	isActive: ( attributes ) =>
		!! ( attributes.hmIconName || attributes.hmIconId ),
} );

/**
 * The offered icons, and whether every request has settled.
 *
 * The `icon` entity does not paginate, so this is one request per collection
 * and the search below can filter what it already has.
 *
 * @return {{icons: Array, hasResolved: boolean}} Icon records and resolution state.
 */
function useCollectionIcons() {
	return useSelect( ( select ) => {
		const { getEntityRecords, hasFinishedResolution } = select( coreStore );

		const results = QUERIES.map( ( query ) => ( {
			records: getEntityRecords( 'root', 'icon', query ),
			hasResolved: hasFinishedResolution( 'getEntityRecords', [
				'root',
				'icon',
				query,
			] ),
		} ) );

		return {
			icons: results.flatMap( ( { records } ) => records ?? [] ),
			hasResolved: results.every( ( { hasResolved } ) => hasResolved ),
		};
	}, [] );
}

/**
 * The icon library, as a searchable grid.
 *
 * @param {Object}   props          Component props.
 * @param {Function} props.onSelect Called with the chosen icon record.
 * @param {Function} props.onClose  Called to dismiss the modal.
 * @return {Element} The modal.
 */
function IconLibrary( { onSelect, onClose } ) {
	const [ search, setSearch ] = useState( '' );
	const { icons, hasResolved } = useCollectionIcons();

	const matches = useMemo( () => {
		const term = search.trim().toLowerCase();

		if ( ! term ) {
			return icons;
		}

		return icons.filter(
			( icon ) =>
				icon.name.toLowerCase().includes( term ) ||
				icon.label.toLowerCase().includes( term )
		);
	}, [ icons, search ] );

	return (
		<Modal
			title={ __( 'Icon library', 'button-block-icon' ) }
			onRequestClose={ onClose }
		>
			<SearchControl
				__nextHasNoMarginBottom
				label={ __( 'Search icons', 'button-block-icon' ) }
				value={ search }
				onChange={ setSearch }
			/>

			{ ! hasResolved && <Spinner /> }

			{ hasResolved && ! matches.length && (
				<p>{ __( 'No icons found.', 'button-block-icon' ) }</p>
			) }

			<div className="hm-button-icon-library">
				{ matches.map( ( icon ) => (
					<Button
						key={ icon.name }
						className="hm-button-icon-library__item"
						label={ icon.label }
						showTooltip
						onClick={ () => onSelect( icon ) }
					>
						<span
							// The REST API returns content core has already run
							// through wp_kses, down to <svg>, <path> and
							// <polygon> with a fixed attribute allowlist.
							dangerouslySetInnerHTML={ {
								__html: sizedIcon( icon.content, 24 ),
							} }
						/>
					</Button>
				) ) }
			</div>
		</Modal>
	);
}

/**
 * A preview of the icon currently on the block, from either source.
 *
 * @param {Object} props            Component props.
 * @param {Object} props.attributes The block's attributes.
 * @return {Element|null} The preview, or null when there is no icon.
 */
function IconPreview( { attributes } ) {
	const { icons } = useCollectionIcons();
	const { hmIconName, hmIconUrl } = attributes;

	if ( hmIconName ) {
		const icon = icons.find( ( { name } ) => name === hmIconName );

		if ( ! icon ) {
			return null;
		}

		return (
			<span
				// See the note in IconLibrary: this markup is sanitised by core.
				dangerouslySetInnerHTML={ {
					__html: sizedIcon( icon.content, 32 ),
				} }
			/>
		);
	}

	if ( hmIconUrl ) {
		return <img src={ hmIconUrl } alt="" width="32" height="32" />;
	}

	return null;
}

const withIconControls = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		const [ isLibraryOpen, setLibraryOpen ] = useState( false );

		if ( props.name !== BLOCK ) {
			return <BlockEdit { ...props } />;
		}

		const { attributes, setAttributes } = props;
		const hasIcon = !! ( attributes.hmIconName || attributes.hmIconId );

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody title={ __( 'Icon', 'button-block-icon' ) }>
						<Flex justify="flex-start" wrap>
							<Button
								variant="secondary"
								onClick={ () => setLibraryOpen( true ) }
							>
								{ __( 'Choose icon', 'button-block-icon' ) }
							</Button>

							<MediaUploadCheck>
								<MediaUpload
									allowedTypes={ [ 'image/svg+xml' ] }
									value={ attributes.hmIconId }
									render={ ( { open } ) => (
										<Button
											variant="secondary"
											onClick={ open }
										>
											{ __(
												'Upload SVG',
												'button-block-icon'
											) }
										</Button>
									) }
									onSelect={ ( media ) =>
										setAttributes( {
											...NO_ICON,
											hmIconId: media.id,
											hmIconUrl: media.url,
										} )
									}
								/>
							</MediaUploadCheck>

							{ hasIcon && (
								<Button
									isDestructive
									variant="tertiary"
									onClick={ () => setAttributes( NO_ICON ) }
								>
									{ __( 'Remove', 'button-block-icon' ) }
								</Button>
							) }
						</Flex>

						{ hasIcon && (
							<>
								<div className="hm-button-icon-preview">
									<IconPreview attributes={ attributes } />
								</div>

								<SelectControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __( 'Size', 'button-block-icon' ) }
									options={ sizeOptions() }
									value={ String( attributes.hmIconSize ) }
									onChange={ ( value ) =>
										setAttributes( {
											hmIconSize: Number( value ),
										} )
									}
								/>

								<RadioControl
									label={ __(
										'Position',
										'button-block-icon'
									) }
									options={ [
										{
											label: __(
												'Before the label',
												'button-block-icon'
											),
											value: 'left',
										},
										{
											label: __(
												'After the label',
												'button-block-icon'
											),
											value: 'right',
										},
									] }
									selected={ attributes.hmIconPosition }
									onChange={ ( value ) =>
										setAttributes( {
											hmIconPosition: value,
										} )
									}
								/>

								<ToggleControl
									__nextHasNoMarginBottom
									label={ __(
										'Hide label on mobile',
										'button-block-icon'
									) }
									help={ __(
										'Leaves the icon alone on small screens. The label stays available to screen readers.',
										'button-block-icon'
									) }
									checked={
										!! attributes.hmHideLabelOnMobile
									}
									onChange={ ( value ) =>
										setAttributes( {
											hmHideLabelOnMobile: value,
										} )
									}
								/>
							</>
						) }
					</PanelBody>
				</InspectorControls>

				{ isLibraryOpen && (
					<IconLibrary
						onClose={ () => setLibraryOpen( false ) }
						onSelect={ ( icon ) => {
							setAttributes( {
								...NO_ICON,
								hmIconName: icon.name,
							} );
							setLibraryOpen( false );
						} }
					/>
				) }
			</>
		);
	},
	'withIconControls'
);

addFilter( 'editor.BlockEdit', 'hm-button-icon/controls', withIconControls );

/**
 * Turn icon markup into a CSS `url()` value.
 *
 * `viewBox` has to have its camel case restored first. Core sanitises icon
 * content with `wp_kses`, which lowercases attribute names, so what the REST
 * API hands back says `viewbox`. Inline in a page that is harmless — the HTML
 * parser corrects the case for foreign content — but a data URI is parsed as
 * XML, where attribute names are case-sensitive. An unrecognised `viewbox`
 * means no viewBox at all, so the artwork draws at its raw coordinates and is
 * clipped by the box instead of scaling into it.
 *
 * @param {string} content Icon SVG markup.
 * @return {string} A `url()` value holding the icon as a data URI.
 */
function iconUrlValue( content ) {
	const xml = content.replace( 'viewbox=', 'viewBox=' );

	return `url("data:image/svg+xml,${ encodeURIComponent( xml ) }")`;
}

/**
 * The canvas preview, as custom properties and classes on the block wrapper.
 *
 * Split out of the filter below so the icon store is only subscribed to for
 * buttons that actually carry an icon, rather than for every block on the page.
 *
 * @param {Object}   props                Block list props.
 * @param {Function} props.BlockListBlock The wrapped component.
 * @return {Element} The block, with the preview attached.
 */
function IconPreviewBlock( { BlockListBlock, ...props } ) {
	const { icons } = useCollectionIcons();
	const { hmIconName, hmIconUrl, hmIconSize, hmIconPosition } =
		props.attributes;

	const source = hmIconName
		? icons.find( ( { name } ) => name === hmIconName )
		: null;

	// A collection icon that has not resolved yet renders nothing rather than a
	// gap, since the size is reserved by the pseudo-element either way.
	if ( hmIconName && ! source ) {
		return <BlockListBlock { ...props } />;
	}

	// The size has to be stamped onto the SVG itself, not left to `mask-size`.
	// Registered icons ship without `width`/`height` so the render filter can
	// set them, which leaves the mask image with an intrinsic ratio but no
	// intrinsic size — `contain` then has nothing to scale from and the artwork
	// draws at its viewBox size, cropped or adrift in the box.
	const value = source
		? iconUrlValue( sizedIcon( source.content, hmIconSize ) )
		: `url("${ hmIconUrl }")`;
	const variant = source
		? 'hm-has-button-icon--themed'
		: 'hm-has-button-icon--custom';

	return (
		<BlockListBlock
			{ ...props }
			className={ [
				props.className,
				'hm-has-button-icon',
				variant,
				'right' === hmIconPosition ? 'hm-has-button-icon--right' : '',
			]
				.filter( Boolean )
				.join( ' ' ) }
			wrapperProps={ {
				...props.wrapperProps,
				style: {
					...props.wrapperProps?.style,
					'--hm-button-icon': value,
					'--hm-button-icon-size': `${ hmIconSize }px`,
				},
			} }
		/>
	);
}

const withIconPreview = createHigherOrderComponent(
	( BlockListBlock ) => ( props ) => {
		const { hmIconName, hmIconUrl } = props.attributes ?? {};

		if ( props.name !== BLOCK || ! ( hmIconName || hmIconUrl ) ) {
			return <BlockListBlock { ...props } />;
		}

		return (
			<IconPreviewBlock BlockListBlock={ BlockListBlock } { ...props } />
		);
	},
	'withIconPreview'
);

addFilter( 'editor.BlockListBlock', 'hm-button-icon/preview', withIconPreview );
