<?php
/**
 * Remove plugin-owned settings on uninstall.
 *
 * @package HaloPress_Live2D
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'halopress_live2d_settings' );

