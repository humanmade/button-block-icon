<?php
/**
 * Turning the icon attributes into markup.
 *
 * Core renders `core/button` from a fixed `save`, so the icon cannot be
 * serialised into post content without inviting a block validation error on the next core
 * update. It is added at render time instead, keyed off the block's attributes
 * rather than off a class, so nothing about the saved markup has to change.
 *
 * @package button-block-icon
 */

declare( strict_types=1 );

namespace HM\Button_Icon\Render;

use WP_HTML_Tag_Processor;

use function HM\Button_Icon\Attributes\sizes;

use const HM\Button_Icon\Attributes\BLOCK;

/**
 * Set up hooks.
 */
function bootstrap(): void {
	add_filter( 'render_block_' . BLOCK, __NAMESPACE__ . '\\render', 10, 2 );
}

/**
 * Place the button's chosen icon beside its label.
 *
 * The icon comes from one of two places, and the editor keeps them mutually
 * exclusive so there is no precedence to resolve here: `hmIconName` names an
 * icon in a registered collection, `hmIconId` an SVG someone uploaded. Both end
 * up as inline SVG, because an `<img>` cannot inherit the button's text colour
 * and the icon has to follow it through hover, active and disabled.
 *
 * The class work goes through `WP_HTML_Tag_Processor`. Wrapping the label needs
 * an insertion, which the HTML API cannot do, so that one step is a single
 * bounded `preg_replace_callback()`.
 *
 * @param string $block_content Rendered block markup.
 * @param array  $block         Parsed block, including its attributes.
 * @return string Filtered block markup.
 */
function render( $block_content, array $block ): string {
	$attributes = $block['attrs'] ?? [];
	$icon       = icon_markup( $attributes );

	if ( '' === $icon ) {
		return $block_content;
	}

	// A cached render can come back through here, and a theme may have its own
	// filter on the same hook, so this has to be safe to apply twice.
	if ( str_contains( $block_content, 'hm-button-icon__label' ) ) {
		return $block_content;
	}

	$position = 'right' === ( $attributes['hmIconPosition'] ?? 'left' ) ? 'right' : 'left';

	$tags = new WP_HTML_Tag_Processor( $block_content );

	if ( $tags->next_tag( [ 'class_name' => 'wp-block-button' ] ) ) {
		$tags->add_class( 'hm-has-button-icon' );

		if ( 'right' === $position ) {
			$tags->add_class( 'hm-has-button-icon--right' );
		}

		if ( ! empty( $attributes['hmHideLabelOnMobile'] ) ) {
			$tags->add_class( 'hm-has-button-icon--hide-label' );
		}
	}

	$block_content = $tags->get_updated_html();

	/*
	 * Which side the icon falls on is carried by the order the two children are
	 * written in below, not by a class flipping the flex direction. One
	 * mechanism, so there is nothing for a later stylesheet to invert by
	 * accident, and the icon lands in the right place even where this plugin's
	 * stylesheet has not loaded.
	 *
	 * The block renders an anchor, or a `<button>` where the element type has
	 * been switched. Match whichever carries the link class, and only the first.
	 */
	return (string) preg_replace_callback(
		'#(<(a|button)\b[^>]*\bclass="[^"]*\bwp-block-button__link\b[^"]*"[^>]*>)(.*?)(</\2>)#is',
		static function ( array $matches ) use ( $icon, $position ): string {
			$label = '<span class="hm-button-icon__label">' . $matches[3] . '</span>';

			return $matches[1]
				. ( 'right' === $position ? $label . $icon : $icon . $label )
				. $matches[4];
		},
		$block_content,
		1
	);
}

/**
 * Resolve a button's icon attributes to inline SVG markup.
 *
 * @param array $attributes The block's attributes.
 * @return string SVG markup, or '' when the button has no icon.
 */
function icon_markup( array $attributes ): string {
	$sizes = sizes();
	$size  = absint( $attributes['hmIconSize'] ?? 24 );

	// The editor only offers the filtered sizes; anything else came from
	// hand-edited markup, or from a filter that has since changed its mind, and
	// falls back rather than being honoured.
	if ( ! in_array( $size, $sizes, true ) ) {
		$size = in_array( 24, $sizes, true ) ? 24 : (int) ( reset( $sizes ) ?: 24 );
	}

	$name = (string) ( $attributes['hmIconName'] ?? '' );

	if ( '' !== $name ) {
		/*
		 * An empty `label` is what makes core mark the SVG `aria-hidden` and
		 * unfocusable, which is what a decorative icon next to a text label
		 * wants. The button keeps its accessible name from the label.
		 *
		 * `--themed` is what opts the icon into taking its colour from the
		 * button. Registered icons go through the Icons API allowlist, which
		 * drops everything but fill geometry, so recolouring one is always
		 * right; an uploaded SVG is left as authored, since it may legitimately
		 * carry a gradient or a brand colour.
		 *
		 * The icon also carries its own slug, which is what lets a theme single
		 * one out — a chevron that travels on hover, say, where nothing else in
		 * the collection moves.
		 */
		$separator = strrpos( $name, '/' );
		$slug      = sanitize_html_class( false === $separator ? $name : substr( $name, $separator + 1 ) );

		return wp_get_icon(
			$name,
			[
				'size'  => $size,
				'class' => 'hm-button-icon hm-button-icon--themed hm-button-icon--' . $slug,
			]
		);
	}

	return uploaded_icon_markup( absint( $attributes['hmIconId'] ?? 0 ), $size );
}

/**
 * Read an uploaded SVG attachment and prepare it for inlining.
 *
 * The file is output as-is. Unlike a registered icon it is not put through the
 * Icons API allowlist, so it keeps strokes, gradients and groups; that is the
 * point of the upload escape hatch. It is also why a site allowing SVG uploads
 * at all should be sanitising them on upload — `safe-svg` or equivalent — which
 * is the same requirement SVG uploads carry without this plugin.
 *
 * @param int $attachment_id Attachment ID.
 * @param int $size          Rendered size in pixels.
 * @return string SVG markup, or '' when the attachment is not a readable SVG.
 */
function uploaded_icon_markup( $attachment_id, $size ): string {
	if ( $attachment_id < 1 || 'image/svg+xml' !== get_post_mime_type( $attachment_id ) ) {
		return '';
	}

	$path = get_attached_file( $attachment_id );

	if ( ! $path || ! is_readable( $path ) ) {
		return '';
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local uploaded file, not a remote request.
	$svg = file_get_contents( $path );

	if ( false === $svg ) {
		return '';
	}

	// Drop any XML prolog, DOCTYPE or comment ahead of the root element.
	$start = strpos( $svg, '<svg' );

	if ( false === $start ) {
		return '';
	}

	$tags = new WP_HTML_Tag_Processor( substr( $svg, $start ) );

	if ( ! $tags->next_tag( 'svg' ) ) {
		return '';
	}

	$tags->add_class( 'hm-button-icon' );
	$tags->set_attribute( 'width', (string) $size );
	$tags->set_attribute( 'height', (string) $size );

	// Match what `wp_get_icon()` produces for a decorative icon.
	$tags->set_attribute( 'aria-hidden', 'true' );
	$tags->set_attribute( 'focusable', 'false' );
	$tags->remove_attribute( 'role' );
	$tags->remove_attribute( 'aria-label' );

	return $tags->get_updated_html();
}
