<?php
/**
 * Admin settings and location manager.
 *
 * @package VRED_Geo_Maps
 */

namespace VRED\GeoMaps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	private const PAGE_SLUG = 'vred-geo-maps';
	private const NONCE_ACTION = 'vred_geo_maps_admin';
	private static bool $settings_updated = false;

	/** Register admin hooks. */
	public static function boot(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );

		add_action( 'wp_ajax_vred_geo_maps_add_location', array( self::class, 'ajax_add_location' ) );
		add_action( 'wp_ajax_vred_geo_maps_save_location', array( self::class, 'ajax_save_location' ) );
		add_action( 'wp_ajax_vred_geo_maps_duplicate_location', array( self::class, 'ajax_duplicate_location' ) );
		add_action( 'wp_ajax_vred_geo_maps_delete_location', array( self::class, 'ajax_delete_location' ) );
		add_action( 'wp_ajax_vred_geo_maps_reorder_locations', array( self::class, 'ajax_reorder_locations' ) );
		add_action( 'wp_ajax_vred_geo_maps_geocode_address', array( self::class, 'ajax_geocode_address' ) );

		add_action( 'wp_ajax_vred_geo_maps_add_type', array( self::class, 'ajax_add_type' ) );
		add_action( 'wp_ajax_vred_geo_maps_save_type', array( self::class, 'ajax_save_type' ) );
		add_action( 'wp_ajax_vred_geo_maps_delete_type', array( self::class, 'ajax_delete_type' ) );
		add_action( 'wp_ajax_vred_geo_maps_reorder_types', array( self::class, 'ajax_reorder_types' ) );
	}

	/** Add the settings page. */
	public static function register_menu(): void {
		add_options_page(
			__( 'VRED Geo Maps', 'vred-geo-maps' ),
			__( 'VRED Geo Maps', 'vred-geo-maps' ),
			'manage_options',
			self::PAGE_SLUG,
			array( self::class, 'render_page' )
		);
	}

	/** Register the global settings option. */
	public static function register_settings(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		if ( self::PAGE_SLUG === $page && 'settings' === $tab && isset( $_GET['settings-updated'] ) ) {
			self::$settings_updated = true;
			unset( $_GET['settings-updated'] );
		}

		register_setting(
			'vred_geo_maps_settings',
			VRED_GEO_MAPS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Data::class, 'sanitize_settings' ),
				'default'           => Data::get_default_settings(),
			)
		);
	}

	/** Enqueue admin UI assets only on the plugin screen. */
	public static function enqueue_assets( string $hook ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'locations';
		if ( 'locations' === $tab ) {
			wp_enqueue_editor();
		}

		wp_enqueue_style(
			'vred-geo-maps-admin',
			VRED_GEO_MAPS_URL . 'assets/css/admin.css',
			array(),
			VRED_GEO_MAPS_VERSION
		);

		wp_enqueue_script(
			'vred-geo-maps-admin',
			VRED_GEO_MAPS_URL . 'assets/js/admin.js',
			array(),
			VRED_GEO_MAPS_VERSION,
			true
		);

		wp_localize_script(
			'vred-geo-maps-admin',
			'vredGeoMapsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'strings' => array(
					'saving'             => __( 'Saving…', 'vred-geo-maps' ),
					'saved'              => __( 'Saved.', 'vred-geo-maps' ),
					'error'              => __( 'Something went wrong.', 'vred-geo-maps' ),
					'networkError'       => __( 'Network error. Check your connection and try again.', 'vred-geo-maps' ),
					'deleteLocation'     => __( 'Delete this location permanently?', 'vred-geo-maps' ),
					'deleteType'         => __( 'Delete this location type? Assigned locations will become uncategorized.', 'vred-geo-maps' ),
					'deleteItem'         => __( 'Delete this item?', 'vred-geo-maps' ),
					'reorderDisabled'    => __( 'Clear the search before reordering.', 'vred-geo-maps' ),
					'findingCoordinates' => __( 'Finding coordinates…', 'vred-geo-maps' ),
					'coordinatesUpdated' => __( 'Coordinates updated.', 'vred-geo-maps' ),
					'coordinatesAndGeographicDataUpdated' => __( 'Coordinates and geographic data updated.', 'vred-geo-maps' ),
					'coordinatesNotFound' => __( 'No coordinates were found for this address.', 'vred-geo-maps' ),
					'addressTooShort'      => __( 'Enter a more complete address.', 'vred-geo-maps' ),
					'selectImage'         => __( 'Select image', 'vred-geo-maps' ),
				),
			)
		);
	}

	/** Render the complete settings screen. */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'locations';

		if ( ! in_array( $tab, array( 'locations', 'types', 'settings' ), true ) ) {
			$tab = 'locations';
		}
		?>
		<div class="wrap vred-geo-admin" data-vred-geo-admin>
			<div class="vred-geo-admin__heading">
				<h1><?php esc_html_e( 'VRED Geo Maps', 'vred-geo-maps' ); ?></h1>
			</div>

			<nav class="nav-tab-wrapper vred-geo-admin__tabs">
				<?php self::render_tab_link( 'locations', __( 'Locations', 'vred-geo-maps' ), $tab ); ?>
				<?php self::render_tab_link( 'types', __( 'Location Types', 'vred-geo-maps' ), $tab ); ?>
				<?php self::render_tab_link( 'settings', __( 'Settings', 'vred-geo-maps' ), $tab ); ?>
			</nav>

			<div class="vred-geo-admin__panel">
				<?php
				if ( 'types' === $tab ) {
					self::render_types_tab();
				} elseif ( 'settings' === $tab ) {
					self::render_settings_tab();
				} else {
					self::render_locations_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	/** Render one nav tab. */
	private static function render_tab_link( string $key, string $label, string $current ): void {
		$url   = add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => $key ), admin_url( 'options-general.php' ) );
		$class = 'nav-tab' . ( $key === $current ? ' nav-tab-active' : '' );
		printf( '<a class="%1$s" href="%2$s">%3$s</a>', esc_attr( $class ), esc_url( $url ), esc_html( $label ) );
	}

	/** Render locations manager. */
	private static function render_locations_tab(): void {
		$locations = Data::get_locations();
		$types     = Data::get_types();
		?>
		<div class="vred-geo-admin__toolbar">
			<form class="vred-geo-admin__add-form" data-vred-geo-add-form="location">
				<label for="vred-geo-new-location" class="screen-reader-text"><?php esc_html_e( 'New location name', 'vred-geo-maps' ); ?></label>
				<input id="vred-geo-new-location" type="text" name="title" required placeholder="<?php echo esc_attr__( 'New location name', 'vred-geo-maps' ); ?>">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Add', 'vred-geo-maps' ); ?></button>
			</form>
			<div class="vred-geo-admin__tools">
				<label class="vred-geo-admin__search">
					<span class="screen-reader-text"><?php esc_html_e( 'Search locations', 'vred-geo-maps' ); ?></span>
					<input type="search" placeholder="<?php echo esc_attr__( 'Search locations…', 'vred-geo-maps' ); ?>" data-vred-geo-admin-search>
				</label>
				<button type="button" class="button" data-vred-geo-expand-all><?php esc_html_e( 'Expand all', 'vred-geo-maps' ); ?></button>
				<button type="button" class="button" data-vred-geo-collapse-all><?php esc_html_e( 'Collapse all', 'vred-geo-maps' ); ?></button>
			</div>
		</div>

		<div class="vred-geo-admin__notice" data-vred-geo-status aria-live="polite"></div>
		<div class="vred-geo-admin__cards" data-vred-geo-sortable="location">
			<?php foreach ( $locations as $location ) : ?>
				<?php self::render_location_card( $location, $types ); ?>
			<?php endforeach; ?>
			<p class="vred-geo-admin__empty" data-vred-geo-empty <?php echo $locations ? 'hidden' : ''; ?>><?php esc_html_e( 'No locations yet. Add the first one above.', 'vred-geo-maps' ); ?></p>
		</div>
		<?php
	}

	/** Render one editable location card. */
	private static function render_location_card( \WP_Post $location, array $types ): void {
		$type              = Data::get_location_type( $location->ID );
		$address           = (string) get_post_meta( $location->ID, Data::META_ADDRESS, true );
		$latitude          = (string) get_post_meta( $location->ID, Data::META_LATITUDE, true );
		$longitude         = (string) get_post_meta( $location->ID, Data::META_LONGITUDE, true );
		$city              = (string) get_post_meta( $location->ID, Data::META_CITY, true );
		$region            = (string) get_post_meta( $location->ID, Data::META_REGION, true );
		$country           = (string) get_post_meta( $location->ID, Data::META_COUNTRY, true );
		$phone             = (string) get_post_meta( $location->ID, Data::META_PHONE, true );
		$email             = (string) get_post_meta( $location->ID, Data::META_EMAIL, true );
		$website           = (string) get_post_meta( $location->ID, Data::META_WEBSITE, true );
		$action            = (string) get_post_meta( $location->ID, Data::META_ACTION, true );
		$popup             = (string) get_post_meta( $location->ID, Data::META_POPUP_CONTENT, true );
		$popup_custom_meta = (string) get_post_meta( $location->ID, Data::META_POPUP_CUSTOM, true );
		$popup_custom      = '1' === $popup_custom_meta || ( '' === $popup_custom_meta && '' !== trim( $popup ) );
		$marker_image_id   = Data::sanitize_attachment_id( get_post_meta( $location->ID, Data::META_MARKER_IMAGE_ID, true ) );
		$marker_color      = (string) get_post_meta( $location->ID, Data::META_MARKER_COLOR, true );
		$marker_size       = (string) get_post_meta( $location->ID, Data::META_MARKER_SIZE, true );
		$search            = trim( implode( ' ', array( $location->post_title, $address, $city, $region, $country, $type ? $type->name : '' ) ) );
		$editor_id         = 'vred-geo-popup-content-' . $location->ID;
		?>
		<article class="vred-geo-admin-card" data-vred-geo-card data-id="<?php echo esc_attr( (string) $location->ID ); ?>" data-search="<?php echo esc_attr( $search ); ?>">
			<header class="vred-geo-admin-card__header">
				<button type="button" class="vred-geo-admin-card__drag" data-vred-geo-drag-handle aria-label="<?php echo esc_attr__( 'Drag to reorder', 'vred-geo-maps' ); ?>">⋮⋮</button>
				<button type="button" class="vred-geo-admin-card__toggle" data-vred-geo-toggle aria-expanded="false">
					<strong data-vred-geo-summary-title><?php echo esc_html( $location->post_title ); ?></strong>
					<span class="vred-geo-admin-card__summary" data-vred-geo-summary-meta><?php echo esc_html( self::get_location_summary( $address, $type ) ); ?></span>
				</button>
				<div class="vred-geo-admin-card__actions">
					<button type="button" class="vred-geo-admin-card__action" data-vred-geo-duplicate><?php esc_html_e( 'Duplicate', 'vred-geo-maps' ); ?></button>
					<button type="button" class="vred-geo-admin-card__action vred-geo-admin-card__action--delete" data-vred-geo-delete><?php esc_html_e( 'Delete', 'vred-geo-maps' ); ?></button>
				</div>
			</header>
			<div class="vred-geo-admin-card__body" data-vred-geo-card-body hidden>
				<form data-vred-geo-edit-form="location">
					<input type="hidden" name="id" value="<?php echo esc_attr( (string) $location->ID ); ?>">
					<section class="vred-geo-admin-block vred-geo-admin-block--compact">
						<header class="vred-geo-admin-block__header">
							<h3 class="vred-geo-admin-block__title"><?php esc_html_e( 'Location data', 'vred-geo-maps' ); ?></h3>
						</header>
						<div class="vred-geo-admin-block__content">
							<div class="vred-geo-admin-grid">
								<?php self::text_field( 'title', __( 'Name', 'vred-geo-maps' ), $location->post_title, true ); ?>
								<label class="vred-geo-admin-field">
									<span><?php esc_html_e( 'Location type', 'vred-geo-maps' ); ?></span>
									<select name="type_id">
										<option value="0"><?php esc_html_e( 'No type', 'vred-geo-maps' ); ?></option>
										<?php foreach ( $types as $type_option ) : ?>
											<option value="<?php echo esc_attr( (string) $type_option->term_id ); ?>" <?php selected( $type ? $type->term_id : 0, $type_option->term_id ); ?>><?php echo esc_html( $type_option->name ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
							</div>
							<div class="vred-geo-admin-contact-grid">
								<?php self::text_field( 'phone', __( 'Phone', 'vred-geo-maps' ), $phone ); ?>
								<?php self::text_field( 'email', __( 'Email', 'vred-geo-maps' ), $email, false, '', 'email' ); ?>
								<?php self::text_field( 'website', __( 'Website', 'vred-geo-maps' ), $website, false, '', 'url' ); ?>
							</div>
							<?php self::address_field( $address ); ?>
						</div>
					</section>
					<section class="vred-geo-admin-block vred-geo-admin-block--compact">
						<header class="vred-geo-admin-block__header">
							<h3 class="vred-geo-admin-block__title"><?php esc_html_e( 'Geographic data', 'vred-geo-maps' ); ?></h3>
						</header>
						<div class="vred-geo-admin-block__content">
							<div class="vred-geo-admin-geographic-grid">
								<?php self::text_field( 'city', __( 'City', 'vred-geo-maps' ), $city ); ?>
								<?php self::text_field( 'region', __( 'Province / region', 'vred-geo-maps' ), $region ); ?>
								<?php self::text_field( 'country', __( 'Country', 'vred-geo-maps' ), $country ); ?>
							</div>
							<div class="vred-geo-admin-coordinate-grid">
								<?php self::text_field( 'latitude', __( 'Latitude', 'vred-geo-maps' ), $latitude ); ?>
								<?php self::text_field( 'longitude', __( 'Longitude', 'vred-geo-maps' ), $longitude ); ?>
							</div>
							<p class="description"><?php esc_html_e( 'These data are filled automatically when locating the address. You can edit them manually.', 'vred-geo-maps' ); ?></p>
						</div>
					</section>

					<?php self::render_marker_override_fields( $marker_image_id, $marker_color, $marker_size ); ?>

					<section class="vred-geo-admin-block vred-geo-admin-block--compact">
						<header class="vred-geo-admin-block__header">
							<h3 class="vred-geo-admin-block__title"><?php esc_html_e( 'Marker action', 'vred-geo-maps' ); ?></h3>
						</header>
						<div class="vred-geo-admin-block__content">
							<div class="vred-geo-admin-action-row">
								<label class="vred-geo-admin-field vred-geo-admin-field--action">
									<span><?php esc_html_e( 'Action', 'vred-geo-maps' ); ?></span>
									<select name="marker_action" data-vred-geo-action-select>
										<option value="none" <?php selected( $action, 'none' ); ?>><?php esc_html_e( 'None', 'vred-geo-maps' ); ?></option>
										<option value="popup" <?php selected( '' === $action || 'popup' === $action, true ); ?>><?php esc_html_e( 'Popup', 'vred-geo-maps' ); ?></option>
										<option value="link" <?php selected( $action, 'link' ); ?>><?php esc_html_e( 'Link', 'vred-geo-maps' ); ?></option>
									</select>
								</label>
								<label class="vred-geo-admin-popup-toggle" data-vred-geo-action-fields="popup" <?php echo ( '' === $action || 'popup' === $action ) ? '' : 'hidden'; ?>>
									<input type="checkbox" name="popup_custom" value="1" data-vred-geo-popup-custom <?php checked( $popup_custom ); ?>>
									<span><?php esc_html_e( 'Custom content', 'vred-geo-maps' ); ?></span>
								</label>
							</div>
							<div class="vred-geo-admin-popup-editor" data-vred-geo-popup-editor <?php echo $popup_custom && ( '' === $action || 'popup' === $action ) ? '' : 'hidden'; ?>>
								<textarea id="<?php echo esc_attr( $editor_id ); ?>" name="popup_content" rows="5" data-vred-geo-popup-textarea><?php echo esc_textarea( $popup ); ?></textarea>
							</div>
						</div>
					</section>
					<div class="vred-geo-admin-card__footer">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save changes', 'vred-geo-maps' ); ?></button>
						<span class="vred-geo-admin-card__save-status" data-vred-geo-save-status aria-live="polite"></span>
					</div>
				</form>
			</div>
		</article>
		<?php
	}

	/** Render location types manager. */
	private static function render_types_tab(): void {
		$types = Data::get_types();
		?>
		<div class="vred-geo-admin__toolbar">
			<form class="vred-geo-admin__add-form" data-vred-geo-add-form="type">
				<label for="vred-geo-new-type" class="screen-reader-text"><?php esc_html_e( 'New location type name', 'vred-geo-maps' ); ?></label>
				<input id="vred-geo-new-type" type="text" name="name" required placeholder="<?php echo esc_attr__( 'New location type', 'vred-geo-maps' ); ?>">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Add', 'vred-geo-maps' ); ?></button>
			</form>
			<div class="vred-geo-admin__tools">
				<label class="vred-geo-admin__search">
					<span class="screen-reader-text"><?php esc_html_e( 'Search location types', 'vred-geo-maps' ); ?></span>
					<input type="search" placeholder="<?php echo esc_attr__( 'Search types…', 'vred-geo-maps' ); ?>" data-vred-geo-admin-search>
				</label>
				<button type="button" class="button" data-vred-geo-expand-all><?php esc_html_e( 'Expand all', 'vred-geo-maps' ); ?></button>
				<button type="button" class="button" data-vred-geo-collapse-all><?php esc_html_e( 'Collapse all', 'vred-geo-maps' ); ?></button>
			</div>
		</div>

		<div class="vred-geo-admin__notice" data-vred-geo-status aria-live="polite"></div>
		<div class="vred-geo-admin__cards" data-vred-geo-sortable="type">
			<?php foreach ( $types as $type ) : ?>
				<?php self::render_type_card( $type ); ?>
			<?php endforeach; ?>
			<p class="vred-geo-admin__empty" data-vred-geo-empty <?php echo $types ? 'hidden' : ''; ?>><?php esc_html_e( 'No location types yet. They are optional.', 'vred-geo-maps' ); ?></p>
		</div>
		<?php
	}

	/** Render one type card. */
	private static function render_type_card( \WP_Term $type ): void {
		$marker_image_id = Data::sanitize_attachment_id( get_term_meta( $type->term_id, Data::TERM_META_MARKER_IMAGE_ID, true ) );
		$marker_color = (string) get_term_meta( $type->term_id, Data::TERM_META_MARKER_COLOR, true );
		$marker_size  = (string) get_term_meta( $type->term_id, Data::TERM_META_MARKER_SIZE, true );
		?>
		<article class="vred-geo-admin-card" data-vred-geo-card data-id="<?php echo esc_attr( (string) $type->term_id ); ?>" data-search="<?php echo esc_attr( $type->name . ' ' . $type->slug ); ?>">
			<header class="vred-geo-admin-card__header">
				<button type="button" class="vred-geo-admin-card__drag" data-vred-geo-drag-handle aria-label="<?php echo esc_attr__( 'Drag to reorder', 'vred-geo-maps' ); ?>">⋮⋮</button>
				<button type="button" class="vred-geo-admin-card__toggle" data-vred-geo-toggle aria-expanded="false">
					<strong data-vred-geo-summary-title><?php echo esc_html( $type->name ); ?></strong>
					<span class="vred-geo-admin-card__summary" data-vred-geo-summary-meta><?php echo esc_html( $type->slug ); ?> · <?php echo esc_html( sprintf( _n( '%d location', '%d locations', (int) $type->count, 'vred-geo-maps' ), (int) $type->count ) ); ?></span>
				</button>
				<div class="vred-geo-admin-card__actions">
					<button type="button" class="vred-geo-admin-card__action vred-geo-admin-card__action--delete" data-vred-geo-delete><?php esc_html_e( 'Delete', 'vred-geo-maps' ); ?></button>
				</div>
			</header>
			<div class="vred-geo-admin-card__body" data-vred-geo-card-body hidden>
				<form data-vred-geo-edit-form="type">
					<input type="hidden" name="id" value="<?php echo esc_attr( (string) $type->term_id ); ?>">
					<section class="vred-geo-admin-block vred-geo-admin-block--compact">
						<header class="vred-geo-admin-block__header">
							<h3 class="vred-geo-admin-block__title"><?php esc_html_e( 'Basic information', 'vred-geo-maps' ); ?></h3>
						</header>
						<div class="vred-geo-admin-block__content">
							<div class="vred-geo-admin-grid">
								<?php self::text_field( 'name', __( 'Name', 'vred-geo-maps' ), $type->name, true ); ?>
								<?php self::text_field( 'slug', __( 'Slug', 'vred-geo-maps' ), $type->slug, true ); ?>
							</div>
						</div>
					</section>
					<?php self::render_marker_override_fields( $marker_image_id, $marker_color, $marker_size ); ?>
					<div class="vred-geo-admin-card__footer">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save changes', 'vred-geo-maps' ); ?></button>
						<span class="vred-geo-admin-card__save-status" data-vred-geo-save-status aria-live="polite"></span>
					</div>
				</form>
			</div>
		</article>
		<?php
	}

	/** Render shared marker inheritance fields. */
	private static function render_marker_override_fields( int $image_id, string $color, string $size ): void {
		$color_value = sanitize_hex_color( $color ) ?: '#2f6fed';
		?>
		<section class="vred-geo-admin-block vred-geo-admin-block--compact">
			<header class="vred-geo-admin-block__header">
				<h3 class="vred-geo-admin-block__title"><?php esc_html_e( 'Custom marker', 'vred-geo-maps' ); ?></h3>
			</header>
			<div class="vred-geo-admin-block__content">
				<div class="vred-geo-admin-overrides">
					<div class="vred-geo-admin-override">
						<label class="vred-geo-admin-override__label">
							<input type="checkbox" name="marker_image_enabled" value="1" data-vred-geo-override-toggle <?php checked( $image_id > 0 ); ?>>
							<span><?php esc_html_e( 'Icon', 'vred-geo-maps' ); ?></span>
						</label>
						<div class="vred-geo-admin-override__control">
							<?php self::render_media_field( 'marker_image_id', $image_id, true ); ?>
						</div>
					</div>
					<div class="vred-geo-admin-override">
						<label class="vred-geo-admin-override__label">
							<input type="checkbox" name="marker_size_enabled" value="1" data-vred-geo-override-toggle <?php checked( '' !== $size && (int) $size > 0 ); ?>>
							<span><?php esc_html_e( 'Size', 'vred-geo-maps' ); ?></span>
						</label>
						<div class="vred-geo-admin-override__control">
							<div class="vred-geo-admin-input-group">
								<input type="number" min="16" max="96" step="1" name="marker_size" value="<?php echo esc_attr( (string) ( (int) $size > 0 ? (int) $size : 34 ) ); ?>" data-vred-geo-override-input <?php disabled( '' === $size || (int) $size <= 0 ); ?>>
								<span>px</span>
							</div>
						</div>
					</div>
					<div class="vred-geo-admin-override">
						<label class="vred-geo-admin-override__label">
							<input type="checkbox" name="marker_color_enabled" value="1" data-vred-geo-override-toggle <?php checked( '' !== $color ); ?>>
							<span><?php esc_html_e( 'Color', 'vred-geo-maps' ); ?></span>
						</label>
						<div class="vred-geo-admin-override__control">
							<div class="vred-geo-admin-color" data-vred-geo-color-control>
								<input type="text" name="marker_color" value="<?php echo esc_attr( $color_value ); ?>" maxlength="7" spellcheck="false" data-vred-geo-color-text data-vred-geo-override-input <?php disabled( '' === $color ); ?>>
								<input type="color" value="<?php echo esc_attr( $color_value ); ?>" aria-label="<?php echo esc_attr__( 'Color', 'vred-geo-maps' ); ?>" data-vred-geo-color-picker data-vred-geo-override-control <?php disabled( '' === $color ); ?>>
							</div>
						</div>
					</div>
				</div>
			</div>
		</section>
		<?php
	}

	/** Render global settings. */
	private static function render_settings_tab(): void {
		$settings    = Data::get_settings();
		$update_data = Updater::get_settings_update_data();
		$uses_carto  = in_array( $settings['tile_provider'], array( 'carto_positron', 'carto_positron_nolabels', 'carto_voyager' ), true );
		?>
		<form method="post" action="options.php" class="vred-geo-admin-settings">
			<?php settings_fields( 'vred_geo_maps_settings' ); ?>

			<?php if ( self::$settings_updated ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Settings saved.' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! empty( $_GET['vred-geo-maps-updates-refreshed'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Update check completed.', 'vred-geo-maps' ); ?></p></div>
			<?php endif; ?>
			<section class="vred-geo-admin-block">
				<header class="vred-geo-admin-block__header">
					<h2 class="vred-geo-admin-block__title"><?php esc_html_e( 'Plugin', 'vred-geo-maps' ); ?></h2>
					<div class="vred-geo-admin-block__actions">
						<span class="description"><?php echo esc_html( sprintf( __( 'Version %s', 'vred-geo-maps' ), $update_data['current_version'] ) ); ?></span>
						<?php if ( ! empty( $update_data['has_update'] ) ) : ?>
							<span class="vred-geo-admin-status-badge is-update"><?php echo esc_html( sprintf( __( 'Update available: %s', 'vred-geo-maps' ), $update_data['remote_version'] ) ); ?></span>
						<?php endif; ?>
						<a href="<?php echo esc_url( Updater::get_refresh_url() ); ?>"><?php esc_html_e( 'Check updates', 'vred-geo-maps' ); ?></a>
					</div>
				</header>
			</section>

			<section class="vred-geo-admin-block">
				<header class="vred-geo-admin-block__header">
					<h2 class="vred-geo-admin-block__title"><?php esc_html_e( 'Map', 'vred-geo-maps' ); ?></h2>
				</header>
				<div class="vred-geo-admin-block__content">
					<div class="vred-geo-admin-settings-fields">
						<label class="vred-geo-admin-field vred-geo-admin-field--select">
							<span><?php esc_html_e( 'Tile provider', 'vred-geo-maps' ); ?></span>
							<select name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[tile_provider]" data-vred-geo-tile-provider-setting>
								<option value="openstreetmap" <?php selected( $settings['tile_provider'], 'openstreetmap' ); ?>>OpenStreetMap</option>
								<option value="carto_positron" <?php selected( $settings['tile_provider'], 'carto_positron' ); ?>><?php esc_html_e( 'CARTO Positron — API key required', 'vred-geo-maps' ); ?></option>
								<option value="carto_positron_nolabels" <?php selected( $settings['tile_provider'], 'carto_positron_nolabels' ); ?>><?php esc_html_e( 'CARTO Positron No Labels — API key required', 'vred-geo-maps' ); ?></option>
								<option value="carto_voyager" <?php selected( $settings['tile_provider'], 'carto_voyager' ); ?>><?php esc_html_e( 'CARTO Voyager — API key required', 'vred-geo-maps' ); ?></option>
							</select>
						</label>
						<label class="vred-geo-admin-field vred-geo-admin-field--select">
							<span><?php esc_html_e( 'Appearance', 'vred-geo-maps' ); ?></span>
							<select name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[appearance]">
								<?php foreach ( self::get_appearance_options() as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['appearance'], $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<?php self::settings_number( 'map_height', __( 'Map height', 'vred-geo-maps' ), $settings['map_height'], 240, 900, 'px' ); ?>
						<?php self::settings_number( 'map_border_radius', __( 'Border radius', 'vred-geo-maps' ), $settings['map_border_radius'], 0, 40, 'px' ); ?>
						<?php self::settings_number( 'initial_zoom', __( 'Initial zoom', 'vred-geo-maps' ), $settings['initial_zoom'], 1, 19 ); ?>
					</div>
					<div class="vred-geo-admin-settings-fields" data-vred-geo-carto-api-key-field<?php echo $uses_carto ? '' : ' hidden'; ?>>
						<div class="vred-geo-admin-field vred-geo-admin-field--select">
							<label for="vred-geo-carto-api-key"><span><?php esc_html_e( 'CARTO API key', 'vred-geo-maps' ); ?></span></label>
							<input type="text" id="vred-geo-carto-api-key" name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[carto_api_key]" value="<?php echo esc_attr( $settings['carto_api_key'] ); ?>" autocomplete="off" spellcheck="false">
							<p class="description"><?php esc_html_e( 'CARTO maps require your own API key.', 'vred-geo-maps' ); ?> <a href="https://carto.com/basemaps/apikey/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Request API key', 'vred-geo-maps' ); ?></a></p>
						</div>
					</div>
					<div class="vred-geo-admin-checks">
						<?php self::settings_checkbox( 'auto_fit', __( 'Automatically fit visible locations', 'vred-geo-maps' ), $settings['auto_fit'] ); ?>
						<?php self::settings_checkbox( 'clustering', __( 'Cluster nearby markers', 'vred-geo-maps' ), $settings['clustering'] ); ?>
					</div>
				</div>
			</section>

			<section class="vred-geo-admin-block">
				<header class="vred-geo-admin-block__header">
					<h2 class="vred-geo-admin-block__title"><label><input type="checkbox" name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[show_list]" value="1" <?php checked( ! empty( $settings['show_list'] ) ); ?> data-vred-geo-show-list-setting> <span><?php esc_html_e( 'Locations list', 'vred-geo-maps' ); ?></span></label></h2>
				</header>
				<div class="vred-geo-admin-block__content" data-vred-geo-list-settings<?php echo ! empty( $settings['show_list'] ) ? '' : ' hidden'; ?>>
					<div class="vred-geo-admin-settings-fields">
						<label class="vred-geo-admin-field vred-geo-admin-field--select">
							<span><?php esc_html_e( 'List style', 'vred-geo-maps' ); ?></span>
							<select name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[list_style]" data-vred-geo-list-style-setting>
								<option value="cards" <?php selected( $settings['list_style'], 'cards' ); ?>><?php esc_html_e( 'Cards', 'vred-geo-maps' ); ?></option>
								<option value="compact" <?php selected( $settings['list_style'], 'compact' ); ?>><?php esc_html_e( 'Compact', 'vred-geo-maps' ); ?></option>
								<option value="legend" <?php selected( $settings['list_style'], 'legend' ); ?>><?php esc_html_e( 'Legend', 'vred-geo-maps' ); ?></option>
								<option value="grouped" <?php selected( $settings['list_style'], 'grouped' ); ?>><?php esc_html_e( 'Grouped', 'vred-geo-maps' ); ?></option>
							</select>
						</label>
						<label class="vred-geo-admin-field vred-geo-admin-field--select" data-vred-geo-list-type-indicator-field<?php echo in_array( $settings['list_style'], array( 'legend', 'grouped' ), true ) ? '' : ' hidden'; ?>>
							<span><?php esc_html_e( 'Type indicator', 'vred-geo-maps' ); ?></span>
							<select name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[list_type_indicator]">
								<option value="auto" <?php selected( $settings['list_type_indicator'], 'auto' ); ?>><?php esc_html_e( 'Automatic', 'vred-geo-maps' ); ?></option>
								<option value="icon" <?php selected( $settings['list_type_indicator'], 'icon' ); ?>><?php esc_html_e( 'Icon', 'vred-geo-maps' ); ?></option>
								<option value="color" <?php selected( $settings['list_type_indicator'], 'color' ); ?>><?php esc_html_e( 'Color', 'vred-geo-maps' ); ?></option>
							</select>
						</label>
						<label class="vred-geo-admin-field vred-geo-admin-field--select">
							<span><?php esc_html_e( 'List position', 'vred-geo-maps' ); ?></span>
							<select name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[list_position]" data-vred-geo-list-position-setting>
								<option value="left" <?php selected( $settings['list_position'], 'left' ); ?>><?php esc_html_e( 'Left', 'vred-geo-maps' ); ?></option>
								<option value="right" <?php selected( $settings['list_position'], 'right' ); ?>><?php esc_html_e( 'Right', 'vred-geo-maps' ); ?></option>
								<option value="top" <?php selected( $settings['list_position'], 'top' ); ?>><?php esc_html_e( 'Top', 'vred-geo-maps' ); ?></option>
								<option value="bottom" <?php selected( $settings['list_position'], 'bottom' ); ?>><?php esc_html_e( 'Bottom', 'vred-geo-maps' ); ?></option>
							</select>
						</label>
					</div>
					<div class="vred-geo-admin-settings-fields">
						<label class="vred-geo-admin-field vred-geo-admin-field--number" data-vred-geo-list-width-field<?php echo in_array( $settings['list_position'], array( 'left', 'right' ), true ) ? '' : ' hidden'; ?>>
							<span><?php esc_html_e( 'List width', 'vred-geo-maps' ); ?></span>
							<div class="vred-geo-admin-input-group">
								<input type="number" min="260" max="560" step="1" name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[list_width]" value="<?php echo esc_attr( (string) $settings['list_width'] ); ?>">
								<span>px</span>
							</div>
						</label>
						<?php self::settings_number( 'gap', __( 'Spacing', 'vred-geo-maps' ), $settings['gap'], 0, 80, 'px' ); ?>
						<?php self::settings_number( 'card_radius', __( 'Card radius', 'vred-geo-maps' ), $settings['card_radius'], 0, 40, 'px' ); ?>
					</div>
					<div class="vred-geo-admin-checks">
						<?php self::settings_checkbox( 'show_directions_link', __( 'Show “Get directions” link', 'vred-geo-maps' ), $settings['show_directions_link'] ); ?>
					</div>
				</div>
			</section>

			<section class="vred-geo-admin-block">
				<header class="vred-geo-admin-block__header">
					<h2 class="vred-geo-admin-block__title"><label><input type="checkbox" name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[show_map_legend]" value="1" <?php checked( ! empty( $settings['show_map_legend'] ) ); ?> data-vred-geo-show-map-legend-setting> <span><?php esc_html_e( 'On-map legend', 'vred-geo-maps' ); ?></span></label></h2>
				</header>
				<div class="vred-geo-admin-block__content" data-vred-geo-map-legend-settings<?php echo ! empty( $settings['show_map_legend'] ) ? '' : ' hidden'; ?>>
					<div class="vred-geo-admin-settings-fields">
						<label class="vred-geo-admin-field vred-geo-admin-field--select">
							<span><?php esc_html_e( 'Position', 'vred-geo-maps' ); ?></span>
							<select name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[map_legend_position]">
								<option value="top-left" <?php selected( $settings['map_legend_position'], 'top-left' ); ?>><?php esc_html_e( 'Top left', 'vred-geo-maps' ); ?></option>
								<option value="top-right" <?php selected( $settings['map_legend_position'], 'top-right' ); ?>><?php esc_html_e( 'Top right', 'vred-geo-maps' ); ?></option>
								<option value="bottom-left" <?php selected( $settings['map_legend_position'], 'bottom-left' ); ?>><?php esc_html_e( 'Bottom left', 'vred-geo-maps' ); ?></option>
								<option value="bottom-right" <?php selected( $settings['map_legend_position'], 'bottom-right' ); ?>><?php esc_html_e( 'Bottom right', 'vred-geo-maps' ); ?></option>
							</select>
						</label>
						<label class="vred-geo-admin-field vred-geo-admin-field--select">
							<span><?php esc_html_e( 'Type indicator', 'vred-geo-maps' ); ?></span>
							<select name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[map_legend_type_indicator]">
								<option value="auto" <?php selected( $settings['map_legend_type_indicator'], 'auto' ); ?>><?php esc_html_e( 'Automatic', 'vred-geo-maps' ); ?></option>
								<option value="icon" <?php selected( $settings['map_legend_type_indicator'], 'icon' ); ?>><?php esc_html_e( 'Icon', 'vred-geo-maps' ); ?></option>
								<option value="color" <?php selected( $settings['map_legend_type_indicator'], 'color' ); ?>><?php esc_html_e( 'Color', 'vred-geo-maps' ); ?></option>
							</select>
						</label>
						<?php self::settings_number( 'map_legend_visible_locations_per_type', __( 'Visible locations per type', 'vred-geo-maps' ), $settings['map_legend_visible_locations_per_type'], 1, 20 ); ?>
						<?php self::settings_number( 'map_legend_border_radius', __( 'Legend border radius', 'vred-geo-maps' ), $settings['map_legend_border_radius'], 0, 40, 'px' ); ?>
						<?php self::settings_number( 'map_legend_background_transparency', __( 'Background transparency', 'vred-geo-maps' ), $settings['map_legend_background_transparency'], 0, 100, '%' ); ?>
					</div>
				</div>
			</section>

			<section class="vred-geo-admin-block">
				<header class="vred-geo-admin-block__header">
					<h2 class="vred-geo-admin-block__title"><label><input type="checkbox" name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[show_filters]" value="1" <?php checked( ! empty( $settings['show_filters'] ) ); ?> data-vred-geo-show-filters-setting> <span><?php esc_html_e( 'Filters', 'vred-geo-maps' ); ?></span></label></h2>
				</header>
				<div class="vred-geo-admin-block__content" data-vred-geo-filter-settings<?php echo ! empty( $settings['show_filters'] ) ? '' : ' hidden'; ?>>
					<div class="vred-geo-admin-settings-fields">
						<label class="vred-geo-admin-field vred-geo-admin-field--select">
							<span><?php esc_html_e( 'Filters position', 'vred-geo-maps' ); ?></span>
							<select name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[filters_position]" data-vred-geo-filters-position-setting>
								<option value="top" <?php selected( $settings['filters_position'], 'top' ); ?>><?php esc_html_e( 'Above the map', 'vred-geo-maps' ); ?></option>
								<option value="panel" <?php selected( $settings['filters_position'], 'panel' ); ?>><?php esc_html_e( 'In the list area', 'vred-geo-maps' ); ?></option>
								<option value="bottom" <?php selected( $settings['filters_position'], 'bottom' ); ?>><?php esc_html_e( 'Below the map', 'vred-geo-maps' ); ?></option>
								<option value="map" <?php selected( $settings['filters_position'], 'map' ); ?>><?php esc_html_e( 'On the map', 'vred-geo-maps' ); ?></option>
							</select>
						</label>
						<label class="vred-geo-admin-field vred-geo-admin-field--select" data-vred-geo-filters-map-position-field<?php echo 'map' === $settings['filters_position'] ? '' : ' hidden'; ?>>
							<span><?php esc_html_e( 'Map position', 'vred-geo-maps' ); ?></span>
							<select name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[filters_map_position]">
								<option value="top-left" <?php selected( $settings['filters_map_position'], 'top-left' ); ?>><?php esc_html_e( 'Top left', 'vred-geo-maps' ); ?></option>
								<option value="top-right" <?php selected( $settings['filters_map_position'], 'top-right' ); ?>><?php esc_html_e( 'Top right', 'vred-geo-maps' ); ?></option>
								<option value="bottom-left" <?php selected( $settings['filters_map_position'], 'bottom-left' ); ?>><?php esc_html_e( 'Bottom left', 'vred-geo-maps' ); ?></option>
								<option value="bottom-right" <?php selected( $settings['filters_map_position'], 'bottom-right' ); ?>><?php esc_html_e( 'Bottom right', 'vred-geo-maps' ); ?></option>
							</select>
						</label>
						<label class="vred-geo-admin-field vred-geo-admin-field--number" data-vred-geo-filters-transparency-field<?php echo 'map' === $settings['filters_position'] ? '' : ' hidden'; ?>>
							<span><?php esc_html_e( 'Background transparency', 'vred-geo-maps' ); ?></span>
							<div class="vred-geo-admin-input-group">
								<input type="number" min="0" max="100" step="1" name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[filters_background_transparency]" value="<?php echo esc_attr( (string) $settings['filters_background_transparency'] ); ?>">
								<span>%</span>
							</div>
						</label>
						<?php self::settings_number( 'filters_radius', __( 'Filter radius', 'vred-geo-maps' ), $settings['filters_radius'], 0, 40, 'px' ); ?>
					</div>
					<div class="vred-geo-admin-checks">
						<?php self::settings_checkbox( 'show_search', __( 'Show search', 'vred-geo-maps' ), $settings['show_search'] ); ?>
						<?php self::settings_checkbox( 'show_type_filter', __( 'Show location type', 'vred-geo-maps' ), $settings['show_type_filter'] ); ?>
						<?php self::settings_checkbox( 'show_city_filter', __( 'Show city', 'vred-geo-maps' ), $settings['show_city_filter'] ); ?>
						<?php self::settings_checkbox( 'show_region_filter', __( 'Show Province / region', 'vred-geo-maps' ), $settings['show_region_filter'] ); ?>
						<?php self::settings_checkbox( 'show_country_filter', __( 'Show country', 'vred-geo-maps' ), $settings['show_country_filter'] ); ?>
					</div>
				</div>
			</section>

			<section class="vred-geo-admin-block">
				<header class="vred-geo-admin-block__header">
					<h2 class="vred-geo-admin-block__title"><?php esc_html_e( 'Default marker', 'vred-geo-maps' ); ?></h2>
				</header>
				<div class="vred-geo-admin-block__content">
					<p><?php esc_html_e( 'Location Types and individual Locations can override these properties independently.', 'vred-geo-maps' ); ?></p>
					<div class="vred-geo-admin-settings-fields">
						<div class="vred-geo-admin-field vred-geo-admin-field--media">
							<span><?php esc_html_e( 'Icon', 'vred-geo-maps' ); ?></span>
							<?php self::render_media_field( VRED_GEO_MAPS_OPTION . '[marker_image_id]', Data::sanitize_attachment_id( $settings['marker_image_id'] ?? 0 ) ); ?>
						</div>
						<?php self::settings_number( 'marker_size', __( 'Size', 'vred-geo-maps' ), $settings['marker_size'], 16, 96, 'px' ); ?>
						<?php self::settings_color( 'marker_color', __( 'Color', 'vred-geo-maps' ), $settings['marker_color'] ); ?>
					</div>
				</div>
			</section>

			<section class="vred-geo-admin-block">
				<header class="vred-geo-admin-block__header">
					<h2 class="vred-geo-admin-block__title"><?php esc_html_e( 'Popup', 'vred-geo-maps' ); ?></h2>
				</header>
				<div class="vred-geo-admin-block__content">
					<div class="vred-geo-admin-settings-fields">
						<?php self::settings_color( 'popup_text_color', __( 'Text color', 'vred-geo-maps' ), $settings['popup_text_color'] ); ?>
						<?php self::settings_color( 'popup_background', __( 'Background', 'vred-geo-maps' ), $settings['popup_background'] ); ?>
						<?php self::settings_color( 'popup_border_color', __( 'Border color', 'vred-geo-maps' ), $settings['popup_border_color'] ); ?>
						<?php self::settings_number( 'popup_border_radius', __( 'Border radius', 'vred-geo-maps' ), $settings['popup_border_radius'], 0, 40, 'px' ); ?>
						<?php self::settings_number( 'popup_width', __( 'Maximum width', 'vred-geo-maps' ), $settings['popup_width'], 180, 520, 'px' ); ?>
					</div>
				</div>
			</section>

			<section class="vred-geo-admin-block">
				<header class="vred-geo-admin-block__header">
					<h2 class="vred-geo-admin-block__title"><?php esc_html_e( 'Data', 'vred-geo-maps' ); ?></h2>
				</header>
				<div class="vred-geo-admin-block__content">
					<div class="vred-geo-admin-checks">
						<?php self::settings_checkbox( 'delete_data_on_uninstall', __( 'Delete all data on uninstall', 'vred-geo-maps' ), $settings['delete_data_on_uninstall'] ); ?>
					</div>
					<p class="description"><?php esc_html_e( 'When enabled, uninstalling VRED Geo Maps permanently removes all Locations, Location Types and plugin settings. Deactivating the plugin never removes data.', 'vred-geo-maps' ); ?></p>
				</div>
			</section>

			<section class="vred-geo-admin-block">
				<header class="vred-geo-admin-block__header">
					<h2 class="vred-geo-admin-block__title"><?php esc_html_e( 'Shortcode', 'vred-geo-maps' ); ?></h2>
				</header>
				<div class="vred-geo-admin-block__content">
					<p><?php esc_html_e( 'Use the shortcode anywhere WordPress processes shortcodes. Global settings are inherited unless an option below overrides them.', 'vred-geo-maps' ); ?></p>
					<div class="vred-geo-admin-shortcodes">
						<div class="vred-geo-admin-shortcode">
							<strong><?php esc_html_e( 'Basic map', 'vred-geo-maps' ); ?></strong>
							<code>[vred_geo_map]</code>
						</div>
						<div class="vred-geo-admin-shortcode">
							<strong><?php esc_html_e( 'Filter by Location Type slugs', 'vred-geo-maps' ); ?></strong>
							<code>[vred_geo_map types="campings,parking"]</code>
						</div>
						<div class="vred-geo-admin-shortcode">
							<strong><?php esc_html_e( 'Show specific location IDs', 'vred-geo-maps' ); ?></strong>
							<code>[vred_geo_map ids="12,34,56"]</code>
						</div>
						<div class="vred-geo-admin-shortcode">
							<strong><?php esc_html_e( 'Common options', 'vred-geo-maps' ); ?></strong>
							<code>[vred_geo_map list="no" filters="no" cluster="no" position="right"]</code>
						</div>
					</div>
					<p class="description"><code>ids</code>, <code>types</code>, <code>list="yes|no"</code>, <code>filters="yes|no"</code>, <code>cluster="yes|no"</code>, <code>position="left|right|top|bottom"</code></p>
				</div>
			</section>

			<?php submit_button( __( 'Save settings', 'vred-geo-maps' ) ); ?>
		</form>
		<?php
	}

	/** Add a location through AJAX. */
	public static function ajax_add_location(): void {
		self::verify_ajax();
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';

		if ( '' === $title ) {
			wp_send_json_error( array( 'message' => __( 'A location name is required.', 'vred-geo-maps' ) ), 400 );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => Data::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
				'menu_order'  => Data::get_next_location_order(),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => $post_id->get_error_message() ), 500 );
		}

		update_post_meta( $post_id, Data::META_ACTION, 'popup' );
		$post = get_post( $post_id );

		ob_start();
		if ( $post instanceof \WP_Post ) {
			self::render_location_card( $post, Data::get_types() );
		}

		wp_send_json_success(
			array(
				'id'   => (int) $post_id,
				'html' => (string) ob_get_clean(),
			)
		);
	}

	/** Geocode one address into coordinates for the location editor. */
	public static function ajax_geocode_address(): void {
		self::verify_ajax();
		$address = self::post_text( 'address' );

		if ( strlen( $address ) < 3 ) {
			wp_send_json_error( array( 'message' => __( 'Enter a more complete address.', 'vred-geo-maps' ) ), 400 );
		}

		$language  = str_replace( '_', '-', determine_locale() );
		$cache_key = 'vred_geo_maps_geocode_v2_' . md5( strtolower( $address . '|' . $language ) );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) && isset( $cached['latitude'], $cached['longitude'] ) && array_key_exists( 'city', $cached ) && array_key_exists( 'region', $cached ) && array_key_exists( 'country', $cached ) ) {
			wp_send_json_success( $cached );
		}

		$last_request = (float) get_transient( 'vred_geo_maps_nominatim_last_request' );
		$elapsed      = microtime( true ) - $last_request;

		if ( $last_request > 0 && $elapsed < 1 ) {
			usleep( (int) ( ( 1 - $elapsed ) * 1000000 ) );
		}

		set_transient( 'vred_geo_maps_nominatim_last_request', microtime( true ), 2 );

		$url = add_query_arg(
			array(
				'q'              => $address,
				'format'         => 'jsonv2',
				'limit'          => 1,
				'addressdetails' => 1,
			),
			'https://nominatim.openstreetmap.org/search'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'headers'     => array(
					'Accept'          => 'application/json',
					'Accept-Language' => $language,
					'User-Agent'      => 'VRED Geo Maps/' . VRED_GEO_MAPS_VERSION . ' (+https://viviendoenred.com)',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			wp_send_json_error( array( 'message' => __( 'Coordinates could not be retrieved right now.', 'vred-geo-maps' ) ), 502 );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || empty( $data[0]['lat'] ) || empty( $data[0]['lon'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No coordinates were found for this address.', 'vred-geo-maps' ) ), 404 );
		}

		$latitude  = Data::sanitize_coordinate( $data[0]['lat'], -90, 90 );
		$longitude = Data::sanitize_coordinate( $data[0]['lon'], -180, 180 );
		$details   = is_array( $data[0]['address'] ?? null ) ? $data[0]['address'] : array();

		if ( null === $latitude || null === $longitude ) {
			wp_send_json_error( array( 'message' => __( 'The geocoding service returned invalid coordinates.', 'vred-geo-maps' ) ), 502 );
		}

		$result = array(
			'latitude'  => (string) $latitude,
			'longitude' => (string) $longitude,
			'city'      => self::get_nominatim_address_value( $details, array( 'city', 'town', 'village', 'municipality', 'hamlet', 'locality' ) ),
			'region'    => self::get_nominatim_address_value( $details, array( 'state', 'province', 'region', 'state_district', 'county' ) ),
			'country'   => self::get_nominatim_address_value( $details, array( 'country' ) ),
		);

		set_transient( $cache_key, $result, 30 * DAY_IN_SECONDS );
		wp_send_json_success( $result );
	}

	/** Save a location through AJAX. */
	public static function ajax_save_location(): void {
		self::verify_ajax();
		$post_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$post    = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || Data::POST_TYPE !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Location not found.', 'vred-geo-maps' ) ), 404 );
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';

		if ( '' === $title ) {
			wp_send_json_error( array( 'message' => __( 'A location name is required.', 'vred-geo-maps' ) ), 400 );
		}

		$result = wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ), true );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		$address       = self::post_text( 'address' );
		$city          = self::post_text( 'city' );
		$region        = self::post_text( 'region' );
		$country       = self::post_text( 'country' );
		$latitude_raw  = self::post_text( 'latitude' );
		$longitude_raw = self::post_text( 'longitude' );
		$latitude      = Data::sanitize_coordinate( $latitude_raw, -90, 90 );
		$longitude     = Data::sanitize_coordinate( $longitude_raw, -180, 180 );

		if ( ( '' !== $latitude_raw || '' !== $longitude_raw ) && ( null === $latitude || null === $longitude ) ) {
			wp_send_json_error( array( 'message' => __( 'Latitude and longitude must both be valid decimal coordinates.', 'vred-geo-maps' ) ), 400 );
		}

		update_post_meta( $post_id, Data::META_ADDRESS, $address );
		self::update_coordinate_meta( $post_id, Data::META_LATITUDE, $latitude );
		self::update_coordinate_meta( $post_id, Data::META_LONGITUDE, $longitude );
		update_post_meta( $post_id, Data::META_CITY, $city );
		update_post_meta( $post_id, Data::META_REGION, $region );
		update_post_meta( $post_id, Data::META_COUNTRY, $country );
		delete_post_meta( $post_id, Data::META_SUBTITLE );
		update_post_meta( $post_id, Data::META_PHONE, self::post_text( 'phone' ) );
		update_post_meta( $post_id, Data::META_EMAIL, sanitize_email( self::post_text( 'email' ) ) );
		update_post_meta( $post_id, Data::META_WEBSITE, esc_url_raw( self::post_text( 'website' ) ) );

		$action       = self::post_text( 'marker_action' );
		$action       = in_array( $action, array( 'none', 'popup', 'link' ), true ) ? $action : 'popup';
		$popup_custom = isset( $_POST['popup_custom'] ) ? 1 : 0;
		update_post_meta( $post_id, Data::META_ACTION, $action );
		update_post_meta( $post_id, Data::META_POPUP_CUSTOM, $popup_custom );
		update_post_meta( $post_id, Data::META_POPUP_CONTENT, wp_kses_post( self::post_raw( 'popup_content' ) ) );
		delete_post_meta( $post_id, Data::META_LINK_URL );
		delete_post_meta( $post_id, Data::META_LINK_TARGET );

		self::save_marker_post_meta( $post_id );

		$type_id = isset( $_POST['type_id'] ) ? absint( $_POST['type_id'] ) : 0;

		if ( $type_id > 0 && term_exists( $type_id, Data::TAXONOMY ) ) {
			wp_set_object_terms( $post_id, array( $type_id ), Data::TAXONOMY, false );
		} else {
			wp_set_object_terms( $post_id, array(), Data::TAXONOMY, false );
			$type_id = 0;
		}

		$type = $type_id > 0 ? get_term( $type_id, Data::TAXONOMY ) : null;
		$type = $type instanceof \WP_Term ? $type : null;

		wp_send_json_success(
			array(
				'message' => __( 'Location saved.', 'vred-geo-maps' ),
				'summary' => array(
					'title' => $title,
					'meta'  => self::get_location_summary( $address, $type ),
					'search' => trim( implode( ' ', array( $title, $address, $city, $region, $country, $type ? $type->name : '' ) ) ),
				),
			)
		);
	}

	/** Duplicate one location. */
	public static function ajax_duplicate_location(): void {
		self::verify_ajax();
		$post_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$post    = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || Data::POST_TYPE !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Location not found.', 'vred-geo-maps' ) ), 404 );
		}

		$new_id = wp_insert_post(
			array(
				'post_type'   => Data::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf( __( '%s copy', 'vred-geo-maps' ), $post->post_title ),
				'menu_order'  => Data::get_next_location_order(),
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			wp_send_json_error( array( 'message' => $new_id->get_error_message() ), 500 );
		}

		$keys = self::get_location_meta_keys();

		foreach ( $keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( '' !== $value && null !== $value ) {
				update_post_meta( $new_id, $key, $value );
			}
		}

		$type = Data::get_location_type( $post_id );
		if ( $type ) {
			wp_set_object_terms( $new_id, array( $type->term_id ), Data::TAXONOMY, false );
		}

		$new_post = get_post( $new_id );
		ob_start();
		if ( $new_post instanceof \WP_Post ) {
			self::render_location_card( $new_post, Data::get_types() );
		}

		wp_send_json_success( array( 'id' => (int) $new_id, 'html' => (string) ob_get_clean() ) );
	}

	/** Permanently delete one location. */
	public static function ajax_delete_location(): void {
		self::verify_ajax();
		$post_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$post    = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || Data::POST_TYPE !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Location not found.', 'vred-geo-maps' ) ), 404 );
		}

		if ( ! wp_delete_post( $post_id, true ) ) {
			wp_send_json_error( array( 'message' => __( 'The location could not be deleted.', 'vred-geo-maps' ) ), 500 );
		}

		wp_send_json_success();
	}

	/** Persist location manual order. */
	public static function ajax_reorder_locations(): void {
		self::verify_ajax();
		$order = isset( $_POST['order'] ) && is_array( $_POST['order'] ) ? array_values( array_filter( array_map( 'absint', $_POST['order'] ) ) ) : array();

		foreach ( $order as $index => $post_id ) {
			$post = get_post( $post_id );
			if ( $post instanceof \WP_Post && Data::POST_TYPE === $post->post_type ) {
				wp_update_post( array( 'ID' => $post_id, 'menu_order' => $index ) );
			}
		}

		wp_send_json_success();
	}

	/** Add a location type. */
	public static function ajax_add_type(): void {
		self::verify_ajax();
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'A type name is required.', 'vred-geo-maps' ) ), 400 );
		}

		$next_order = Data::get_next_type_order();
		$result     = wp_insert_term( $name, Data::TAXONOMY );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		$term_id = (int) $result['term_id'];
		update_term_meta( $term_id, Data::TERM_META_ORDER, $next_order );
		$term = get_term( $term_id, Data::TAXONOMY );

		ob_start();
		if ( $term instanceof \WP_Term ) {
			self::render_type_card( $term );
		}

		wp_send_json_success( array( 'id' => $term_id, 'html' => (string) ob_get_clean() ) );
	}

	/** Save one location type. */
	public static function ajax_save_type(): void {
		self::verify_ajax();
		$term_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$term    = get_term( $term_id, Data::TAXONOMY );

		if ( ! $term instanceof \WP_Term ) {
			wp_send_json_error( array( 'message' => __( 'Location type not found.', 'vred-geo-maps' ) ), 404 );
		}

		$name = self::post_text( 'name' );
		$slug = sanitize_title( self::post_text( 'slug' ) );

		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'A type name is required.', 'vred-geo-maps' ) ), 400 );
		}

		$result = wp_update_term(
			$term_id,
			Data::TAXONOMY,
			array(
				'name' => $name,
				'slug' => '' !== $slug ? $slug : sanitize_title( $name ),
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		self::save_marker_term_meta( $term_id );
		$updated = get_term( $term_id, Data::TAXONOMY );

		wp_send_json_success(
			array(
				'message' => __( 'Location type saved.', 'vred-geo-maps' ),
				'summary' => array(
					'title' => $updated instanceof \WP_Term ? $updated->name : $name,
					'meta'  => $updated instanceof \WP_Term ? $updated->slug . ' · ' . sprintf( _n( '%d location', '%d locations', (int) $updated->count, 'vred-geo-maps' ), (int) $updated->count ) : $slug,
					'search'=> $updated instanceof \WP_Term ? $updated->name . ' ' . $updated->slug : $name . ' ' . $slug,
				),
			)
		);
	}

	/** Delete one location type. */
	public static function ajax_delete_type(): void {
		self::verify_ajax();
		$term_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$term    = get_term( $term_id, Data::TAXONOMY );

		if ( ! $term instanceof \WP_Term ) {
			wp_send_json_error( array( 'message' => __( 'Location type not found.', 'vred-geo-maps' ) ), 404 );
		}

		$result = wp_delete_term( $term_id, Data::TAXONOMY );

		if ( is_wp_error( $result ) || false === $result ) {
			$message = is_wp_error( $result ) ? $result->get_error_message() : __( 'The location type could not be deleted.', 'vred-geo-maps' );
			wp_send_json_error( array( 'message' => $message ), 500 );
		}

		wp_send_json_success();
	}

	/** Persist type manual order. */
	public static function ajax_reorder_types(): void {
		self::verify_ajax();
		$order = isset( $_POST['order'] ) && is_array( $_POST['order'] ) ? array_values( array_filter( array_map( 'absint', $_POST['order'] ) ) ) : array();

		foreach ( $order as $index => $term_id ) {
			if ( term_exists( $term_id, Data::TAXONOMY ) ) {
				update_term_meta( $term_id, Data::TERM_META_ORDER, $index );
			}
		}

		wp_send_json_success();
	}

	/** Verify permissions and nonce for all mutations. */
	private static function verify_ajax(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage Geo Maps.', 'vred-geo-maps' ) ), 403 );
		}
	}

	/** Save marker overrides for a location. */
	private static function save_marker_post_meta( int $post_id ): void {
		$image_id = ! empty( $_POST['marker_image_enabled'] ) ? Data::sanitize_attachment_id( self::post_text( 'marker_image_id' ) ) : 0;
		$color    = ! empty( $_POST['marker_color_enabled'] ) ? sanitize_hex_color( self::post_text( 'marker_color' ) ) : '';
		$size     = ! empty( $_POST['marker_size_enabled'] ) ? Data::clamp_int( self::post_text( 'marker_size' ), 16, 96 ) : 0;

		self::update_optional_post_meta( $post_id, Data::META_MARKER_IMAGE_ID, $image_id > 0 ? $image_id : '' );
		self::update_optional_post_meta( $post_id, Data::META_MARKER_COLOR, $color ?: '' );
		self::update_optional_post_meta( $post_id, Data::META_MARKER_SIZE, $size > 0 ? $size : '' );
		delete_post_meta( $post_id, Data::META_MARKER_SVG );
	}

	/** Save marker overrides for a location type. */
	private static function save_marker_term_meta( int $term_id ): void {
		$image_id = ! empty( $_POST['marker_image_enabled'] ) ? Data::sanitize_attachment_id( self::post_text( 'marker_image_id' ) ) : 0;
		$color    = ! empty( $_POST['marker_color_enabled'] ) ? sanitize_hex_color( self::post_text( 'marker_color' ) ) : '';
		$size     = ! empty( $_POST['marker_size_enabled'] ) ? Data::clamp_int( self::post_text( 'marker_size' ), 16, 96 ) : 0;

		self::update_optional_term_meta( $term_id, Data::TERM_META_MARKER_IMAGE_ID, $image_id > 0 ? $image_id : '' );
		self::update_optional_term_meta( $term_id, Data::TERM_META_MARKER_COLOR, $color ?: '' );
		self::update_optional_term_meta( $term_id, Data::TERM_META_MARKER_SIZE, $size > 0 ? $size : '' );
		delete_term_meta( $term_id, Data::TERM_META_MARKER_SVG );
	}

	/** Update or delete one optional post meta value. */
	private static function update_optional_post_meta( int $post_id, string $key, mixed $value ): void {
		if ( '' === $value || null === $value ) {
			delete_post_meta( $post_id, $key );
			return;
		}

		update_post_meta( $post_id, $key, $value );
	}

	/** Update or delete one optional term meta value. */
	private static function update_optional_term_meta( int $term_id, string $key, mixed $value ): void {
		if ( '' === $value || null === $value ) {
			delete_term_meta( $term_id, $key );
			return;
		}

		update_term_meta( $term_id, $key, $value );
	}

	/** Save a valid coordinate or delete an invalid/empty one. */
	private static function update_coordinate_meta( int $post_id, string $key, ?float $value ): void {
		if ( null === $value ) {
			delete_post_meta( $post_id, $key );
			return;
		}

		update_post_meta( $post_id, $key, (string) $value );
	}

	/** Return raw POST text after unslashing. */
	private static function post_raw( string $key ): string {
		return isset( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : '';
	}

	/** Return sanitized POST text. */
	private static function post_text( string $key ): string {
		return sanitize_text_field( self::post_raw( $key ) );
	}

	/** Return the first usable structured address value from Nominatim. */
	private static function get_nominatim_address_value( array $details, array $keys ): string {
		foreach ( $keys as $key ) {
			$value = sanitize_text_field( (string) ( $details[ $key ] ?? '' ) );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/** Return all location meta keys copied by duplicate. */
	private static function get_location_meta_keys(): array {
		return array(
			Data::META_ADDRESS,
			Data::META_LATITUDE,
			Data::META_LONGITUDE,
			Data::META_CITY,
			Data::META_REGION,
			Data::META_COUNTRY,
			Data::META_PHONE,
			Data::META_EMAIL,
			Data::META_WEBSITE,
			Data::META_ACTION,
			Data::META_POPUP_CONTENT,
			Data::META_POPUP_CUSTOM,
			Data::META_MARKER_IMAGE_ID,
			Data::META_MARKER_SVG,
			Data::META_MARKER_COLOR,
			Data::META_MARKER_SIZE,
		);
	}

	/** Build the compact header summary. */
	private static function get_location_summary( string $address, ?\WP_Term $type ): string {
		$parts = array_filter( array( $type ? $type->name : '', $address ) );
		return $parts ? implode( ' · ', $parts ) : __( 'No address or type yet', 'vred-geo-maps' );
	}

	/** Render the address field with direct coordinate lookup. */
	private static function address_field( string $value ): void {
		$help = __( 'Press Enter or click the location icon to find coordinates.', 'vred-geo-maps' );
		?>
		<label class="vred-geo-admin-field vred-geo-admin-field--wide">
			<span class="vred-geo-admin-field__label">
				<?php esc_html_e( 'Address', 'vred-geo-maps' ); ?>
				<span class="dashicons dashicons-editor-help vred-geo-admin-field__help" role="img" aria-label="<?php echo esc_attr( $help ); ?>" title="<?php echo esc_attr( $help ); ?>"></span>
			</span>
			<span class="vred-geo-admin-address">
				<input type="text" name="address" value="<?php echo esc_attr( $value ); ?>" data-vred-geo-address data-original-address="<?php echo esc_attr( $value ); ?>" autocomplete="street-address">
				<button type="button" class="button vred-geo-admin-address__geocode" data-vred-geo-geocode aria-label="<?php echo esc_attr__( 'Find coordinates', 'vred-geo-maps' ); ?>" title="<?php echo esc_attr__( 'Find coordinates', 'vred-geo-maps' ); ?>">
					<span class="dashicons dashicons-location" aria-hidden="true"></span>
				</button>
			</span>
			<small class="vred-geo-admin-field__status" data-vred-geo-geocode-status aria-live="polite"></small>
		</label>
		<?php
	}

	/** Render one Media Library image selector. */
	private static function render_media_field( string $name, int $attachment_id, bool $override = false ): void {
		$attachment_id = Data::sanitize_attachment_id( $attachment_id );
		$url           = Data::get_attachment_url( $attachment_id );
		?>
		<div class="vred-geo-admin-media<?php echo $attachment_id > 0 ? ' has-image' : ''; ?>" data-vred-geo-media>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $attachment_id ); ?>" data-vred-geo-media-id <?php echo $override ? 'data-vred-geo-override-input' : ''; ?>>
			<button type="button" class="vred-geo-admin-media__preview" data-vred-geo-media-preview data-vred-geo-media-select <?php echo $override ? 'data-vred-geo-override-control' : ''; ?> <?php echo $attachment_id > 0 ? '' : 'hidden'; ?> <?php disabled( $override && $attachment_id <= 0 ); ?> aria-label="<?php echo esc_attr__( 'Replace image', 'vred-geo-maps' ); ?>">
				<?php if ( $url ) : ?>
					<img src="<?php echo esc_url( $url ); ?>" alt="">
				<?php endif; ?>
			</button>
			<div class="vred-geo-admin-media__actions">
				<button type="button" class="button" data-vred-geo-media-empty-select data-vred-geo-media-select <?php echo $override ? 'data-vred-geo-override-control' : ''; ?> <?php echo $attachment_id > 0 ? 'hidden' : ''; ?> <?php disabled( $override && $attachment_id <= 0 ); ?>><?php esc_html_e( 'Select image', 'vred-geo-maps' ); ?></button>
				<button type="button" class="vred-geo-admin-media__remove" data-vred-geo-media-remove <?php echo $override ? 'data-vred-geo-override-control' : ''; ?> <?php echo $attachment_id > 0 ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'vred-geo-maps' ); ?></button>
			</div>
		</div>
		<?php
	}

	/** Render a standard text field. */
	private static function text_field( string $name, string $label, string $value, bool $required = false, string $class = '', string $type = 'text' ): void {
		?>
		<label class="vred-geo-admin-field <?php echo esc_attr( $class ); ?>">
			<span><?php echo esc_html( $label ); ?></span>
			<input type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php echo $required ? 'required' : ''; ?>>
		</label>
		<?php
	}

	/** Render a number setting. */
	private static function settings_number( string $key, string $label, mixed $value, int $min, int $max, string $unit = '' ): void {
		?>
		<label class="vred-geo-admin-field vred-geo-admin-field--number<?php echo '' === $unit ? ' vred-geo-admin-field--number-short' : ''; ?>">
			<span><?php echo esc_html( $label ); ?></span>
			<?php if ( '' !== $unit ) : ?>
				<div class="vred-geo-admin-input-group">
					<input type="number" min="<?php echo esc_attr( (string) $min ); ?>" max="<?php echo esc_attr( (string) $max ); ?>" step="1" name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>">
					<span><?php echo esc_html( $unit ); ?></span>
				</div>
			<?php else : ?>
				<input type="number" min="<?php echo esc_attr( (string) $min ); ?>" max="<?php echo esc_attr( (string) $max ); ?>" step="1" name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>">
			<?php endif; ?>
		</label>
		<?php
	}

	/** Render a color setting. */
	private static function settings_color( string $key, string $label, string $value ): void {
		$color = sanitize_hex_color( $value ) ?: '#2f6fed';
		?>
		<label class="vred-geo-admin-field vred-geo-admin-field--color">
			<span><?php echo esc_html( $label ); ?></span>
			<div class="vred-geo-admin-color" data-vred-geo-color-control>
				<input type="text" name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $color ); ?>" maxlength="7" spellcheck="false" data-vred-geo-color-text>
				<input type="color" value="<?php echo esc_attr( $color ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" data-vred-geo-color-picker>
			</div>
		</label>
		<?php
	}

	/** Render a checkbox setting. */
	private static function settings_checkbox( string $key, string $label, mixed $value ): void {
		?>
		<label><input type="checkbox" name="<?php echo esc_attr( VRED_GEO_MAPS_OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $value ) ); ?>> <?php echo esc_html( $label ); ?></label>
		<?php
	}

	/** Appearance labels shared by admin. */
	private static function get_appearance_options(): array {
		return array(
			'default'   => __( 'Default', 'vred-geo-maps' ),
			'grayscale' => __( 'Grayscale', 'vred-geo-maps' ),
			'soft'      => __( 'Soft', 'vred-geo-maps' ),
			'dark'      => __( 'Dark', 'vred-geo-maps' ),
			'contrast'  => __( 'Contrast', 'vred-geo-maps' ),
			'muted'     => __( 'Muted', 'vred-geo-maps' ),
			'warm'      => __( 'Warm', 'vred-geo-maps' ),
			'cool'      => __( 'Cool', 'vred-geo-maps' ),
			'sepia'     => __( 'Sepia', 'vred-geo-maps' ),
			'blueprint' => __( 'Blueprint', 'vred-geo-maps' ),
		);
	}
}
