<?php
/**
 * Plugin bootstrap.
 *
 * @package VRED_Geo_Maps
 */

namespace VRED\GeoMaps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	/** Bootstrap plugin services. */
	public static function bootstrap(): void {
		load_plugin_textdomain( 'vred-geo-maps', false, dirname( VRED_GEO_MAPS_BASENAME ) . '/languages' );

		add_action( 'init', array( Data::class, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( Renderer::class, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'maybe_enqueue_shortcode_assets' ), 20 );

		Shortcode::boot();
		Updater::boot();

		if ( is_admin() ) {
			Admin::boot();
		}
	}

	/** Initialize defaults on activation. */
	public static function activate(): void {
		Data::register();

		if ( false === get_option( VRED_GEO_MAPS_OPTION, false ) ) {
			add_option( VRED_GEO_MAPS_OPTION, Data::get_default_settings() );
		}
	}

	/** Enqueue shortcode assets early when possible. */
	public static function maybe_enqueue_shortcode_assets(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof \WP_Post || ! has_shortcode( (string) $post->post_content, 'vred_geo_map' ) ) {
			return;
		}

		$settings = Data::get_settings();
		Renderer::enqueue_assets( ! empty( $settings['clustering'] ) );
	}
}
