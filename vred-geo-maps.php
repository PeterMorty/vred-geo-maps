<?php
/**
 * Plugin Name: VRED Geo Maps
 * Description: VRED Geo Maps manages reusable locations and displays them on Leaflet maps via shortcode.
 * Version: 1.0.0.11
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author: VRED
 * Author URI: https://viviendoenred.com
 * Text Domain: vred-geo-maps
 * Domain Path: /languages
 * Update URI: https://dev.viviendoenred.com/wordpress/plugins/vred-geo-maps/updates/
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VRED_GEO_MAPS_VERSION', '1.0.0.11' );
define( 'VRED_GEO_MAPS_FILE', __FILE__ );
define( 'VRED_GEO_MAPS_BASENAME', plugin_basename( __FILE__ ) );
define( 'VRED_GEO_MAPS_PATH', plugin_dir_path( __FILE__ ) );
define( 'VRED_GEO_MAPS_URL', plugin_dir_url( __FILE__ ) );
define( 'VRED_GEO_MAPS_SLUG', 'vred-geo-maps' );
define( 'VRED_GEO_MAPS_OPTION', 'vred_geo_maps_settings' );
define( 'VRED_GEO_MAPS_UPDATE_URL', 'https://dev.viviendoenred.com/wordpress/plugins/vred-geo-maps/updates/vred-geo-maps.json' );

require_once VRED_GEO_MAPS_PATH . 'includes/class-data.php';
require_once VRED_GEO_MAPS_PATH . 'includes/class-renderer.php';
require_once VRED_GEO_MAPS_PATH . 'includes/class-shortcode.php';
require_once VRED_GEO_MAPS_PATH . 'includes/class-admin.php';
require_once VRED_GEO_MAPS_PATH . 'includes/class-updater.php';
require_once VRED_GEO_MAPS_PATH . 'includes/class-plugin.php';

register_activation_hook( VRED_GEO_MAPS_FILE, array( 'VRED\\GeoMaps\\Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'VRED\\GeoMaps\\Plugin', 'bootstrap' ) );
