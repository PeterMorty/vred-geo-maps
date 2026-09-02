<?php
/**
 * VRED Geo Maps uninstall handler.
 *
 * @package VRED_Geo_Maps
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'vred_geo_maps_settings', array() );

if ( ! is_array( $settings ) || empty( $settings['delete_data_on_uninstall'] ) ) {
	return;
}

register_post_type(
	'vred_geo_location',
	array(
		'public' => false,
	)
);

register_taxonomy(
	'vred_geo_type',
	array( 'vred_geo_location' ),
	array(
		'public' => false,
	)
);

$location_ids = get_posts(
	array(
		'post_type'      => 'vred_geo_location',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	)
);

foreach ( $location_ids as $location_id ) {
	wp_delete_post( (int) $location_id, true );
}

$type_ids = get_terms(
	array(
		'taxonomy'   => 'vred_geo_type',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);

if ( ! is_wp_error( $type_ids ) ) {
	foreach ( $type_ids as $type_id ) {
		wp_delete_term( (int) $type_id, 'vred_geo_type' );
	}
}

delete_option( 'vred_geo_maps_settings' );
delete_site_transient( 'vred_geo_maps_remote_plugin_info' );
delete_transient( 'vred_geo_maps_nominatim_last_request' );

// Remove cached geocoding results whose keys are generated from addresses.
global $wpdb;
$like = $wpdb->esc_like( '_transient_vred_geo_maps_geocode_' ) . '%';
$timeout_like = $wpdb->esc_like( '_transient_timeout_vred_geo_maps_geocode_' ) . '%';

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$like,
		$timeout_like
	)
);
