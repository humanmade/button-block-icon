<?php
/**
 * Plugin Name: Button Block Icon
 * Plugin URI: https://github.com/humanmade/button-block-icon
 * Description: Puts an icon beside the label on core/button, chosen from a registered icon collection or uploaded as a one-off SVG.
 * Version: 1.0.0
 * Requires at least: 7.1
 * Requires PHP: 8.3
 * Author: Human Made Limited
 * Author URI: https://humanmade.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: button-block-icon
 * Domain Path: /languages
 *
 * @package button-block-icon
 */

declare( strict_types=1 );

namespace HM\Button_Icon;

const VERSION = '1.0.0';

/**
 * The plugin's own directory, with a trailing slash.
 */
define( 'HM\\Button_Icon\\DIR', plugin_dir_path( __FILE__ ) );

/**
 * The plugin's own URL, with a trailing slash.
 */
define( 'HM\\Button_Icon\\URL', plugin_dir_url( __FILE__ ) );

require_once DIR . 'inc/attributes.php';
require_once DIR . 'inc/assets.php';
require_once DIR . 'inc/render.php';

/**
 * Set up the plugin.
 *
 * Everything here depends on the Icon Registration API, which landed in
 * WordPress 7.1. On anything older the plugin does nothing at all rather than
 * half of it: the picker would have no collection to read and `wp_get_icon()`
 * would be undefined at render time, so a button carrying an icon attribute
 * would fatal on the front end.
 */
function bootstrap(): void {
	if ( ! function_exists( 'wp_get_icon' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\unsupported_notice' );
		return;
	}

	Attributes\bootstrap();
	Assets\bootstrap();
	Render\bootstrap();
}

/**
 * Say why the plugin is inert.
 */
function unsupported_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	wp_admin_notice(
		esc_html__( 'Button Block Icon needs WordPress 7.1 or later for the Icon Registration API, and is doing nothing until then.', 'button-block-icon' ),
		[ 'type' => 'warning' ]
	);
}

bootstrap();
