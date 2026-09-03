=== VRED Geo Maps ===
Contributors: VRED
Tags: maps, leaflet, locations, shortcode
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later

VRED Geo Maps manages reusable locations and displays responsive Leaflet maps, lists, filters, legends and popups via shortcode.

== Description ==

VRED Geo Maps lets you manage reusable locations and optional Location Types, then display them in configurable Leaflet maps with the `[vred_geo_map]` shortcode.

Main features:

* Reusable locations with address, coordinates, contact details, image and automatic or custom popup content.
* Location Types and inherited marker styling at global, type and individual-location levels.
* Marker images or SVG icons, colors, sizing, clustering and automatic fitting of visible locations.
* Responsive map and list layouts with Cards, Compact, Legend and Grouped list styles.
* Search plus Location Type, country, province/region and city filters.
* Interactive on-map legend with configurable position, indicators and visible-location limits.
* User geolocation and responsive map controls.
* OpenStreetMap and CARTO basemaps, with an automatic OpenStreetMap fallback when a CARTO API key is unavailable.
* Configurable map height, appearance, global theme color, spacing, radii, popups and directions links.

== Requirements ==

* WordPress 6.6 or later. Tested up to WordPress 7.1.
* PHP 8.1 or later.

== Installation ==

1. Upload and activate VRED Geo Maps.
2. Open Settings > VRED Geo Maps.
3. Add locations and optional location types.
4. Configure the map, list, legend, filters, markers and popup appearance.
5. Insert `[vred_geo_map]` wherever WordPress processes shortcodes.

== Configuration ==

The global settings control the tile provider, map appearance and height, theme color, marker defaults, clustering, list layout, filters, on-map legend, popups and data removal on uninstall. Location Types and individual locations can override marker properties independently.

CARTO Positron, Positron No Labels and Voyager require your own CARTO API key. If a CARTO style is selected without a key, the frontend falls back to OpenStreetMap.

Map height supports `px`, `vh`, `dvh` and a validated custom CSS length expression. List and filter positions adapt to responsive layouts on smaller screens.

== Shortcode ==

Basic usage:

`[vred_geo_map]`

The shortcode inherits global settings and accepts these optional attributes:

* `ids="12,34"` displays specific location IDs.
* `types="shops,offices"` displays locations from specific Location Type slugs.
* `list="yes|no"` shows or hides the locations list.
* `filters="yes|no"` shows or hides filters.
* `cluster="yes|no"` enables or disables marker clustering.
* `position="left|right|top|bottom"` overrides the list position.

Example:

`[vred_geo_map types="shops" list="yes" filters="yes" cluster="yes" position="right"]`

== Changelog ==

= 1.1.0 =

* Added advanced layout options for maps, location lists and filters.
* Added an interactive on-map legend with configurable position, indicators and visible items.
* Improved responsive filters and map controls, including user geolocation.
* Refined Cards, Compact, Legend and Grouped list modes, and added a new automatic popup.
* Added map heights using px, vh, dvh or validated custom values.
* Improved markers, clustering and Location Type indicators.
* Added a global theme color and consistent shared frontend actions.
* Improved the location editor and plugin settings experience.
* Added CARTO configuration with automatic OpenStreetMap fallback.
* Improved the self-hosted updater and manual update checks.
* Isolated Leaflet stacking to prevent conflicts with surrounding site content.

= 1.0.0 =

* Initial release.
