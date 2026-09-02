<?php
/**
 * Shortcode integration.
 *
 * @package VRED_Geo_Maps
 */

namespace VRED\GeoMaps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcode {
	/** Register shortcode. */
	public static function boot(): void {
		add_shortcode( 'vred_geo_map', array( self::class, 'render' ) );
	}

	/** Render shared map output. */
	public static function render( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'ids'      => '',
				'types'    => '',
				'list'     => 'inherit',
				'filters'  => 'inherit',
				'cluster'  => 'inherit',
				'position' => 'inherit',
			),
			$atts,
			'vred_geo_map'
		);

		$query = array();

		if ( '' !== trim( (string) $atts['ids'] ) ) {
			$query['ids'] = array_values( array_filter( array_map( 'absint', preg_split( '/\s*,\s*/', (string) $atts['ids'] ) ?: array() ) ) );
		}

		if ( '' !== trim( (string) $atts['types'] ) ) {
			$query['type_slugs'] = array_values( array_filter( array_map( 'sanitize_title', preg_split( '/\s*,\s*/', (string) $atts['types'] ) ?: array() ) ) );
		}

		$overrides = array();

		if ( in_array( $atts['list'], array( 'yes', 'no' ), true ) ) {
			$overrides['show_list'] = 'yes' === $atts['list'] ? 1 : 0;
		}

		if ( in_array( $atts['filters'], array( 'yes', 'no' ), true ) ) {
			$visible = 'yes' === $atts['filters'] ? 1 : 0;
			$overrides['show_search'] = $visible;
			$overrides['show_type_filter'] = $visible;

			if ( ! $visible ) {
				$overrides['show_country_filter'] = 0;
				$overrides['show_region_filter']  = 0;
				$overrides['show_city_filter']    = 0;
			}
		}

		if ( in_array( $atts['cluster'], array( 'yes', 'no' ), true ) ) {
			$overrides['clustering'] = 'yes' === $atts['cluster'] ? 1 : 0;
		}

		if ( in_array( $atts['position'], array( 'left', 'right', 'top', 'bottom' ), true ) ) {
			$overrides['list_position'] = $atts['position'];
		}

		return Renderer::render( $query, $overrides );
	}
}
