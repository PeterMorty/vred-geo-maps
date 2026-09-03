<?php
/**
 * Shared shortcode renderer.
 *
 * @package VRED_Geo_Maps
 */

namespace VRED\GeoMaps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Renderer {
	private const LEAFLET_VERSION = '1.9.4';
	private const CLUSTER_VERSION = '1.5.3';

	// Share the VRED Leaflet handle to avoid loading two global Leaflet instances when VRED Elements is active.
	private const LEAFLET_STYLE = 'vred-elements-leaflet';
	private const LEAFLET_SCRIPT = 'vred-elements-leaflet';
	private const CLUSTER_STYLE = 'vred-geo-maps-cluster';
	private const CLUSTER_DEFAULT_STYLE = 'vred-geo-maps-cluster-default';
	private const CLUSTER_SCRIPT = 'vred-geo-maps-cluster';
	private const FRONTEND_STYLE = 'vred-geo-maps-frontend';
	private const FRONTEND_SCRIPT = 'vred-geo-maps-frontend';

	private static int $instance_counter = 0;

	/** Register reusable assets without globally enqueuing them. */
	public static function register_assets(): void {
		if ( ! wp_style_is( self::LEAFLET_STYLE, 'registered' ) ) {
			wp_register_style(
				self::LEAFLET_STYLE,
				VRED_GEO_MAPS_URL . 'assets/vendor/leaflet/leaflet.css',
				array(),
				self::LEAFLET_VERSION
			);
		}

		if ( ! wp_script_is( self::LEAFLET_SCRIPT, 'registered' ) ) {
			wp_register_script(
				self::LEAFLET_SCRIPT,
				VRED_GEO_MAPS_URL . 'assets/vendor/leaflet/leaflet.js',
				array(),
				self::LEAFLET_VERSION,
				true
			);
		}

		if ( ! wp_style_is( self::CLUSTER_STYLE, 'registered' ) ) {
			wp_register_style(
				self::CLUSTER_STYLE,
				VRED_GEO_MAPS_URL . 'assets/vendor/leaflet-markercluster/MarkerCluster.css',
				array( self::LEAFLET_STYLE ),
				self::CLUSTER_VERSION
			);
		}

		if ( ! wp_style_is( self::CLUSTER_DEFAULT_STYLE, 'registered' ) ) {
			wp_register_style(
				self::CLUSTER_DEFAULT_STYLE,
				VRED_GEO_MAPS_URL . 'assets/vendor/leaflet-markercluster/MarkerCluster.Default.css',
				array( self::CLUSTER_STYLE ),
				self::CLUSTER_VERSION
			);
		}

		if ( ! wp_script_is( self::CLUSTER_SCRIPT, 'registered' ) ) {
			wp_register_script(
				self::CLUSTER_SCRIPT,
				VRED_GEO_MAPS_URL . 'assets/vendor/leaflet-markercluster/leaflet.markercluster.js',
				array( self::LEAFLET_SCRIPT ),
				self::CLUSTER_VERSION,
				true
			);
		}

		if ( ! wp_style_is( self::FRONTEND_STYLE, 'registered' ) ) {
			wp_register_style(
				self::FRONTEND_STYLE,
				VRED_GEO_MAPS_URL . 'assets/css/frontend.css',
				array( self::LEAFLET_STYLE, 'dashicons' ),
				VRED_GEO_MAPS_VERSION
			);
		}

		if ( ! wp_script_is( self::FRONTEND_SCRIPT, 'registered' ) ) {
			wp_register_script(
				self::FRONTEND_SCRIPT,
				VRED_GEO_MAPS_URL . 'assets/js/frontend.js',
				array( self::LEAFLET_SCRIPT ),
				VRED_GEO_MAPS_VERSION,
				true
			);
		}
	}

	/** Enqueue only assets needed by an actual map consumer. */
	public static function enqueue_assets( bool $clustering = true ): void {
		self::register_assets();

		wp_enqueue_style( self::LEAFLET_STYLE );
		wp_enqueue_style( self::FRONTEND_STYLE );
		wp_enqueue_script( self::LEAFLET_SCRIPT );

		if ( $clustering ) {
			wp_enqueue_style( self::CLUSTER_STYLE );
			wp_enqueue_style( self::CLUSTER_DEFAULT_STYLE );
			wp_enqueue_script( self::CLUSTER_SCRIPT );
		}

		wp_enqueue_script( self::FRONTEND_SCRIPT );
	}

	/** Render one map instance. */
	public static function render( array $query = array(), array $overrides = array() ): string {
		$settings = self::get_effective_settings( $overrides );
		$posts    = Data::get_locations( $query );

		$locations = array();

		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$location = Data::normalize_location( $post, ! empty( $settings['show_directions_link'] ) );

			if ( null !== $location ) {
				$locations[] = $location;
			}
		}

		if ( empty( $locations ) ) {
			return '<div class="vred-geo-maps-empty">' . esc_html__( 'No locations with valid coordinates were found.', 'vred-geo-maps' ) . '</div>';
		}

		self::enqueue_assets( ! empty( $settings['clustering'] ) );
		self::$instance_counter++;
		$instance_id = 'vred-geo-map-' . self::$instance_counter;

		$types_by_id = array();

		foreach ( $locations as $location ) {
			$type_id = (int) $location['type_id'];

			if ( $type_id <= 0 || isset( $types_by_id[ $type_id ] ) ) {
				continue;
			}

			$term = get_term( $type_id, Data::TAXONOMY );

			if ( $term instanceof \WP_Term ) {
				$types_by_id[ $type_id ] = Data::normalize_type( $term );
			}
		}

		$types = array_values( $types_by_id );
		usort(
			$types,
			static function ( array $a, array $b ): int {
				$order_a = (int) get_term_meta( $a['id'], Data::TERM_META_ORDER, true );
				$order_b = (int) get_term_meta( $b['id'], Data::TERM_META_ORDER, true );
				return $order_a === $order_b ? strcasecmp( $a['name'], $b['name'] ) : $order_a <=> $order_b;
			}
		);

		$show_country_filter = ! empty( $settings['show_country_filter'] ) && self::has_location_value( $locations, 'country' );
		$show_region_filter  = ! empty( $settings['show_region_filter'] ) && self::has_location_value( $locations, 'region' );
		$show_city_filter    = ! empty( $settings['show_city_filter'] ) && self::has_location_value( $locations, 'city' );
		$show_filters        = ! empty( $settings['show_filters'] ) && ( ! empty( $settings['show_search'] ) || ( ! empty( $settings['show_type_filter'] ) && ! empty( $types ) ) || $show_country_filter || $show_region_filter || $show_city_filter );
		$filters_position    = $settings['filters_position'];
		$has_panel           = ! empty( $settings['show_list'] ) || ( $show_filters && 'panel' === $filters_position );
		$has_map_overlays    = ( $show_filters && 'map' === $filters_position ) || ! empty( $settings['show_map_legend'] );
		$carto_api_key       = trim( (string) $settings['carto_api_key'] );
		$uses_carto          = in_array( $settings['tile_provider'], array( 'carto_positron', 'carto_positron_nolabels', 'carto_voyager' ), true );
		$tile_provider       = $uses_carto && '' === $carto_api_key ? 'openstreetmap' : $settings['tile_provider'];

		$config = array(
			'id'           => $instance_id,
			'tileProvider' => $tile_provider,
			'appearance'   => $settings['appearance'],
			'zoom'         => (int) $settings['initial_zoom'],
			'popupWidth'   => (int) $settings['popup_width'],
			'autoFit'      => ! empty( $settings['auto_fit'] ),
			'clustering'   => ! empty( $settings['clustering'] ),
			'locations'    => array_map(
				static fn( array $location ): array => self::get_js_location( $location, $settings['list_type_indicator'] ),
				$locations
			),
			'strings'      => array(
				'clusterUnavailable' => __( 'VRED Geo Maps: marker clustering is enabled but Leaflet.markercluster is unavailable.', 'vred-geo-maps' ),
				'viewAll'             => __( 'View all (%d)', 'vred-geo-maps' ),
				'showLess'            => __( 'Show less', 'vred-geo-maps' ),
				'expandLegend'         => __( 'Expand map legend', 'vred-geo-maps' ),
				'collapseLegend'       => __( 'Collapse map legend', 'vred-geo-maps' ),
				'expandGroup'          => __( 'Expand %s', 'vred-geo-maps' ),
				'collapseGroup'        => __( 'Collapse %s', 'vred-geo-maps' ),
				'useMyLocation'        => __( 'Use my location', 'vred-geo-maps' ),
				'locationUnavailable'  => __( 'Your location could not be determined.', 'vred-geo-maps' ),
			),
		);

		if ( $uses_carto && '' !== $carto_api_key ) {
			$config['cartoApiKey'] = $carto_api_key;
		}

		$styles = self::build_style_attribute( $settings );
		$classes = array(
			'vred-geo-maps',
			'vred-geo-maps--' . sanitize_html_class( $settings['list_position'] ),
			'vred-geo-maps--list-' . sanitize_html_class( $settings['list_style'] ),
			'vred-geo-maps--filters-' . sanitize_html_class( $filters_position ),
			'vred-geo-maps--appearance-' . sanitize_html_class( $settings['appearance'] ),
		);

		if ( empty( $settings['show_list'] ) ) {
			$classes[] = 'vred-geo-maps--no-list';
		}

		if ( ! $has_panel ) {
			$classes[] = 'vred-geo-maps--no-panel';
		}

		ob_start();
		?>
		<section id="<?php echo esc_attr( $instance_id ); ?>" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="<?php echo esc_attr( $styles ); ?>" data-vred-geo-maps>
			<div class="vred-geo-maps__content">
				<?php if ( $has_panel ) : ?>
					<div class="vred-geo-maps__panel">
						<?php if ( $show_filters && 'panel' === $filters_position ) : ?>
							<?php self::render_filters( $types, $settings, $show_country_filter, $show_region_filter, $show_city_filter, 'panel' ); ?>
						<?php endif; ?>
						<?php if ( ! empty( $settings['show_list'] ) ) : ?>
							<div class="vred-geo-maps__list" data-vred-geo-list>
								<?php self::render_locations_list( $locations, $types, $settings['list_style'], $settings['list_position'], $settings['list_type_indicator'] ); ?>
								<p class="vred-geo-maps__no-results" data-vred-geo-no-results hidden><?php esc_html_e( 'No locations match the filters.', 'vred-geo-maps' ); ?></p>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="vred-geo-maps__map-zone">
					<?php if ( $show_filters && 'top' === $filters_position ) : ?>
						<?php self::render_filters( $types, $settings, $show_country_filter, $show_region_filter, $show_city_filter ); ?>
					<?php endif; ?>
					<div class="vred-geo-maps__map-wrap">
						<div class="vred-geo-maps__map" data-vred-geo-canvas aria-label="<?php echo esc_attr__( 'Interactive locations map', 'vred-geo-maps' ); ?>"></div>
						<?php if ( $has_map_overlays ) : ?>
							<?php foreach ( array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' ) as $overlay_position ) : ?>
								<?php $has_overlay_slot = ( $show_filters && 'map' === $filters_position && $settings['filters_map_position'] === $overlay_position ) || ( ! empty( $settings['show_map_legend'] ) && $settings['map_legend_position'] === $overlay_position ); ?>
								<?php if ( $has_overlay_slot ) : ?>
									<div class="vred-geo-maps__overlay-slot vred-geo-maps__overlay-slot--<?php echo esc_attr( $overlay_position ); ?>" data-vred-geo-overlay-slot data-position="<?php echo esc_attr( $overlay_position ); ?>">
										<?php if ( $show_filters && 'map' === $filters_position && $settings['filters_map_position'] === $overlay_position ) : ?>
											<?php self::render_filters( $types, $settings, $show_country_filter, $show_region_filter, $show_city_filter, 'map' ); ?>
										<?php endif; ?>
										<?php if ( ! empty( $settings['show_map_legend'] ) && $settings['map_legend_position'] === $overlay_position ) : ?>
											<?php self::render_map_legend( $locations, $types, $settings ); ?>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
					<?php if ( $show_filters && 'bottom' === $filters_position ) : ?>
						<?php self::render_filters( $types, $settings, $show_country_filter, $show_region_filter, $show_city_filter ); ?>
					<?php endif; ?>
				</div>
			</div>

			<script type="application/json" data-vred-geo-config><?php echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/** Merge global settings with shortcode overrides. */
	private static function get_effective_settings( array $overrides ): array {
		$settings = Data::get_settings();
		$keys = array(
			'tile_provider',
			'carto_api_key',
			'theme_color',
			'appearance',
			'map_height',
			'map_height_unit',
			'map_height_custom',
			'map_border_radius',
			'initial_zoom',
			'auto_fit',
			'clustering',
			'show_list',
			'list_style',
			'list_type_indicator',
			'map_legend_type_indicator',
			'map_legend_visible_locations_per_type',
			'map_legend_border_radius',
			'map_legend_background_transparency',
			'list_position',
			'filters_position',
			'filters_map_position',
			'show_filters',
			'show_map_legend',
			'map_legend_position',
			'show_search',
			'show_type_filter',
			'show_country_filter',
			'show_region_filter',
			'show_city_filter',
			'show_directions_link',
			'list_width',
			'gap',
			'filters_radius',
			'filters_background_transparency',
			'card_radius',
			'popup_text_color',
			'popup_background',
			'popup_border_color',
			'popup_border_radius',
			'popup_width',
		);

		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $overrides ) && '' !== $overrides[ $key ] && null !== $overrides[ $key ] && 'inherit' !== $overrides[ $key ] ) {
				$settings[ $key ] = $overrides[ $key ];
			}
		}

		$settings['list_style']          = in_array( $settings['list_style'], array( 'cards', 'compact', 'legend', 'grouped' ), true ) ? $settings['list_style'] : 'cards';
		$settings['list_type_indicator'] = in_array( $settings['list_type_indicator'], array( 'auto', 'icon', 'color' ), true ) ? $settings['list_type_indicator'] : 'auto';
		$settings['map_legend_type_indicator'] = in_array( $settings['map_legend_type_indicator'], array( 'auto', 'icon', 'color' ), true ) ? $settings['map_legend_type_indicator'] : 'auto';
		$settings['map_legend_visible_locations_per_type'] = Data::clamp_int( $settings['map_legend_visible_locations_per_type'], 1, 20 );
		$settings['map_legend_border_radius'] = Data::clamp_int( $settings['map_legend_border_radius'], 0, 40 );
		$settings['map_legend_background_transparency'] = Data::clamp_int( $settings['map_legend_background_transparency'], 0, 100 );
		$settings['filters_position']    = in_array( $settings['filters_position'], array( 'top', 'panel', 'bottom', 'map' ), true ) ? $settings['filters_position'] : 'top';
		$settings['filters_map_position'] = in_array( $settings['filters_map_position'], array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' ), true ) ? $settings['filters_map_position'] : 'top-right';
		$settings['map_legend_position'] = in_array( $settings['map_legend_position'], array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' ), true ) ? $settings['map_legend_position'] : 'top-right';
		$settings['map_height_unit']     = in_array( $settings['map_height_unit'], array( 'px', 'vh', 'dvh', 'custom' ), true ) ? $settings['map_height_unit'] : 'px';
		$map_height_min                 = in_array( $settings['map_height_unit'], array( 'vh', 'dvh' ), true ) ? 1 : 240;
		$settings['map_height']          = Data::clamp_int( $settings['map_height'], $map_height_min, 900 );
		$settings['map_height_custom']   = Data::sanitize_css_length_expression( $settings['map_height_custom'] );
		$settings['map_border_radius']   = Data::clamp_int( $settings['map_border_radius'], 0, 40 );
		$settings['initial_zoom']        = Data::clamp_int( $settings['initial_zoom'], 1, 19 );
		$settings['list_width']          = Data::clamp_int( $settings['list_width'], 260, 560 );
		$settings['gap']                 = Data::clamp_int( $settings['gap'], 0, 80 );
		$settings['filters_radius']        = Data::clamp_int( $settings['filters_radius'], 0, 40 );
		$settings['filters_background_transparency'] = Data::clamp_int( $settings['filters_background_transparency'], 0, 100 );
		$settings['card_radius']         = Data::clamp_int( $settings['card_radius'], 0, 40 );
		$settings['popup_border_radius'] = Data::clamp_int( $settings['popup_border_radius'], 0, 40 );
		$settings['popup_width']         = Data::clamp_int( $settings['popup_width'], 180, 520 );

		foreach ( array( 'theme_color', 'popup_text_color', 'popup_background', 'popup_border_color' ) as $color_key ) {
			$settings[ $color_key ] = sanitize_hex_color( $settings[ $color_key ] ) ?: Data::get_default_settings()[ $color_key ];
		}

		return $settings;
	}

	/** Build scoped CSS variables for one instance. */
	private static function build_style_attribute( array $settings ): string {
		$filters_background_alpha = number_format( 1 - ( (int) $settings['filters_background_transparency'] / 100 ), 2, '.', '' );
		$legend_background_alpha  = number_format( 1 - ( (int) $settings['map_legend_background_transparency'] / 100 ), 2, '.', '' );
		$map_height                = (int) $settings['map_height'] . $settings['map_height_unit'];

		if ( 'custom' === $settings['map_height_unit'] ) {
			$map_height = '' !== $settings['map_height_custom'] ? $settings['map_height_custom'] : (int) $settings['map_height'] . 'px';
		}

		return implode(
			';',
			array(
				'--vred-geo-map-height:' . $map_height,
				'--vred-geo-map-radius:' . (int) $settings['map_border_radius'] . 'px',
				'--vred-geo-theme-color:' . $settings['theme_color'],
				'--vred-geo-list-width:' . (int) $settings['list_width'] . 'px',
				'--vred-geo-gap:' . (int) $settings['gap'] . 'px',
				'--vred-geo-filters-radius:' . (int) $settings['filters_radius'] . 'px',
				'--vred-geo-filters-background:rgba(255,255,255,' . $filters_background_alpha . ')',
				'--vred-geo-map-legend-radius:' . (int) $settings['map_legend_border_radius'] . 'px',
				'--vred-geo-map-legend-background:rgba(255,255,255,' . $legend_background_alpha . ')',
				'--vred-geo-card-radius:' . (int) $settings['card_radius'] . 'px',
				'--vred-geo-popup-text:' . $settings['popup_text_color'],
				'--vred-geo-popup-bg:' . $settings['popup_background'],
				'--vred-geo-popup-border:' . $settings['popup_border_color'],
				'--vred-geo-popup-radius:' . (int) $settings['popup_border_radius'] . 'px',
			)
		);
	}

	/** Render the single configured filters block. */
	private static function render_filters( array $types, array $settings, bool $show_country_filter, bool $show_region_filter, bool $show_city_filter, string $context = 'flow' ): void {
		$show_type_filter      = ! empty( $settings['show_type_filter'] ) && ! empty( $types );
		$has_secondary_filters = $show_type_filter || $show_country_filter || $show_region_filter || $show_city_filter;
		$classes               = 'vred-geo-maps__filters vred-geo-maps__filters--' . sanitize_html_class( $context );
		$secondary_id          = 'map' === $context && $has_secondary_filters ? wp_unique_id( 'vred-geo-filter-options-' ) : '';

		if ( $has_secondary_filters ) {
			$classes .= ' has-secondary-filters';
		}

		if ( ! empty( $settings['show_search'] ) ) {
			$classes .= ' has-search';
		}
		?>
		<div class="<?php echo esc_attr( $classes ); ?>"<?php echo 'map' === $context ? ' data-vred-geo-overlay-block data-vred-geo-map-filters' : ''; ?>>
			<div class="vred-geo-maps__filter-controls">
				<?php if ( ! empty( $settings['show_search'] ) ) : ?>
					<label class="vred-geo-maps__field vred-geo-maps__field--search">
						<span><?php esc_html_e( 'Search', 'vred-geo-maps' ); ?></span>
						<input type="search" placeholder="<?php echo esc_attr__( 'Search by name or address…', 'vred-geo-maps' ); ?>" data-vred-geo-search>
					</label>
				<?php endif; ?>
				<?php if ( 'map' === $context && $has_secondary_filters ) : ?>
					<button type="button" class="vred-geo-maps__action vred-geo-maps__filter-toggle" data-vred-geo-filter-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $secondary_id ); ?>"><?php esc_html_e( 'Filters', 'vred-geo-maps' ); ?></button>
				<?php endif; ?>
				<div class="vred-geo-maps__filter-secondary"<?php echo '' !== $secondary_id ? ' id="' . esc_attr( $secondary_id ) . '"' : ''; ?>>
				<?php if ( $show_type_filter ) : ?>
					<label class="vred-geo-maps__field vred-geo-maps__field--secondary">
						<span><?php esc_html_e( 'Location type', 'vred-geo-maps' ); ?></span>
						<select data-vred-geo-type-filter>
							<option value=""><?php esc_html_e( 'All types', 'vred-geo-maps' ); ?></option>
							<?php foreach ( $types as $type ) : ?>
								<option value="<?php echo esc_attr( (string) $type['id'] ); ?>"><?php echo esc_html( $type['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>
				<?php if ( $show_country_filter ) : ?>
					<label class="vred-geo-maps__field vred-geo-maps__field--secondary">
						<span><?php esc_html_e( 'Country', 'vred-geo-maps' ); ?></span>
						<select data-vred-geo-country-filter>
							<option value=""><?php esc_html_e( 'All countries', 'vred-geo-maps' ); ?></option>
						</select>
					</label>
				<?php endif; ?>
				<?php if ( $show_region_filter ) : ?>
					<label class="vred-geo-maps__field vred-geo-maps__field--secondary">
						<span><?php esc_html_e( 'Province / region', 'vred-geo-maps' ); ?></span>
						<select data-vred-geo-region-filter>
							<option value=""><?php esc_html_e( 'All provinces / regions', 'vred-geo-maps' ); ?></option>
						</select>
					</label>
				<?php endif; ?>
				<?php if ( $show_city_filter ) : ?>
					<label class="vred-geo-maps__field vred-geo-maps__field--secondary">
						<span><?php esc_html_e( 'City', 'vred-geo-maps' ); ?></span>
						<select data-vred-geo-city-filter>
							<option value=""><?php esc_html_e( 'All cities', 'vred-geo-maps' ); ?></option>
						</select>
					</label>
				<?php endif; ?>
					<div class="vred-geo-maps__filter-meta">
						<button type="button" class="vred-geo-maps__action vred-geo-maps__reset" data-vred-geo-reset>
							<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
								<path d="M3 12a9 9 0 1 0 3-6.7L3 8"></path>
								<path d="M3 3v5h5"></path>
							</svg>
							<span><?php esc_html_e( 'Clear filters', 'vred-geo-maps' ); ?></span>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/** Render the independent Location Types legend shown over the map. */
	private static function render_map_legend( array $locations, array $types, array $settings ): void {
		$groups         = self::get_location_type_groups( $locations, $types );
		$type_indicator = $settings['map_legend_type_indicator'];
		$visible_limit  = (int) $settings['map_legend_visible_locations_per_type'];
		$body_id        = wp_unique_id( 'vred-geo-map-legend-' );
		?>
		<aside class="vred-geo-maps__map-legend" data-vred-geo-overlay-block data-vred-geo-map-legend data-visible-limit="<?php echo esc_attr( (string) $visible_limit ); ?>" aria-label="<?php echo esc_attr__( 'Location Types', 'vred-geo-maps' ); ?>">
			<div class="vred-geo-maps__map-legend-header">
				<strong class="vred-geo-maps__map-legend-title"><?php esc_html_e( 'Location Types', 'vred-geo-maps' ); ?></strong>
				<button type="button" class="vred-geo-maps__action vred-geo-maps__map-legend-toggle" data-vred-geo-map-legend-toggle aria-expanded="true" aria-controls="<?php echo esc_attr( $body_id ); ?>" aria-label="<?php echo esc_attr__( 'Collapse map legend', 'vred-geo-maps' ); ?>">
					<span aria-hidden="true">&minus;</span>
				</button>
			</div>
			<div id="<?php echo esc_attr( $body_id ); ?>" class="vred-geo-maps__map-legend-body" data-vred-geo-map-legend-body aria-live="polite">
				<?php foreach ( $groups as $group ) : ?>
					<?php if ( ! empty( $group['locations'] ) ) : ?>
						<details class="vred-geo-maps__map-legend-group" data-vred-geo-map-legend-group data-type-id="<?php echo esc_attr( (string) $group['id'] ); ?>" open>
							<summary class="vred-geo-maps__action vred-geo-maps__map-legend-summary" aria-label="<?php echo esc_attr( sprintf( __( 'Collapse %s', 'vred-geo-maps' ), $group['name'] ) ); ?>">
								<span class="vred-geo-maps__legend-heading">
									<?php self::render_type_indicator( $group, $type_indicator ); ?>
									<strong><?php echo esc_html( $group['name'] ); ?></strong>
								</span>
								<span class="vred-geo-maps__map-legend-meta">
									<span>(<span data-vred-geo-map-legend-count><?php echo esc_html( (string) count( $group['locations'] ) ); ?></span>)</span>
									<span class="vred-geo-maps__map-legend-chevron" aria-hidden="true"></span>
								</span>
							</summary>
							<div class="vred-geo-maps__map-legend-locations">
								<?php foreach ( $group['locations'] as $location_index => $location ) : ?>
									<button type="button" class="vred-geo-maps__action vred-geo-maps__map-legend-location" data-vred-geo-location-item data-vred-geo-map-legend-location data-location-id="<?php echo esc_attr( (string) $location['id'] ); ?>" data-type-id="<?php echo esc_attr( (string) $location['type_id'] ); ?>" data-vred-geo-location-select<?php echo $location_index >= $visible_limit ? ' hidden' : ''; ?>><?php echo esc_html( $location['title'] ); ?></button>
								<?php endforeach; ?>
							</div>
							<button type="button" class="vred-geo-maps__action vred-geo-maps__map-legend-limit" data-vred-geo-map-legend-limit aria-expanded="false"<?php echo count( $group['locations'] ) > $visible_limit ? '' : ' hidden'; ?>><?php echo esc_html( sprintf( __( 'View all (%d)', 'vred-geo-maps' ), count( $group['locations'] ) ) ); ?></button>
						</details>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</aside>
		<?php
	}

	/** Render the configured locations list style. */
	private static function render_locations_list( array $locations, array $types, string $list_style, string $list_position, string $type_indicator ): void {
		if ( in_array( $list_style, array( 'legend', 'grouped' ), true ) ) {
			self::render_grouped_list( $locations, $types, $list_position, $type_indicator, 'grouped' === $list_style );
			return;
		}

		foreach ( $locations as $location ) {
			if ( 'compact' === $list_style ) {
				self::render_compact_location( $location, $type_indicator );
				continue;
			}

			self::render_location_card( $location, $type_indicator );
		}
	}

	/** Render one card-style location item. */
	private static function render_location_card( array $location, string $type_indicator ): void {
		$has_details       = self::location_has_metadata( $location );
		$indicator_variant = self::get_type_indicator_variant( $location, $type_indicator );
		$card_classes      = 'vred-geo-maps__card vred-geo-maps__card--indicator-' . $indicator_variant . ( $has_details ? ' has-details' : '' );
		?>
		<?php if ( $has_details ) : ?>
			<details class="<?php echo esc_attr( $card_classes ); ?>" <?php self::render_location_item_attributes( $location ); ?>>
				<summary class="vred-geo-maps__action vred-geo-maps__card-summary" data-vred-geo-location-select>
					<?php self::render_location_identity( $location, $type_indicator, 'card' ); ?>
					<span class="vred-geo-maps__card-chevron" aria-hidden="true"></span>
				</summary>
				<?php self::render_location_metadata( $location ); ?>
			</details>
		<?php else : ?>
			<article class="<?php echo esc_attr( $card_classes ); ?>" <?php self::render_location_item_attributes( $location ); ?>>
				<a href="#" class="vred-geo-maps__action vred-geo-maps__card-summary" data-vred-geo-location-select>
					<?php self::render_location_identity( $location, $type_indicator, 'card' ); ?>
				</a>
			</article>
		<?php endif; ?>
		<?php
	}

	/** Render one compact location row. */
	private static function render_compact_location( array $location, string $type_indicator ): void {
		?>
		<article class="vred-geo-maps__compact-item" <?php self::render_location_item_attributes( $location ); ?>>
			<a href="#" class="vred-geo-maps__action vred-geo-maps__compact-link" data-vred-geo-location-select>
				<?php self::render_location_identity( $location, $type_indicator, 'compact' ); ?>
			</a>
			<?php if ( '' !== $location['directions_url'] ) : ?>
				<?php self::render_directions_link( $location['directions_url'], 'vred-geo-maps__directions-link--compact' ); ?>
			<?php endif; ?>
		</article>
		<?php
	}

	/** Render locations grouped by Location Type. */
	private static function render_grouped_list( array $locations, array $types, string $list_position, string $type_indicator, bool $show_addresses ): void {
		$groups = self::get_location_type_groups( $locations, $types );

		$group_index   = 0;
		$static_groups = in_array( $list_position, array( 'top', 'bottom' ), true );

		foreach ( $groups as $group ) {
			if ( empty( $group['locations'] ) ) {
				continue;
			}

			$group_index++;
			?>
			<?php if ( $static_groups ) : ?>
				<div class="vred-geo-maps__legend-group is-static<?php echo $show_addresses ? ' is-detailed' : ''; ?>" data-vred-geo-legend-group data-type-id="<?php echo esc_attr( (string) $group['id'] ); ?>">
					<div class="vred-geo-maps__legend-summary">
						<?php self::render_group_heading( $group, $type_indicator ); ?>
					</div>
			<?php else : ?>
				<details class="vred-geo-maps__legend-group<?php echo $show_addresses ? ' is-detailed' : ''; ?>" data-vred-geo-legend-group data-type-id="<?php echo esc_attr( (string) $group['id'] ); ?>"<?php echo 1 === $group_index ? ' open' : ''; ?>>
					<summary class="vred-geo-maps__action vred-geo-maps__legend-summary">
						<?php self::render_group_heading( $group, $type_indicator, true ); ?>
					</summary>
			<?php endif; ?>
				<div class="vred-geo-maps__legend-items">
					<?php foreach ( $group['locations'] as $location ) : ?>
						<?php if ( '' !== $location['directions_url'] ) : ?>
							<div class="vred-geo-maps__legend-location" <?php self::render_location_item_attributes( $location ); ?>>
								<a href="#" class="vred-geo-maps__action vred-geo-maps__legend-item<?php echo $show_addresses ? ' vred-geo-maps__legend-item--detailed' : ''; ?>" data-vred-geo-location-select>
									<?php self::render_grouped_location_content( $location, $show_addresses ); ?>
								</a>
								<?php self::render_directions_link( $location['directions_url'], 'vred-geo-maps__directions-link--legend' ); ?>
							</div>
						<?php else : ?>
							<a href="#" class="vred-geo-maps__action vred-geo-maps__legend-item<?php echo $show_addresses ? ' vred-geo-maps__legend-item--detailed' : ''; ?>" <?php self::render_location_item_attributes( $location ); ?> data-vred-geo-location-select>
								<?php self::render_grouped_location_content( $location, $show_addresses ); ?>
							</a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php if ( $static_groups ) : ?>
				</div>
			<?php else : ?>
				</details>
			<?php endif; ?>
			<?php
		}
	}

	/** Build ordered Location Type groups shared by lists and the map legend. */
	private static function get_location_type_groups( array $locations, array $types ): array {
		$groups = array();

		foreach ( $types as $type ) {
			$groups[ (int) $type['id'] ] = array(
				'id'        => (int) $type['id'],
				'name'      => $type['name'],
				'color'     => sanitize_hex_color( $type['marker']['color'] ?? '' ) ?: '#2f6fed',
				'marker'    => $type['marker'],
				'locations' => array(),
			);
		}

		$untyped = array();

		foreach ( $locations as $location ) {
			$type_id = (int) $location['type_id'];

			if ( $type_id > 0 && isset( $groups[ $type_id ] ) ) {
				$groups[ $type_id ]['locations'][] = $location;
				continue;
			}

			$untyped[] = $location;
		}

		if ( $untyped ) {
			$groups[0] = array(
				'id'        => 0,
				'name'      => __( 'Other locations', 'vred-geo-maps' ),
				'color'     => sanitize_hex_color( $untyped[0]['marker']['color'] ?? '' ) ?: '#2f6fed',
				'marker'    => $untyped[0]['marker'],
				'locations' => $untyped,
			);
		}

		return $groups;
	}

	/** Render one grouped list heading. */
	private static function render_group_heading( array $group, string $type_indicator, bool $show_toggle = false ): void {
		?>
		<span class="vred-geo-maps__legend-heading">
			<?php self::render_type_indicator( $group, $type_indicator ); ?>
			<strong><?php echo esc_html( $group['name'] ); ?></strong>
		</span>
		<span class="vred-geo-maps__legend-meta">
			<span class="vred-geo-maps__legend-count" data-vred-geo-legend-count><?php echo esc_html( (string) count( $group['locations'] ) ); ?></span>
			<?php if ( $show_toggle ) : ?>
				<span class="vred-geo-maps__legend-toggle" aria-hidden="true"></span>
			<?php endif; ?>
		</span>
		<?php
	}

	/** Render the shared title and optional address for grouped list items. */
	private static function render_grouped_location_content( array $location, bool $show_address ): void {
		?>
		<span class="vred-geo-maps__legend-item-title"><?php echo esc_html( $location['title'] ); ?></span>
		<?php if ( $show_address && '' !== $location['address'] ) : ?>
			<span class="vred-geo-maps__legend-item-address"><?php echo esc_html( $location['address'] ); ?></span>
		<?php endif; ?>
		<?php
	}

	/** Render a type indicator from a resolved marker payload. */
	private static function render_type_indicator( array $visual, string $type_indicator, string $context = 'legend' ): void {
		$marker      = is_array( $visual['marker'] ?? null ) ? $visual['marker'] : array();
		$marker_url  = esc_url_raw( $marker['image_url'] ?? '' );
		$marker_svg  = Data::sanitize_svg( $marker['svg'] ?? '' );
		$color       = sanitize_hex_color( $marker['color'] ?? ( $visual['color'] ?? '' ) ) ?: '#2f6fed';
		$context     = in_array( $context, array( 'legend', 'card', 'compact', 'popup' ), true ) ? $context : 'legend';
		$variant     = self::get_type_indicator_variant( $visual, $type_indicator );

		if ( 'color' === $variant ) {
			self::render_type_color_indicator( $color, $context );
			return;
		}

		self::render_type_icon_indicator( $marker_url, $marker_svg, $color, $context );
	}

	/** Resolve whether a type indicator renders as an icon or color swatch. */
	private static function get_type_indicator_variant( array $visual, string $type_indicator ): string {
		if ( 'color' === $type_indicator ) {
			return 'color';
		}

		if ( 'icon' === $type_indicator ) {
			return 'icon';
		}

		$marker = is_array( $visual['marker'] ?? null ) ? $visual['marker'] : array();

		return '' !== esc_url_raw( $marker['image_url'] ?? '' ) || '' !== Data::sanitize_svg( $marker['svg'] ?? '' ) ? 'icon' : 'color';
	}

	/** Render a color type indicator. */
	private static function render_type_color_indicator( string $color, string $context ): void {
		?>
		<span class="vred-geo-maps__type-indicator vred-geo-maps__type-indicator--color vred-geo-maps__type-indicator--<?php echo esc_attr( $context ); ?>" aria-hidden="true" style="--vred-geo-location-color:<?php echo esc_attr( $color ); ?>"></span>
		<?php
	}

	/** Render an icon type indicator with a built-in fallback. */
	private static function render_type_icon_indicator( string $image_url, string $svg, string $color, string $context ): void {
		?>
		<span class="vred-geo-maps__type-indicator vred-geo-maps__type-indicator--icon vred-geo-maps__type-indicator--<?php echo esc_attr( $context ); ?>" aria-hidden="true" style="--vred-geo-location-color:<?php echo esc_attr( $color ); ?>">
			<?php if ( '' !== $image_url ) : ?>
				<img src="<?php echo esc_url( $image_url ); ?>" alt="">
			<?php elseif ( '' !== $svg ) : ?>
				<?php echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized by Data::sanitize_svg(). ?>
			<?php else : ?>
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 10a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"></path></svg>
			<?php endif; ?>
		</span>
		<?php
	}

	/** Render the location identity shared by cards and compact rows. */
	private static function render_location_identity( array $location, string $type_indicator, string $context ): void {
		$marker_color = sanitize_hex_color( $location['marker']['color'] ?? '' ) ?: '#2f6fed';
		?>
		<span class="vred-geo-maps__location-identity vred-geo-maps__location-identity--<?php echo esc_attr( $context ); ?>" style="--vred-geo-location-color:<?php echo esc_attr( $marker_color ); ?>">
			<?php self::render_type_indicator( $location, $type_indicator, $context ); ?>
			<span class="vred-geo-maps__location-copy">
				<strong class="vred-geo-maps__location-title"><?php echo esc_html( $location['title'] ); ?></strong>
				<?php if ( '' !== $location['type_name'] ) : ?>
					<span class="vred-geo-maps__location-type"><?php echo esc_html( $location['type_name'] ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $location['address'] ) : ?>
					<span class="vred-geo-maps__location-address"><?php echo esc_html( $location['address'] ); ?></span>
				<?php endif; ?>
			</span>
		</span>
		<?php
	}

	/** Return whether the location has metadata shown in an expanded card or automatic popup. */
	private static function location_has_metadata( array $location ): bool {
		return '' !== $location['phone'] || '' !== $location['email'] || '' !== $location['website'] || '' !== $location['directions_url'];
	}

	/** Render metadata rows for an expanded card. */
	private static function render_location_metadata( array $location ): void {
		if ( ! self::location_has_metadata( $location ) ) {
			return;
		}
		?>
		<div class="vred-geo-maps__location-metadata vred-geo-maps__location-metadata--card">
			<?php if ( '' !== $location['phone'] ) : ?>
				<div class="vred-geo-maps__metadata-row">
					<?php self::render_contact_link( 'phone', $location['phone'] ); ?>
				</div>
			<?php endif; ?>
			<?php if ( '' !== $location['email'] ) : ?>
				<div class="vred-geo-maps__metadata-row">
					<?php self::render_contact_link( 'email', $location['email'] ); ?>
				</div>
			<?php endif; ?>
			<?php if ( '' !== $location['website'] ) : ?>
				<div class="vred-geo-maps__metadata-row">
					<?php self::render_contact_link( 'website', $location['website'] ); ?>
				</div>
			<?php endif; ?>
			<?php if ( '' !== $location['directions_url'] ) : ?>
				<div class="vred-geo-maps__metadata-row vred-geo-maps__metadata-row--directions">
					<?php self::render_directions_link( $location['directions_url'] ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Render one shared phone, email or website link with its icon. */
	private static function render_contact_link( string $type, string $value ): void {
		$is_external = false;

		if ( 'phone' === $type ) {
			$href  = 'tel:' . preg_replace( '/[^0-9+]/', '', $value );
			$label = $value;
		} elseif ( 'email' === $type ) {
			$href  = 'mailto:' . $value;
			$label = $value;
		} elseif ( 'website' === $type ) {
			$href        = $value;
			$label       = __( 'Website', 'vred-geo-maps' );
			$is_external = true;
		} else {
			return;
		}
		?>
		<?php self::render_metadata_icon( $type ); ?>
		<a class="vred-geo-maps__action vred-geo-maps__contact-link" href="<?php echo $is_external ? esc_url( $href ) : esc_attr( $href ); ?>"<?php if ( $is_external ) : ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>><?php echo esc_html( $label ); ?></a>
		<?php
	}

	/** Render one decorative metadata icon. */
	private static function render_metadata_icon( string $icon ): void {
		if ( 'phone' === $icon ) {
			?>
			<span class="vred-geo-maps__metadata-icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384"></path></svg>
			</span>
			<?php
			return;
		}

		$classes = array(
			'email'      => 'dashicons-email-alt',
			'website'    => 'dashicons-admin-site-alt3',
			'directions' => 'dashicons-location',
		);

		if ( ! isset( $classes[ $icon ] ) ) {
			return;
		}
		?>
		<span class="vred-geo-maps__metadata-icon dashicons <?php echo esc_attr( $classes[ $icon ] ); ?>" aria-hidden="true"></span>
		<?php
	}

	/** Build the automatic popup with a dedicated Leaflet-friendly layout. */
	private static function build_automatic_popup( array $location, string $type_indicator ): string {
		$marker_color = sanitize_hex_color( $location['marker']['color'] ?? '' ) ?: '#2f6fed';

		ob_start();
		?>
		<div class="vred-geo-maps__popup-content" style="--vred-geo-location-color:<?php echo esc_attr( $marker_color ); ?>">
			<div class="vred-geo-maps__popup-header">
				<?php self::render_type_indicator( $location, $type_indicator, 'popup' ); ?>
				<div class="vred-geo-maps__popup-identity">
					<strong class="vred-geo-maps__popup-title"><?php echo esc_html( $location['title'] ); ?></strong>
					<?php if ( '' !== $location['type_name'] ) : ?>
						<span class="vred-geo-maps__popup-type"><?php echo esc_html( $location['type_name'] ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $location['address'] ) : ?>
						<span class="vred-geo-maps__popup-address"><?php echo esc_html( $location['address'] ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( self::location_has_metadata( $location ) ) : ?>
				<div class="vred-geo-maps__popup-metadata">
					<?php if ( '' !== $location['phone'] ) : ?>
						<div class="vred-geo-maps__popup-metadata-row">
							<?php self::render_contact_link( 'phone', $location['phone'] ); ?>
						</div>
					<?php endif; ?>
					<?php if ( '' !== $location['email'] ) : ?>
						<div class="vred-geo-maps__popup-metadata-row">
							<?php self::render_contact_link( 'email', $location['email'] ); ?>
						</div>
					<?php endif; ?>
					<?php if ( '' !== $location['website'] ) : ?>
						<div class="vred-geo-maps__popup-metadata-row">
							<?php self::render_contact_link( 'website', $location['website'] ); ?>
						</div>
					<?php endif; ?>
					<?php if ( '' !== $location['directions_url'] ) : ?>
						<div class="vred-geo-maps__popup-metadata-row vred-geo-maps__popup-metadata-row--directions">
							<?php self::render_directions_link( $location['directions_url'] ); ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/** Render a safe external directions link without affecting location selection. */
	private static function render_directions_link( string $url, string $class = '' ): void {
		$classes = trim( 'vred-geo-maps__action vred-geo-maps__directions-link ' . $class );
		?>
		<a class="<?php echo esc_attr( $classes ); ?>" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
			<?php self::render_metadata_icon( 'directions' ); ?>
			<span><?php esc_html_e( 'Get directions', 'vred-geo-maps' ); ?></span>
		</a>
		<?php
	}

	/** Return whether at least one rendered location has a value for a field. */
	private static function has_location_value( array $locations, string $key ): bool {
		foreach ( $locations as $location ) {
			if ( '' !== trim( (string) ( $location[ $key ] ?? '' ) ) ) {
				return true;
			}
		}

		return false;
	}

	/** Render data attributes shared by all list modes. */
	private static function render_location_item_attributes( array $location ): void {
		printf(
			'data-vred-geo-location-item data-location-id="%1$s" data-type-id="%2$s" data-search="%3$s"',
			esc_attr( (string) $location['id'] ),
			esc_attr( (string) $location['type_id'] ),
			esc_attr( $location['search_text'] )
		);
	}

	/** Return only data needed by JavaScript. */
	private static function get_js_location( array $location, string $type_indicator ): array {
		$popup_html = ! empty( $location['popup_custom'] )
			? $location['popup_html']
			: self::build_automatic_popup( $location, $type_indicator );

		return array(
			'id'          => (int) $location['id'],
			'title'       => $location['title'],
			'latitude'    => (float) $location['latitude'],
			'longitude'   => (float) $location['longitude'],
			'typeId'      => (int) $location['type_id'],
			'city'        => $location['city'],
			'region'      => $location['region'],
			'country'     => $location['country'],
			'searchText'  => $location['search_text'],
			'marker'      => $location['marker'],
			'action'      => $location['action'],
			'website'     => $location['website'],
			'popupHtml'   => $popup_html,
		);
	}
}
