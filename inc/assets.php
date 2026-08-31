<?php
/**
 * Asset registration.
 *
 * Two bundles come out of `src/index.js`: the editor script with its stylesheet,
 * and the front-end stylesheet that wp-scripts splits `style.scss` into.
 *
 * The front-end stylesheet goes through `wp_enqueue_block_style()` rather than
 * `wp_enqueue_style()`, so a page with no button on it never downloads it, and
 * so the editor canvas gets the same rules the visitor does.
 *
 * The editor stylesheet has to reach two different documents, which is why it is
 * enqueued twice. The inspector panel is drawn in the admin page itself, while
 * the canvas preview is drawn inside the editor's iframe, and core populates the
 * iframe by re-running `enqueue_block_assets` in a fresh `WP_Styles` — with
 * `should_load_block_editor_scripts_and_styles` forced false, so
 * `enqueue_block_editor_assets` never fires there at all. A stylesheet enqueued
 * only on that hook lands in the admin page and never in the canvas.
 *
 * Registered handles are copied into the iframe's `WP_Styles`, so one
 * registration serves both routes.
 *
 * @package button-block-icon
 */

declare( strict_types=1 );

namespace HM\Button_Icon\Assets;

use HM\Button_Icon\Attributes;

use const HM\Button_Icon\DIR;
use const HM\Button_Icon\URL;
use const HM\Button_Icon\VERSION;

const HANDLE        = 'hm-button-icon';
const EDITOR_HANDLE = 'hm-button-icon-editor';

/**
 * Set up hooks.
 */
function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\\register_styles' );
	add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_editor_assets' );
}

/**
 * Read a wp-scripts asset manifest.
 *
 * @param string $name Bundle name, without extension.
 * @return array Dependencies, and a version to bust the cache with.
 */
function asset_manifest( string $name ): array {
	$path = DIR . "build/{$name}.asset.php";

	if ( ! is_readable( $path ) ) {
		return [
			'dependencies' => [],
			'version'      => VERSION,
		];
	}

	$manifest = require $path;

	return [
		'dependencies' => $manifest['dependencies'] ?? [],
		'version'      => $manifest['version'] ?? VERSION,
	];
}

/**
 * Register one built stylesheet.
 *
 * The `path` data is what lets core inline the file for a single button rather
 * than serving a request for it, and is what `wp_enqueue_block_style()` looks
 * for.
 *
 * @param string $handle Style handle.
 * @param string $file   Built file, relative to the plugin directory.
 * @return bool Whether the file was there to register.
 */
function register_style( string $handle, string $file ): bool {
	if ( ! is_readable( DIR . $file ) ) {
		return false;
	}

	$manifest = asset_manifest( 'index' );

	wp_register_style( $handle, URL . $file, [], $manifest['version'] );
	wp_style_add_data( $handle, 'path', DIR . $file );

	$rtl = substr( $file, 0, -4 ) . '-rtl.css';

	if ( is_readable( DIR . $rtl ) ) {
		wp_style_add_data( $handle, 'rtl', 'replace' );

		if ( is_rtl() ) {
			wp_style_add_data( $handle, 'path', DIR . $rtl );
		}
	}

	return true;
}

/**
 * Register both stylesheets and attach them to core/button.
 */
function register_styles(): void {
	if ( register_style( HANDLE, 'build/style-index.css' ) ) {
		wp_enqueue_block_style( Attributes\BLOCK, [ 'handle' => HANDLE ] );
	}

	// The canvas half of the editor stylesheet. `enqueue_editor_assets()` below
	// is the other half; see the note at the top of this file.
	if ( is_admin() && register_style( EDITOR_HANDLE, 'build/index.css' ) ) {
		wp_enqueue_block_style( Attributes\BLOCK, [ 'handle' => EDITOR_HANDLE ] );
	}
}

/**
 * Enqueue the editor script, and the editor stylesheet into the admin page.
 *
 * The settings object carries the attribute definitions so the JS registration
 * cannot drift from the PHP one, plus whatever the two filters in
 * `inc/attributes.php` resolved to. It is printed before the script rather than
 * localised, because `wp_localize_script()` stringifies everything and the
 * sizes have to arrive as numbers.
 */
function enqueue_editor_assets(): void {
	$script = 'build/index.js';

	if ( ! is_readable( DIR . $script ) ) {
		return;
	}

	$manifest = asset_manifest( 'index' );

	wp_enqueue_script(
		EDITOR_HANDLE,
		URL . $script,
		$manifest['dependencies'],
		$manifest['version'],
		[ 'in_footer' => true ]
	);

	wp_add_inline_script(
		EDITOR_HANDLE,
		'window.hmButtonIcon = ' . wp_json_encode(
			[
				'attributes'  => Attributes\definitions(),
				'collections' => Attributes\collections(),
				'sizes'       => Attributes\sizes(),
			]
		) . ';',
		'before'
	);

	wp_set_script_translations( EDITOR_HANDLE, 'button-block-icon' );

	// Registered on `init` above; this is the inspector half.
	wp_enqueue_style( EDITOR_HANDLE );
}
