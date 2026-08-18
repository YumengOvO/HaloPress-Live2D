<?php
/**
 * Plugin Name: HaloPress-Live2D
 * Plugin URI:  https://github.com/YumengOvO/HaloPress-Live2D
 * Description: Add a configurable Cubism 2 character widget to a WordPress site without bundling models or the Live2D runtime.
 * Version:     1.0.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author:      YumengOvO
 * Author URI:  https://github.com/YumengOvO
 * License:     GPL-3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: halopress-live2d
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HALOPRESS_LIVE2D_VERSION', '1.0.0' );
define( 'HALOPRESS_LIVE2D_FILE', __FILE__ );
define( 'HALOPRESS_LIVE2D_DIR', plugin_dir_path( __FILE__ ) );
define( 'HALOPRESS_LIVE2D_URL', plugin_dir_url( __FILE__ ) );

require_once HALOPRESS_LIVE2D_DIR . 'includes/class-halopress-live2d.php';

register_activation_hook( __FILE__, array( 'HaloPress_Live2D', 'activate' ) );

HaloPress_Live2D::instance();

