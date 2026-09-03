<?php
/**
 * Data registration and normalization.
 *
 * @package VRED_Geo_Maps
 */

namespace VRED\GeoMaps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Data {
	public const POST_TYPE = 'vred_geo_location';
	public const TAXONOMY = 'vred_geo_type';

	public const META_ADDRESS = '_vred_geo_address';
	public const META_LATITUDE = '_vred_geo_latitude';
	public const META_LONGITUDE = '_vred_geo_longitude';
	public const META_CITY = '_vred_geo_city';
	public const META_REGION = '_vred_geo_region';
	public const META_COUNTRY = '_vred_geo_country';
	public const META_SUBTITLE = '_vred_geo_subtitle';
	public const META_PHONE = '_vred_geo_phone';
	public const META_EMAIL = '_vred_geo_email';
	public const META_WEBSITE = '_vred_geo_website';
	public const META_ACTION = '_vred_geo_action';
	public const META_LINK_URL = '_vred_geo_link_url';
	public const META_LINK_TARGET = '_vred_geo_link_target';
	public const META_POPUP_CONTENT = '_vred_geo_popup_content';
	public const META_POPUP_CUSTOM = '_vred_geo_popup_custom';
	public const META_MARKER_IMAGE_ID = '_vred_geo_marker_image_id';
	public const META_MARKER_SVG = '_vred_geo_marker_svg';
	public const META_MARKER_COLOR = '_vred_geo_marker_color';
	public const META_MARKER_SIZE = '_vred_geo_marker_size';

	public const TERM_META_ORDER = '_vred_geo_order';
	public const TERM_META_MARKER_IMAGE_ID = '_vred_geo_marker_image_id';
	public const TERM_META_MARKER_SVG = '_vred_geo_marker_svg';
	public const TERM_META_MARKER_COLOR = '_vred_geo_marker_color';
	public const TERM_META_MARKER_SIZE = '_vred_geo_marker_size';

	/** Register hidden storage objects. */
	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Locations', 'vred-geo-maps' ),
					'singular_name' => __( 'Location', 'vred-geo-maps' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title', 'page-attributes' ),
				'map_meta_cap'        => true,
				'capability_type'     => 'post',
				'rewrite'             => false,
				'query_var'           => false,
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			array( self::POST_TYPE ),
			array(
				'labels' => array(
					'name'          => __( 'Location Types', 'vred-geo-maps' ),
					'singular_name' => __( 'Location Type', 'vred-geo-maps' ),
				),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => false,
				'show_in_menu'       => false,
				'show_in_rest'       => false,
				'hierarchical'       => false,
				'rewrite'            => false,
				'query_var'          => false,
			)
		);
	}

	/** Return plugin defaults. */
	public static function get_default_settings(): array {
		return array(
			'tile_provider'        => 'openstreetmap',
			'carto_api_key'        => '',
			'theme_color'          => '#2f6fed',
			'appearance'           => 'default',
			'map_height'           => 560,
			'map_border_radius'    => 18,
			'initial_zoom'         => 6,
			'auto_fit'             => 1,
			'clustering'           => 1,
			'show_list'            => 1,
			'list_style'           => 'cards',
			'list_type_indicator'  => 'auto',
			'map_legend_type_indicator' => 'auto',
			'map_legend_visible_locations_per_type' => 5,
			'map_legend_border_radius' => 14,
			'map_legend_background_transparency' => 0,
			'list_position'        => 'left',
			'filters_position'     => 'top',
			'filters_map_position' => 'top-right',
			'show_filters'         => 1,
			'show_map_legend'      => 0,
			'map_legend_position'  => 'top-right',
			'show_search'          => 1,
			'show_type_filter'     => 1,
			'show_country_filter'  => 0,
			'show_region_filter'   => 0,
			'show_city_filter'     => 0,
			'show_directions_link' => 1,
			'list_width'           => 360,
			'gap'                  => 20,
			'filters_radius'       => 16,
			'filters_background_transparency' => 0,
			'card_radius'          => 14,
			'marker_image_id'      => 0,
			'marker_svg'           => '',
			'marker_color'         => '#2f6fed',
			'marker_size'          => 34,
			'popup_text_color'     => '#202124',
			'popup_background'     => '#ffffff',
			'popup_border_color'   => '#d9dedb',
			'popup_border_radius'  => 14,
			'popup_width'          => 320,
			'delete_data_on_uninstall' => 0,
		);
	}

	/** Return sanitized current settings merged with defaults. */
	public static function get_settings(): array {
		$stored   = get_option( VRED_GEO_MAPS_OPTION, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$defaults = self::get_default_settings();
		$type_indicators = array( 'auto', 'icon', 'color' );

		unset( $stored['accent_color'] );

		if ( array_key_exists( 'type_indicator', $stored ) ) {
			$legacy_type_indicator = in_array( $stored['type_indicator'], $type_indicators, true ) ? $stored['type_indicator'] : $defaults['list_type_indicator'];
			$stored['list_type_indicator'] = $stored['list_type_indicator'] ?? $legacy_type_indicator;
			$stored['map_legend_type_indicator'] = $stored['map_legend_type_indicator'] ?? $legacy_type_indicator;
		}

		if ( ! array_key_exists( 'filters_radius', $stored ) && array_key_exists( 'filters_border_radius', $stored ) ) {
			$stored['filters_radius'] = self::clamp_int( $stored['filters_border_radius'], 0, 40 );
		}

		unset( $stored['type_indicator'], $stored['filters_border_radius'] );

		if ( '#2f6f58' === strtolower( (string) ( $stored['marker_color'] ?? '' ) ) ) {
			$stored['marker_color'] = $defaults['marker_color'];
		}

		return wp_parse_args( $stored, $defaults );
	}

	/** Sanitize settings option. */
	public static function sanitize_settings( mixed $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::get_default_settings();

		$tile_providers = array( 'openstreetmap', 'carto_positron', 'carto_positron_nolabels', 'carto_voyager' );
		$appearances    = array( 'default', 'grayscale', 'soft', 'dark', 'contrast', 'muted', 'warm', 'cool', 'sepia', 'blueprint' );
		$positions      = array( 'left', 'right', 'top', 'bottom' );
		$filter_positions = array( 'top', 'panel', 'bottom', 'map' );
		$map_positions  = array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' );
		$list_styles    = array( 'cards', 'compact', 'legend', 'grouped' );
		$type_indicators = array( 'auto', 'icon', 'color' );

		$settings = array(
			'tile_provider'       => in_array( $input['tile_provider'] ?? '', $tile_providers, true ) ? $input['tile_provider'] : $defaults['tile_provider'],
			'carto_api_key'       => trim( sanitize_text_field( (string) ( $input['carto_api_key'] ?? '' ) ) ),
			'theme_color'         => sanitize_hex_color( $input['theme_color'] ?? '' ) ?: $defaults['theme_color'],
			'appearance'          => in_array( $input['appearance'] ?? '', $appearances, true ) ? $input['appearance'] : $defaults['appearance'],
			'map_height'          => self::clamp_int( $input['map_height'] ?? $defaults['map_height'], 240, 900 ),
			'map_border_radius'   => self::clamp_int( $input['map_border_radius'] ?? $defaults['map_border_radius'], 0, 40 ),
			'initial_zoom'        => self::clamp_int( $input['initial_zoom'] ?? $defaults['initial_zoom'], 1, 19 ),
			'auto_fit'            => ! empty( $input['auto_fit'] ) ? 1 : 0,
			'clustering'          => ! empty( $input['clustering'] ) ? 1 : 0,
			'show_list'           => ! empty( $input['show_list'] ) ? 1 : 0,
			'list_style'          => in_array( $input['list_style'] ?? '', $list_styles, true ) ? $input['list_style'] : $defaults['list_style'],
			'list_type_indicator' => in_array( $input['list_type_indicator'] ?? '', $type_indicators, true ) ? $input['list_type_indicator'] : $defaults['list_type_indicator'],
			'map_legend_type_indicator' => in_array( $input['map_legend_type_indicator'] ?? '', $type_indicators, true ) ? $input['map_legend_type_indicator'] : $defaults['map_legend_type_indicator'],
			'map_legend_visible_locations_per_type' => self::clamp_int( $input['map_legend_visible_locations_per_type'] ?? $defaults['map_legend_visible_locations_per_type'], 1, 20 ),
			'map_legend_border_radius' => self::clamp_int( $input['map_legend_border_radius'] ?? $defaults['map_legend_border_radius'], 0, 40 ),
			'map_legend_background_transparency' => self::clamp_int( $input['map_legend_background_transparency'] ?? $defaults['map_legend_background_transparency'], 0, 100 ),
			'list_position'       => in_array( $input['list_position'] ?? '', $positions, true ) ? $input['list_position'] : $defaults['list_position'],
			'filters_position'    => in_array( $input['filters_position'] ?? '', $filter_positions, true ) ? $input['filters_position'] : $defaults['filters_position'],
			'filters_map_position' => in_array( $input['filters_map_position'] ?? '', $map_positions, true ) ? $input['filters_map_position'] : $defaults['filters_map_position'],
			'show_filters'        => ! empty( $input['show_filters'] ) ? 1 : 0,
			'show_map_legend'     => ! empty( $input['show_map_legend'] ) ? 1 : 0,
			'map_legend_position' => in_array( $input['map_legend_position'] ?? '', $map_positions, true ) ? $input['map_legend_position'] : $defaults['map_legend_position'],
			'show_search'         => ! empty( $input['show_search'] ) ? 1 : 0,
			'show_type_filter'    => ! empty( $input['show_type_filter'] ) ? 1 : 0,
			'show_country_filter' => ! empty( $input['show_country_filter'] ) ? 1 : 0,
			'show_region_filter'  => ! empty( $input['show_region_filter'] ) ? 1 : 0,
			'show_city_filter'    => ! empty( $input['show_city_filter'] ) ? 1 : 0,
			'show_directions_link' => ! empty( $input['show_directions_link'] ) ? 1 : 0,
			'list_width'          => self::clamp_int( $input['list_width'] ?? $defaults['list_width'], 260, 560 ),
			'gap'                 => self::clamp_int( $input['gap'] ?? $defaults['gap'], 0, 80 ),
			'filters_radius'      => self::clamp_int( $input['filters_radius'] ?? $defaults['filters_radius'], 0, 40 ),
			'filters_background_transparency' => self::clamp_int( $input['filters_background_transparency'] ?? $defaults['filters_background_transparency'], 0, 100 ),
			'card_radius'         => self::clamp_int( $input['card_radius'] ?? $defaults['card_radius'], 0, 40 ),
			'marker_image_id'     => self::sanitize_attachment_id( $input['marker_image_id'] ?? 0 ),
			'marker_color'        => sanitize_hex_color( $input['marker_color'] ?? '' ) ?: $defaults['marker_color'],
			'marker_size'         => self::clamp_int( $input['marker_size'] ?? $defaults['marker_size'], 16, 96 ),
			'popup_text_color'    => sanitize_hex_color( $input['popup_text_color'] ?? '' ) ?: $defaults['popup_text_color'],
			'popup_background'    => sanitize_hex_color( $input['popup_background'] ?? '' ) ?: $defaults['popup_background'],
			'popup_border_color'  => sanitize_hex_color( $input['popup_border_color'] ?? '' ) ?: $defaults['popup_border_color'],
			'popup_border_radius' => self::clamp_int( $input['popup_border_radius'] ?? $defaults['popup_border_radius'], 0, 40 ),
			'popup_width'         => self::clamp_int( $input['popup_width'] ?? $defaults['popup_width'], 180, 520 ),
			'delete_data_on_uninstall' => ! empty( $input['delete_data_on_uninstall'] ) ? 1 : 0,
		);

		return $settings;
	}

	/** Return location posts in manual order. */
	public static function get_locations( array $args = array() ): array {
		$query_args = array(
			'post_type'              => self::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => true,
			'update_post_meta_cache' => true,
		);

		if ( ! empty( $args['ids'] ) && is_array( $args['ids'] ) ) {
			$query_args['post__in'] = array_values( array_filter( array_map( 'absint', $args['ids'] ) ) );
		}

		$tax_query = array();

		if ( ! empty( $args['type_ids'] ) && is_array( $args['type_ids'] ) ) {
			$tax_query[] = array(
				'taxonomy' => self::TAXONOMY,
				'field'    => 'term_id',
				'terms'    => array_values( array_filter( array_map( 'absint', $args['type_ids'] ) ) ),
			);
		} elseif ( ! empty( $args['type_slugs'] ) && is_array( $args['type_slugs'] ) ) {
			$tax_query[] = array(
				'taxonomy' => self::TAXONOMY,
				'field'    => 'slug',
				'terms'    => array_values( array_filter( array_map( 'sanitize_title', $args['type_slugs'] ) ) ),
			);
		}

		if ( $tax_query ) {
			$query_args['tax_query'] = $tax_query;
		}

		$query = new \WP_Query( $query_args );

		return is_array( $query->posts ) ? $query->posts : array();
	}

	/** Return all location types in manual order. */
	public static function get_types(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		usort(
			$terms,
			static function ( \WP_Term $a, \WP_Term $b ): int {
				$order_a = (int) get_term_meta( $a->term_id, self::TERM_META_ORDER, true );
				$order_b = (int) get_term_meta( $b->term_id, self::TERM_META_ORDER, true );

				if ( $order_a === $order_b ) {
					return strcasecmp( $a->name, $b->name );
				}

				return $order_a <=> $order_b;
			}
		);

		return $terms;
	}

	/** Return the single type assigned through the Geo Maps UI. */
	public static function get_location_type( int $post_id ): ?\WP_Term {
		$terms = wp_get_object_terms( $post_id, self::TAXONOMY );

		if ( is_wp_error( $terms ) || empty( $terms ) || ! $terms[0] instanceof \WP_Term ) {
			return null;
		}

		return $terms[0];
	}

	/** Resolve marker inheritance for one location. */
	public static function resolve_marker( int $post_id = 0, int $type_id = 0 ): array {
		$global          = self::get_settings();
		$global_image_id = self::sanitize_attachment_id( $global['marker_image_id'] ?? 0 );
		$marker          = array(
			'image_id'  => $global_image_id,
			'image_url' => self::get_attachment_url( $global_image_id ),
			'svg'       => $global_image_id > 0 ? '' : self::sanitize_svg( $global['marker_svg'] ?? '' ),
			'color'     => $global['marker_color'],
			'size'      => (int) $global['marker_size'],
		);

		if ( $type_id > 0 ) {
			$type_image_id = self::sanitize_attachment_id( get_term_meta( $type_id, self::TERM_META_MARKER_IMAGE_ID, true ) );
			$type_svg      = (string) get_term_meta( $type_id, self::TERM_META_MARKER_SVG, true );
			$type_color    = (string) get_term_meta( $type_id, self::TERM_META_MARKER_COLOR, true );
			$type_size     = (int) get_term_meta( $type_id, self::TERM_META_MARKER_SIZE, true );

			if ( $type_image_id > 0 ) {
				$marker['image_id']  = $type_image_id;
				$marker['image_url'] = self::get_attachment_url( $type_image_id );
				$marker['svg']       = '';
			} elseif ( '' !== $type_svg ) {
				$marker['image_id']  = 0;
				$marker['image_url'] = '';
				$marker['svg']       = self::sanitize_svg( $type_svg );
			}

			if ( sanitize_hex_color( $type_color ) ) {
				$marker['color'] = sanitize_hex_color( $type_color );
			}

			if ( $type_size > 0 ) {
				$marker['size'] = self::clamp_int( $type_size, 16, 96 );
			}
		}

		if ( $post_id > 0 ) {
			$location_image_id = self::sanitize_attachment_id( get_post_meta( $post_id, self::META_MARKER_IMAGE_ID, true ) );
			$location_svg      = (string) get_post_meta( $post_id, self::META_MARKER_SVG, true );
			$location_color    = (string) get_post_meta( $post_id, self::META_MARKER_COLOR, true );
			$location_size     = (int) get_post_meta( $post_id, self::META_MARKER_SIZE, true );

			if ( $location_image_id > 0 ) {
				$marker['image_id']  = $location_image_id;
				$marker['image_url'] = self::get_attachment_url( $location_image_id );
				$marker['svg']       = '';
			} elseif ( '' !== $location_svg ) {
				$marker['image_id']  = 0;
				$marker['image_url'] = '';
				$marker['svg']       = self::sanitize_svg( $location_svg );
			}

			if ( sanitize_hex_color( $location_color ) ) {
				$marker['color'] = sanitize_hex_color( $location_color );
			}

			if ( $location_size > 0 ) {
				$marker['size'] = self::clamp_int( $location_size, 16, 96 );
			}
		}

		return $marker;
	}

	/** Normalize one location for the shared renderer. */
	public static function normalize_location( \WP_Post $post, bool $show_directions_link = true ): ?array {
		$latitude  = self::sanitize_coordinate( get_post_meta( $post->ID, self::META_LATITUDE, true ), -90, 90 );
		$longitude = self::sanitize_coordinate( get_post_meta( $post->ID, self::META_LONGITUDE, true ), -180, 180 );

		if ( null === $latitude || null === $longitude ) {
			return null;
		}

		$type        = self::get_location_type( $post->ID );
		$type_id     = $type ? (int) $type->term_id : 0;
		$marker      = self::resolve_marker( $post->ID, $type_id );
		$address     = (string) get_post_meta( $post->ID, self::META_ADDRESS, true );
		$city        = sanitize_text_field( (string) get_post_meta( $post->ID, self::META_CITY, true ) );
		$region      = sanitize_text_field( (string) get_post_meta( $post->ID, self::META_REGION, true ) );
		$country     = sanitize_text_field( (string) get_post_meta( $post->ID, self::META_COUNTRY, true ) );
		$phone       = (string) get_post_meta( $post->ID, self::META_PHONE, true );
		$email       = (string) get_post_meta( $post->ID, self::META_EMAIL, true );
		$website     = (string) get_post_meta( $post->ID, self::META_WEBSITE, true );
		$action            = (string) get_post_meta( $post->ID, self::META_ACTION, true );
		$popup             = (string) get_post_meta( $post->ID, self::META_POPUP_CONTENT, true );
		$popup_custom_meta = (string) get_post_meta( $post->ID, self::META_POPUP_CUSTOM, true );
		$popup_custom      = ( '1' === $popup_custom_meta || ( '' === $popup_custom_meta && '' !== trim( $popup ) ) ) && '' !== trim( $popup );

		if ( ! in_array( $action, array( 'none', 'link', 'popup' ), true ) ) {
			$action = 'popup';
		}

		$directions_url = $show_directions_link && '' !== trim( $address ) ? self::get_directions_url( $latitude, $longitude ) : '';

		if ( ! $popup_custom ) {
			$popup = '';
		}

		$search_text = implode(
			' ',
			array_filter(
				array(
					$post->post_title,
					$address,
					$city,
					$region,
					$country,
					$type ? $type->name : '',
				)
			)
		);

		return array(
			'id'          => (int) $post->ID,
			'title'       => $post->post_title,
			'address'     => $address,
			'city'        => $city,
			'region'      => $region,
			'country'     => $country,
			'latitude'    => $latitude,
			'longitude'   => $longitude,
			'phone'       => $phone,
			'email'       => sanitize_email( $email ),
			'website'     => esc_url_raw( $website ),
			'type_id'     => $type_id,
			'type_name'   => $type ? $type->name : '',
			'type_slug'   => $type ? $type->slug : '',
			'marker'      => $marker,
			'action'      => $action,
			'directions_url' => $directions_url,
			'popup_custom' => $popup_custom,
			'popup_html'  => wp_kses_post( $popup ),
			'search_text' => wp_strip_all_tags( $search_text ),
		);
	}

	/** Normalize one type for filters and grouped navigation. */
	public static function normalize_type( \WP_Term $term ): array {
		$type_id = (int) $term->term_id;

		return array(
			'id'     => $type_id,
			'name'   => $term->name,
			'slug'   => $term->slug,
			'marker' => self::resolve_marker( 0, $type_id ),
		);
	}

	/** Return the next location menu order. */
	public static function get_next_location_order(): int {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'orderby'        => 'menu_order',
				'order'          => 'DESC',
			)
		);

		if ( empty( $ids ) ) {
			return 0;
		}

		$post = get_post( (int) $ids[0] );
		return $post ? (int) $post->menu_order + 1 : 0;
	}

	/** Return the next type order. */
	public static function get_next_type_order(): int {
		$types = self::get_types();

		if ( empty( $types ) ) {
			return 0;
		}

		$last = end( $types );
		return $last instanceof \WP_Term ? (int) get_term_meta( $last->term_id, self::TERM_META_ORDER, true ) + 1 : 0;
	}


	/** Return a valid Media Library image attachment ID. */
	public static function sanitize_attachment_id( mixed $value ): int {
		$attachment_id = absint( $value );

		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
			return 0;
		}

		$mime = (string) get_post_mime_type( $attachment_id );

		return str_starts_with( strtolower( $mime ), 'image/' ) ? $attachment_id : 0;
	}

	/** Return the full URL for one valid image attachment. */
	public static function get_attachment_url( int $attachment_id ): string {
		$attachment_id = self::sanitize_attachment_id( $attachment_id );

		if ( $attachment_id <= 0 ) {
			return '';
		}

		$url = wp_get_attachment_url( $attachment_id );

		return is_string( $url ) ? esc_url_raw( $url ) : '';
	}

	/** Sanitize a limited inline SVG. */
	public static function sanitize_svg( mixed $svg ): string {
		$svg = trim( (string) $svg );

		if ( '' === $svg || strlen( $svg ) > 20000 || ! str_contains( strtolower( $svg ), '<svg' ) ) {
			return '';
		}

		if ( preg_match( '/(?:javascript:|data:|url\s*\()/i', $svg ) ) {
			return '';
		}

		$allowed = array(
			'svg' => array(
				'xmlns'       => true,
				'viewbox'     => true,
				'viewBox'     => true,
				'width'       => true,
				'height'      => true,
				'fill'        => true,
				'stroke'      => true,
				'aria-hidden' => true,
				'role'        => true,
				'focusable'   => true,
			),
			'g' => array(
				'fill'      => true,
				'stroke'    => true,
				'transform' => true,
			),
			'path' => array(
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'fill-rule'       => true,
				'clip-rule'       => true,
				'transform'       => true,
			),
			'circle' => array(
				'cx'           => true,
				'cy'           => true,
				'r'            => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'ellipse' => array(
				'cx'           => true,
				'cy'           => true,
				'rx'           => true,
				'ry'           => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'rect' => array(
				'x'            => true,
				'y'            => true,
				'width'        => true,
				'height'       => true,
				'rx'           => true,
				'ry'           => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'transform'    => true,
			),
			'line' => array(
				'x1'           => true,
				'y1'           => true,
				'x2'           => true,
				'y2'           => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'polyline' => array(
				'points'       => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'polygon' => array(
				'points'       => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
		);

		$sanitized = wp_kses( $svg, $allowed );

		if ( ! preg_match( '/<svg\b[^>]*>.*<\/svg>/is', $sanitized ) ) {
			return '';
		}

		return $sanitized;
	}

	/** Clamp an integer value. */
	public static function clamp_int( mixed $value, int $min, int $max ): int {
		$value = (int) $value;
		return max( $min, min( $max, $value ) );
	}

	/** Sanitize a decimal coordinate. */
	public static function sanitize_coordinate( mixed $value, float $min, float $max ): ?float {
		if ( is_string( $value ) ) {
			$value = str_replace( ',', '.', trim( $value ) );
		}

		if ( '' === $value || null === $value || ! is_numeric( $value ) ) {
			return null;
		}

		$value = (float) $value;

		if ( $value < $min || $value > $max ) {
			return null;
		}

		return $value;
	}

	/** Build the official Google Maps directions URL for normalized coordinates. */
	public static function get_directions_url( mixed $latitude, mixed $longitude ): string {
		$latitude  = self::sanitize_coordinate( $latitude, -90, 90 );
		$longitude = self::sanitize_coordinate( $longitude, -180, 180 );

		if ( null === $latitude || null === $longitude ) {
			return '';
		}

		return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( (string) $latitude ) . ',' . rawurlencode( (string) $longitude );
	}

}
