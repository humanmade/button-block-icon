<?php
/**
 * The attributes the plugin adds to core/button.
 *
 * One definition, used three times: registered on the block server side from
 * here, handed to the editor script so the JS registration cannot drift from
 * it, and read back by the render filter.
 *
 * Registering them server side is not what makes the render filter work —
 * `render_block_core/button` is handed `WP_Block::$parsed_block`, whose `attrs`
 * come straight from the block comment and are never filtered against the
 * registry. It is what gives the attributes their defaults in
 * `WP_Block::$attributes`, and what puts them in `/wp/v2/block-types` for
 * anything else that reads what a block accepts.
 *
 * None of them declares a `source`, so they serialise into the block comment
 * and core/button's saved markup is untouched. That matters: core renders the
 * block from a fixed `save`, and anything written into that markup would raise
 * a validation error on the next core update.
 *
 * @package button-block-icon
 */

declare( strict_types=1 );

namespace HM\Button_Icon\Attributes;

const BLOCK = 'core/button';

/**
 * Set up hooks.
 */
function bootstrap(): void {
	add_filter( 'block_type_metadata', __NAMESPACE__ . '\\add_attributes' );
}

/**
 * The attribute definitions, in the shape `block.json` uses.
 *
 * `hmIconUrl` looks redundant next to `hmIconId`, and on the front end it is —
 * the render filter reads the file behind the attachment ID. It exists so the
 * editor can draw the preview without a second REST request per button.
 *
 * @return array<string, array<string, mixed>> Attribute name to schema.
 */
function definitions(): array {
	return [
		'hmIconName'          => [
			'type'    => 'string',
			'default' => '',
		],
		'hmIconId'            => [
			'type'    => 'number',
			'default' => 0,
		],
		'hmIconUrl'           => [
			'type'    => 'string',
			'default' => '',
		],
		'hmIconPosition'      => [
			'type'    => 'string',
			'enum'    => [ 'left', 'right' ],
			'default' => 'left',
		],
		'hmIconSize'          => [
			'type'    => 'number',
			'default' => 24,
		],
		'hmHideLabelOnMobile' => [
			'type'    => 'boolean',
			'default' => false,
		],
	];
}

/**
 * The icon sizes the picker offers and the render filter accepts.
 *
 * @return int[] Sizes in pixels.
 */
function sizes(): array {
	/**
	 * Filters the icon sizes a button can be set to.
	 *
	 * @param int[] $sizes Sizes in pixels.
	 */
	$sizes = (array) apply_filters( 'hm_button_icon_sizes', [ 16, 24, 32 ] );

	return array_values( array_unique( array_map( 'absint', $sizes ) ) );
}

/**
 * The icon collections the picker offers.
 *
 * An empty list means every collection registered with the Icons API, which is
 * the sensible default for a plugin on its own. A theme shipping its own
 * design system will usually want to name it, so that editors stay inside the
 * curated set rather than reaching for core's own icons.
 *
 * @return string[] Collection slugs, or an empty array for all of them.
 */
function collections(): array {
	/**
	 * Filters the icon collections offered in the button icon picker.
	 *
	 * @param string[] $collections Collection slugs. Empty for all registered collections.
	 */
	$collections = (array) apply_filters( 'hm_button_icon_collections', [] );

	return array_values( array_filter( array_map( 'sanitize_key', $collections ) ) );
}

/**
 * Declare the attributes on core/button.
 *
 * `block_type_metadata` rather than `register_block_type_args`, because it runs
 * before core builds the block type and so needs no knowledge of how core turns
 * metadata into arguments.
 *
 * @param array $metadata Parsed `block.json` metadata.
 * @return array Filtered metadata.
 */
function add_attributes( array $metadata ): array {
	if ( BLOCK !== ( $metadata['name'] ?? '' ) ) {
		return $metadata;
	}

	$metadata['attributes'] = array_merge( $metadata['attributes'] ?? [], definitions() );

	return $metadata;
}
