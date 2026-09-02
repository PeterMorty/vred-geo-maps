<?php
/**
 * Self-hosted VRED updater integration.
 *
 * @package VRED_Geo_Maps
 */

namespace VRED\GeoMaps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Updater {
	private const CACHE_KEY = 'vred_geo_maps_remote_plugin_info';

	/** Register updater hooks. */
	public static function boot(): void {
		add_filter( 'update_plugins_dev.viviendoenred.com', array( self::class, 'filter_plugin_update' ), 10, 4 );
		add_filter( 'pre_set_site_transient_update_plugins', array( self::class, 'filter_update_transient' ) );
		add_filter( 'site_transient_update_plugins', array( self::class, 'filter_update_transient' ) );
		add_filter( 'plugins_api', array( self::class, 'filter_plugin_info' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( self::class, 'after_plugin_update' ), 10, 2 );
		add_action( 'admin_init', array( self::class, 'maybe_refresh_updates' ) );
	}

	/** Provide updates through the Update URI hostname filter. */
	public static function filter_plugin_update( mixed $update, array $plugin_data, string $plugin_file, array $locales ): mixed {
		unset( $locales );

		if ( VRED_GEO_MAPS_BASENAME !== $plugin_file ) {
			return $update;
		}

		return self::get_update_payload( $plugin_data );
	}

	/** Populate WordPress update transients. */
	public static function filter_update_transient( mixed $transient ): mixed {
		if ( ! is_object( $transient ) || empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
			return $transient;
		}

		if ( ! isset( $transient->checked[ VRED_GEO_MAPS_BASENAME ] ) ) {
			return $transient;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_data = get_plugin_data( VRED_GEO_MAPS_FILE, false, false );
		$payload     = self::get_update_payload( $plugin_data );

		if ( ! empty( $payload ) ) {
			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}

			$transient->response[ VRED_GEO_MAPS_BASENAME ] = (object) $payload;
		} else {
			if ( isset( $transient->response[ VRED_GEO_MAPS_BASENAME ] ) ) {
				unset( $transient->response[ VRED_GEO_MAPS_BASENAME ] );
			}

			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
				$transient->no_update = array();
			}

			$transient->no_update[ VRED_GEO_MAPS_BASENAME ] = (object) array(
				'id'            => VRED_GEO_MAPS_UPDATE_URL,
				'slug'          => VRED_GEO_MAPS_SLUG,
				'plugin'        => VRED_GEO_MAPS_BASENAME,
				'new_version'   => VRED_GEO_MAPS_VERSION,
				'url'           => 'https://viviendoenred.com',
				'package'       => '',
				'icons'         => array(),
				'banners'       => array(),
				'banners_rtl'   => array(),
				'tested'        => '',
				'requires_php'  => '8.1',
				'compatibility' => new \stdClass(),
			);
		}

		return $transient;
	}

	/** Provide the native plugin information modal. */
	public static function filter_plugin_info( mixed $result, string $action, mixed $args ): mixed {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || VRED_GEO_MAPS_SLUG !== $args->slug ) {
			return $result;
		}

		$plugin_info = self::get_remote_plugin_info();
		$sections    = ! empty( $plugin_info['sections'] ) && is_array( $plugin_info['sections'] ) ? $plugin_info['sections'] : self::get_fallback_sections();

		return (object) array(
			'name'          => ! empty( $plugin_info['name'] ) ? $plugin_info['name'] : 'VRED Geo Maps',
			'slug'          => VRED_GEO_MAPS_SLUG,
			'version'       => ! empty( $plugin_info['version'] ) ? $plugin_info['version'] : VRED_GEO_MAPS_VERSION,
			'author'        => '<a href="https://viviendoenred.com">VRED</a>',
			'homepage'      => ! empty( $plugin_info['homepage'] ) ? $plugin_info['homepage'] : 'https://viviendoenred.com',
			'requires'      => ! empty( $plugin_info['requires'] ) ? $plugin_info['requires'] : '6.6',
			'tested'        => ! empty( $plugin_info['tested'] ) ? $plugin_info['tested'] : '',
			'requires_php'  => ! empty( $plugin_info['requires_php'] ) ? $plugin_info['requires_php'] : '8.1',
			'last_updated'  => ! empty( $plugin_info['last_updated'] ) ? $plugin_info['last_updated'] : '',
			'download_link' => ! empty( $plugin_info['download_url'] ) ? $plugin_info['download_url'] : '',
			'sections'      => array(
				'description'  => $sections['description'] ?? '',
				'installation' => $sections['installation'] ?? '',
				'changelog'    => $sections['changelog'] ?? '',
			),
			'banners'       => ! empty( $plugin_info['banners'] ) && is_array( $plugin_info['banners'] ) ? $plugin_info['banners'] : array(),
			'icons'         => self::get_plugin_icons( $plugin_info ),
		);
	}

	/** Return update data used by the settings screen. */
	public static function get_settings_update_data(): array {
		$plugin_info    = self::get_remote_plugin_info();
		$remote_version = ! empty( $plugin_info['version'] ) ? (string) $plugin_info['version'] : '';

		return array(
			'current_version' => VRED_GEO_MAPS_VERSION,
			'remote_version'  => $remote_version,
			'has_update'      => '' !== $remote_version && version_compare( VRED_GEO_MAPS_VERSION, $remote_version, '<' ),
		);
	}

	/** Return the manual update check URL used by the settings screen. */
	public static function get_refresh_url(): string {
		$url = add_query_arg(
			array(
				'page'                          => VRED_GEO_MAPS_SLUG,
				'tab'                           => 'settings',
				'vred-geo-maps-refresh-updates' => '1',
			),
			admin_url( 'options-general.php' )
		);

		return wp_nonce_url( $url, 'vred-geo-maps-refresh-updates' );
	}

	/** Clear updater caches and ask WordPress to check again. */
	public static function maybe_refresh_updates(): void {
		if ( empty( $_GET['page'] ) || VRED_GEO_MAPS_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		if ( empty( $_GET['vred-geo-maps-refresh-updates'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'vred-geo-maps-refresh-updates' );

		if ( ! function_exists( 'wp_update_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		delete_site_transient( self::CACHE_KEY );
		delete_site_transient( 'update_plugins' );
		wp_clean_plugins_cache( true );
		wp_update_plugins();

		$redirect = add_query_arg(
			array(
				'page'                           => VRED_GEO_MAPS_SLUG,
				'tab'                            => 'settings',
				'vred-geo-maps-updates-refreshed' => '1',
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/** Clear updater caches after a plugin update. */
	public static function after_plugin_update( mixed $upgrader_object, array $options ): void {
		unset( $upgrader_object );

		if ( empty( $options['action'] ) || 'update' !== $options['action'] || empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
			return;
		}

		delete_site_transient( self::CACHE_KEY );
		delete_site_transient( 'update_plugins' );
	}

	/** Build the update payload when a newer remote version exists. */
	private static function get_update_payload( array $plugin_data ): array {
		$plugin_info       = self::get_remote_plugin_info();
		$installed_version = ! empty( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : VRED_GEO_MAPS_VERSION;

		if ( empty( $plugin_info['version'] ) || version_compare( $installed_version, $plugin_info['version'], '>=' ) ) {
			return array();
		}

		$package_url = ! empty( $plugin_info['download_url'] ) ? (string) $plugin_info['download_url'] : '';

		if ( '' === $package_url ) {
			return array();
		}

		return array(
			'id'            => ! empty( $plugin_data['UpdateURI'] ) ? $plugin_data['UpdateURI'] : VRED_GEO_MAPS_UPDATE_URL,
			'slug'          => VRED_GEO_MAPS_SLUG,
			'plugin'        => VRED_GEO_MAPS_BASENAME,
			'new_version'   => $plugin_info['version'],
			'url'           => ! empty( $plugin_info['homepage'] ) ? $plugin_info['homepage'] : 'https://viviendoenred.com',
			'package'       => $package_url,
			'tested'        => ! empty( $plugin_info['tested'] ) ? $plugin_info['tested'] : '',
			'requires'      => ! empty( $plugin_info['requires'] ) ? $plugin_info['requires'] : '',
			'requires_php'  => ! empty( $plugin_info['requires_php'] ) ? $plugin_info['requires_php'] : '',
			'autoupdate'    => false,
			'icons'         => self::get_plugin_icons( $plugin_info ),
			'banners'       => ! empty( $plugin_info['banners'] ) && is_array( $plugin_info['banners'] ) ? $plugin_info['banners'] : array(),
			'banners_rtl'   => ! empty( $plugin_info['banners_rtl'] ) && is_array( $plugin_info['banners_rtl'] ) ? $plugin_info['banners_rtl'] : array(),
			'translations'  => array(),
			'compatibility' => new \stdClass(),
		);
	}

	/** Get and cache remote update metadata. */
	private static function get_remote_plugin_info(): array {
		static $plugin_info = null;

		if ( null !== $plugin_info ) {
			return $plugin_info;
		}

		$cached = get_site_transient( self::CACHE_KEY );

		if ( is_array( $cached ) ) {
			$plugin_info = self::sanitize_remote_plugin_info( $cached );
			return $plugin_info;
		}

		$update_url = self::validate_remote_url( VRED_GEO_MAPS_UPDATE_URL );

		if ( '' === $update_url ) {
			$plugin_info = array();
			return $plugin_info;
		}

		$response = wp_remote_get(
			$update_url,
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'headers'     => array(
					'Accept'        => 'application/json',
					'Cache-Control' => 'no-cache',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$plugin_info = array();
			set_site_transient( self::CACHE_KEY, array(), 5 * MINUTE_IN_SECONDS );
			return $plugin_info;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $data ) ) {
			$plugin_info = array();
			set_site_transient( self::CACHE_KEY, array(), 5 * MINUTE_IN_SECONDS );
			return $plugin_info;
		}

		$plugin_info = self::sanitize_remote_plugin_info( $data );

		if ( empty( $plugin_info['version'] ) || empty( $plugin_info['download_url'] ) ) {
			$plugin_info = array();
			set_site_transient( self::CACHE_KEY, array(), 5 * MINUTE_IN_SECONDS );
			return $plugin_info;
		}

		set_site_transient( self::CACHE_KEY, $plugin_info, HOUR_IN_SECONDS );
		return $plugin_info;
	}

	/** Sanitize remote update metadata. */
	private static function sanitize_remote_plugin_info( array $data ): array {
		$version = ! empty( $data['version'] ) ? sanitize_text_field( (string) $data['version'] ) : '';

		if ( '' === $version || strlen( $version ) > 64 || ! preg_match( '/^[0-9]+(?:\.[0-9]+)*(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ) {
			return array();
		}

		$sections    = ! empty( $data['sections'] ) && is_array( $data['sections'] ) ? $data['sections'] : array();
		$package_url = ! empty( $data['download_url'] ) ? (string) $data['download_url'] : ( ! empty( $data['package'] ) ? (string) $data['package'] : '' );

		return array(
			'name'          => ! empty( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '',
			'version'       => $version,
			'homepage'      => ! empty( $data['homepage'] ) ? self::validate_remote_url( (string) $data['homepage'] ) : '',
			'requires'      => ! empty( $data['requires'] ) ? sanitize_text_field( (string) $data['requires'] ) : '',
			'tested'        => ! empty( $data['tested'] ) ? sanitize_text_field( (string) $data['tested'] ) : '',
			'requires_php'  => ! empty( $data['requires_php'] ) ? sanitize_text_field( (string) $data['requires_php'] ) : '',
			'last_updated'  => ! empty( $data['last_updated'] ) ? sanitize_text_field( (string) $data['last_updated'] ) : '',
			'sections'      => array(
				'description'  => ! empty( $sections['description'] ) ? wp_kses_post( (string) $sections['description'] ) : '',
				'installation' => ! empty( $sections['installation'] ) ? wp_kses_post( (string) $sections['installation'] ) : '',
				'changelog'    => ! empty( $sections['changelog'] ) ? wp_kses_post( (string) $sections['changelog'] ) : '',
			),
			'icons'         => self::sanitize_remote_assets( $data['icons'] ?? array(), array( '1x', '2x', 'svg', 'default' ) ),
			'banners'       => self::sanitize_remote_assets( $data['banners'] ?? array(), array( 'low', 'high' ) ),
			'banners_rtl'   => self::sanitize_remote_assets( $data['banners_rtl'] ?? array(), array( 'low', 'high' ) ),
			'download_url'  => self::validate_remote_url( $package_url ),
		);
	}

	/** Sanitize remote icon/banner URLs. */
	private static function sanitize_remote_assets( mixed $assets, array $allowed_keys ): array {
		if ( ! is_array( $assets ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $allowed_keys as $key ) {
			if ( empty( $assets[ $key ] ) ) {
				continue;
			}

			$url = self::validate_remote_url( (string) $assets[ $key ] );
			if ( '' !== $url ) {
				$sanitized[ $key ] = $url;
			}
		}

		return $sanitized;
	}

	/** Return remote icons when present. */
	private static function get_plugin_icons( array $plugin_info = array() ): array {
		return ! empty( $plugin_info['icons'] ) && is_array( $plugin_info['icons'] ) ? $plugin_info['icons'] : array();
	}

	/** Allow only trusted HTTPS VRED update hosts. */
	private static function validate_remote_url( string $url ): string {
		$url = trim( $url );

		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		$host  = is_array( $parts ) && ! empty( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';

		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || 'https' !== strtolower( (string) $parts['scheme'] ) ) {
			return '';
		}

		if ( ! in_array( $host, array( 'viviendoenred.com', 'www.viviendoenred.com', 'dev.viviendoenred.com' ), true ) ) {
			return '';
		}

		return esc_url_raw( $url );
	}

	/** Local modal content used until the remote manifest exists. */
	private static function get_fallback_sections(): array {
		return array(
			'description'  => '<p>' . esc_html__( 'VRED Geo Maps manages reusable locations and displays them on Leaflet maps via shortcode.', 'vred-geo-maps' ) . '</p>',
			'installation' => '<p>' . esc_html__( 'Upload and activate the plugin, then configure locations under Settings > VRED Geo Maps.', 'vred-geo-maps' ) . '</p>',
			'changelog'    => '<h4>0.1.16</h4><p>' . esc_html__( 'Added a grouped list style with addresses and configurable type indicators for grouped views.', 'vred-geo-maps' ) . '</p><h4>0.1.15</h4><p>' . esc_html__( 'Refined Legend behavior for horizontal layouts, simplified filter controls and tightened admin add/geocoding controls.', 'vred-geo-maps' ) . '</p><h4>0.1.14</h4><p>' . esc_html__( 'Added selectable Cards, Compact and Legend list styles while keeping map data and interactions shared.', 'vred-geo-maps' ) . '</p><h4>0.1.13</h4><p>' . esc_html__( 'Removed the dedicated Elementor integration and simplified the frontend to a shortcode-first architecture.', 'vred-geo-maps' ) . '</p><h4>0.1.12</h4><p>' . esc_html__( 'Refined admin field spacing, compacted card contact details, and normalized Leaflet attribution typography.', 'vred-geo-maps' ) . '</p><h4>0.1.11</h4><p>' . esc_html__( 'Refined frontend card details and Leaflet controls, removed accent color styling, and finalized admin spacing and labels.', 'vred-geo-maps' ) . '</p><h4>0.1.10</h4><p>' . esc_html__( 'Refined admin fieldset headings and redesigned frontend location cards as compact neutral accordions.', 'vred-geo-maps' ) . '</p><h4>0.1.9</h4><p>' . esc_html__( 'Localized AJAX network failures and audited user-facing strings.', 'vred-geo-maps' ) . '</p><h4>0.1.8</h4><p>' . esc_html__( 'Finalized admin controls, marker override labels, save actions and update status presentation.', 'vred-geo-maps' ) . '</p><h4>0.1.7</h4><p>' . esc_html__( 'Compacted global settings controls and tightened marker override spacing for a more consistent admin layout.', 'vred-geo-maps' ) . '</p><h4>0.1.6</h4><p>' . esc_html__( 'Refined marker override controls, popup option visibility, geocoding feedback and admin status styling.', 'vred-geo-maps' ) . '</p><h4>0.1.5</h4><p>' . esc_html__( 'Compacted location editing, added explicit address geocoding controls and optional rich popup content.', 'vred-geo-maps' ) . '</p><h4>0.1.4</h4><p>' . esc_html__( 'Fixed marker action persistence and marker clustering initialization.', 'vred-geo-maps' ) . '</p><h4>0.1.3</h4><p>' . esc_html__( 'Added optional uninstall cleanup, simplified location links and marker controls, and bundled map libraries locally.', 'vred-geo-maps' ) . '</p><h4>0.1.2</h4><p>' . esc_html__( 'Improved custom markers, list scrolling and shortcode guidance.', 'vred-geo-maps' ) . '</p><h4>0.1.1</h4><p>' . esc_html__( 'Refined admin experience and plugin integration.', 'vred-geo-maps' ) . '</p><h4>0.1.0</h4><p>' . esc_html__( 'Initial development version.', 'vred-geo-maps' ) . '</p>',
		);
	}
}
