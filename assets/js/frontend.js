(() => {
	'use strict';

	const initialized = new WeakSet();
	const tileProviders = {
		openstreetmap: {
			url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
			options: {
				maxZoom: 19,
				attribution: '&copy; OpenStreetMap contributors'
			}
		},
		carto_positron: {
			url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
			options: {
				maxZoom: 20,
				subdomains: 'abcd',
				attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
			}
		},
		carto_positron_nolabels: {
			url: 'https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png',
			options: {
				maxZoom: 20,
				subdomains: 'abcd',
				attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
			}
		},
		carto_voyager: {
			url: 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
			options: {
				maxZoom: 20,
				subdomains: 'abcd',
				attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
			}
		}
	};

	const defaultSvg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4" fill="currentColor"></circle></svg>';


	const normalizeSearchText = (value) => String(value || '')
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.toLowerCase()
		.replace(/\s+/g, ' ')
		.trim();

	const getGeographicOptions = (locations, key, constraints = {}) => {
		const options = new Map();

		locations.forEach((location) => {
			const matchesConstraints = Object.entries(constraints).every(([constraintKey, selected]) => (
				!selected || normalizeSearchText(location[constraintKey]) === selected
			));

			if (!matchesConstraints) {
				return;
			}

			const label = String(location[key] || '').trim();
			const value = normalizeSearchText(label);

			if (value && !options.has(value)) {
				options.set(value, label);
			}
		});

		return Array.from(options, ([value, label]) => ({ value, label }))
			.sort((first, second) => first.label.localeCompare(second.label, undefined, { sensitivity: 'base' }));
	};

	const syncGeographicSelect = (select, options) => {
		if (!select) {
			return;
		}

		const selected = select.value;
		select.querySelectorAll('option:not(:first-child)').forEach((option) => option.remove());

		options.forEach(({ value, label }) => {
			const option = document.createElement('option');
			option.value = value;
			option.textContent = label;
			select.append(option);
		});

		select.value = options.some((option) => option.value === selected) ? selected : '';
	};

	const getConfig = (root) => {
		const node = root.querySelector('[data-vred-geo-config]');

		if (!node) {
			return null;
		}

		try {
			return JSON.parse(node.textContent || '{}');
		} catch (error) {
			return null;
		}
	};

	const getMarkerColor = (location) => (/^#[0-9a-f]{6}$/i.test(location.marker?.color || '') ? location.marker.color : '#2f6fed');

	const getMarkerIcon = (location) => {
		const rawSize = Number.parseInt(location.marker?.size, 10);
		const size = Number.isFinite(rawSize) ? Math.max(16, Math.min(96, rawSize)) : 34;
		const color = getMarkerColor(location);
		const imageUrl = location.marker?.image_url || '';

		if (imageUrl) {
			return window.L.icon({
				iconUrl: imageUrl,
				className: 'vred-geo-marker-image',
				iconSize: [size, size],
				iconAnchor: [size / 2, size / 2],
				popupAnchor: [0, -(size / 2)]
			});
		}

		const icon = location.marker?.svg || defaultSvg;

		return window.L.divIcon({
			className: 'vred-geo-marker-wrap',
			html: `<span class="vred-geo-marker" style="--vred-geo-marker-color:${color}"><span class="vred-geo-marker__icon">${icon}</span></span>`,
			iconSize: [size, size],
			iconAnchor: [size / 2, size],
			popupAnchor: [0, -size + 4]
		});
	};

	const setActiveLocation = (root, markers, id, options = {}) => {
		root.querySelectorAll('[data-vred-geo-location-item].is-active').forEach((item) => item.classList.remove('is-active'));

		markers.forEach((entry) => {
			const element = entry.marker.getElement();
			if (element) {
				element.classList.toggle('is-active', String(entry.location.id) === String(id));
			}
		});

		const items = Array.from(root.querySelectorAll(`[data-vred-geo-location-item][data-location-id="${CSS.escape(String(id))}"]`));

		items.forEach((item) => {
			item.classList.add('is-active');

			const legendGroup = item.closest('[data-vred-geo-legend-group]');
			if (legendGroup instanceof HTMLDetailsElement) {
				legendGroup.open = true;
			}

			const mapLegendGroup = item.closest('[data-vred-geo-map-legend-group]');
			if (mapLegendGroup instanceof HTMLDetailsElement) {
				mapLegendGroup.open = true;
			}
		});

		if (options.scroll !== false && items.length) {
			const scrollTarget = items.find((item) => !item.hasAttribute('data-vred-geo-map-legend-location')) || items[0];
			scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		}
	};

	const getOverlayPadding = (root, canvas) => {
		const canvasRect = canvas.getBoundingClientRect();
		const padding = { top: 28, right: 28, bottom: 28, left: 28 };

		root.querySelectorAll('[data-vred-geo-overlay-block]').forEach((block) => {
			const slot = block.closest('[data-vred-geo-overlay-slot]');

			if (!slot || window.getComputedStyle(slot).position !== 'absolute') {
				return;
			}

			const rect = block.getBoundingClientRect();
			const overlapsCanvas = rect.right > canvasRect.left && rect.left < canvasRect.right
				&& rect.bottom > canvasRect.top && rect.top < canvasRect.bottom;

			if (!overlapsCanvas) {
				return;
			}

			const position = slot.dataset.position || '';

			if (position.startsWith('top-')) {
				padding.top = Math.max(padding.top, rect.bottom - canvasRect.top + 16);
			} else {
				padding.bottom = Math.max(padding.bottom, canvasRect.bottom - rect.top + 16);
			}

			if (position.endsWith('-left')) {
				padding.left = Math.max(padding.left, rect.right - canvasRect.left + 16);
			} else {
				padding.right = Math.max(padding.right, canvasRect.right - rect.left + 16);
			}
		});

		canvas.querySelectorAll('.leaflet-control-container .leaflet-control').forEach((control) => {
			const corner = control.parentElement;
			const rect = control.getBoundingClientRect();

			if (!corner || rect.width <= 0 || rect.height <= 0) {
				return;
			}

			if (corner.classList.contains('leaflet-top')) {
				padding.top = Math.max(padding.top, rect.bottom - canvasRect.top + 16);
			} else {
				padding.bottom = Math.max(padding.bottom, canvasRect.bottom - rect.top + 16);
			}

			if (corner.classList.contains('leaflet-left')) {
				padding.left = Math.max(padding.left, rect.right - canvasRect.left + 16);
			} else {
				padding.right = Math.max(padding.right, canvasRect.right - rect.left + 16);
			}
		});

		padding.left = Math.min(padding.left, canvasRect.width * 0.45);
		padding.right = Math.min(padding.right, canvasRect.width * 0.45);
		padding.top = Math.min(padding.top, canvasRect.height * 0.45);
		padding.bottom = Math.min(padding.bottom, canvasRect.height * 0.45);

		return padding;
	};

	const addLocationControl = (map, strings, getPadding) => {
		const LocationControl = window.L.Control.extend({
			options: { position: 'bottomright' },
			onAdd: () => {
				const container = window.L.DomUtil.create('div', 'leaflet-bar vred-geo-maps__location-control');
				const button = window.L.DomUtil.create('a', 'vred-geo-maps__action vred-geo-maps__location-button', container);
				const status = window.L.DomUtil.create('span', 'vred-geo-maps__location-status', container);
				const label = strings?.useMyLocation || 'Use my location';

				button.href = '#';
				button.setAttribute('role', 'button');
				button.title = label;
				button.setAttribute('aria-label', label);
				button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="3"></circle><path d="M12 2v3M12 19v3M2 12h3M19 12h3"></path><circle cx="12" cy="12" r="7"></circle></svg>';
				status.className = 'vred-geo-maps__location-status screen-reader-text';
				status.setAttribute('aria-live', 'polite');

				window.L.DomEvent.disableClickPropagation(container);
				window.L.DomEvent.on(button, 'keydown', (event) => {
					if (event.key === ' ') {
						event.preventDefault();
						button.click();
					}
				});
				window.L.DomEvent.on(button, 'click', (event) => {
					window.L.DomEvent.preventDefault(event);

					if (button.getAttribute('aria-busy') === 'true') {
						return;
					}

					button.title = label;
					button.setAttribute('aria-label', label);
					button.setAttribute('aria-disabled', 'true');
					button.setAttribute('aria-busy', 'true');
					status.textContent = '';

					const finish = () => {
						button.removeAttribute('aria-disabled');
						button.removeAttribute('aria-busy');
					};

					const onFound = (event) => {
						map.off('locationerror', onError);
						finish();
						const targetZoom = Math.min(14, map.getMaxZoom());
						map.setView(event.latlng, targetZoom);
						window.requestAnimationFrame(() => panToVisibleArea(map, event.latlng, getPadding()));
					};
					const onError = () => {
						const errorMessage = strings?.locationUnavailable || 'Your location could not be determined.';
						map.off('locationfound', onFound);
						finish();
						button.title = errorMessage;
						button.setAttribute('aria-label', errorMessage);
						status.textContent = errorMessage;
					};

					map.once('locationfound', onFound);
					map.once('locationerror', onError);
					map.locate({
						setView: false,
						watch: false,
						enableHighAccuracy: false,
						timeout: 10000,
						maximumAge: 60000
					});
				});

				return container;
			}
		});

		return new LocationControl().addTo(map);
	};

	const syncBottomRightControlInset = (canvas) => {
		const corner = canvas.querySelector('.leaflet-bottom.leaflet-right');
		const mapWrap = canvas.closest('.vred-geo-maps__map-wrap');

		if (!corner || !mapWrap) {
			return;
		}

		const controlRects = Array.from(corner.querySelectorAll('.leaflet-control'))
			.map((control) => control.getBoundingClientRect())
			.filter((rect) => rect.width > 0 && rect.height > 0);

		if (!controlRects.length) {
			return;
		}

		const top = Math.min(...controlRects.map((rect) => rect.top));
		const height = Math.max(0, canvas.getBoundingClientRect().bottom - top);
		mapWrap.style.setProperty('--vred-geo-bottom-right-controls-height', `${Math.ceil(height)}px`);
	};

	const panToVisibleArea = (map, latLng, padding) => {
		map.panTo(latLng, { animate: false });
		map.panBy([
			(padding.right - padding.left) / 2,
			(padding.bottom - padding.top) / 2
		], { animate: true });
	};

	const fitVisible = (map, visibleEntries, initialZoom, padding) => {
		if (!visibleEntries.length) {
			return;
		}

		if (visibleEntries.length === 1) {
			map.setView(visibleEntries[0].marker.getLatLng(), Math.max(10, initialZoom || 10));
			panToVisibleArea(map, visibleEntries[0].marker.getLatLng(), padding);
			return;
		}

		const bounds = window.L.latLngBounds(visibleEntries.map((entry) => entry.marker.getLatLng()));

		if (bounds.isValid()) {
			map.fitBounds(bounds, {
				paddingTopLeft: [padding.left, padding.top],
				paddingBottomRight: [padding.right, padding.bottom],
				maxZoom: 15
			});
		}
	};

	const revealMarker = (map, layerGroup, entry, padding, onReveal = null) => {
		const open = () => {
			if (typeof onReveal === 'function') {
				onReveal();
			}
			panToVisibleArea(map, entry.marker.getLatLng(), padding());
			if (entry.location.action === 'popup' && entry.location.popupHtml) {
				entry.marker.openPopup();
			}
		};

		if (typeof layerGroup.zoomToShowLayer === 'function') {
			layerGroup.zoomToShowLayer(entry.marker, open);
			return;
		}

		open();
	};

	const openLocationLink = (location) => {
		if (!location.website) {
			return;
		}

		const opened = window.open(location.website, '_blank', 'noopener,noreferrer');

		if (opened) {
			opened.opener = null;
		}
	};

	const initMap = (root) => {
		if (initialized.has(root) || !window.L) {
			return;
		}

		const canvas = root.querySelector('[data-vred-geo-canvas]');
		const config = getConfig(root);

		if (!canvas || !config || !Array.isArray(config.locations) || !config.locations.length) {
			return;
		}

		initialized.add(root);

		const cartoRequested = String(config.tileProvider || '').startsWith('carto_');
		const provider = cartoRequested && !config.cartoApiKey
			? tileProviders.openstreetmap
			: tileProviders[config.tileProvider] || tileProviders.openstreetmap;
		const tileUrl = cartoRequested && config.cartoApiKey
			? `${provider.url}?key=${encodeURIComponent(config.cartoApiKey)}`
			: provider.url;
		const map = window.L.map(canvas, {
			zoomControl: false,
			scrollWheelZoom: true
		});

		window.L.tileLayer(tileUrl, provider.options).addTo(map);

		const clusteringRequested = Boolean(config.clustering);
		const clusteringAvailable = typeof window.L.markerClusterGroup === 'function';

		if (clusteringRequested && !clusteringAvailable) {
			root.classList.add('vred-geo-maps--cluster-error');

			if (window.console && typeof window.console.error === 'function') {
				window.console.error(config.strings?.clusterUnavailable || '');
			}
		}

		const useClustering = clusteringRequested && clusteringAvailable;
		const layerGroup = useClustering
			? window.L.markerClusterGroup({
				showCoverageOnHover: false,
				maxClusterRadius: 80
			})
			: window.L.layerGroup();

		const markers = new Map();
		const markerLayers = [];
		const popupMaxWidth = Number.parseInt(config.popupWidth, 10);
		const popupMaxHeight = Math.max(180, Math.min(420, canvas.clientHeight - 80));

		config.locations.forEach((location) => {
			const marker = window.L.marker([location.latitude, location.longitude], {
				icon: getMarkerIcon(location),
				title: location.title || ''
			});

			marker.on('add', () => {
				marker.getElement()?.style.setProperty('--vred-geo-marker-color', getMarkerColor(location));
			});

			if (location.action === 'popup' && location.popupHtml) {
				marker.bindPopup(location.popupHtml, {
					maxWidth: popupMaxWidth,
					maxHeight: popupMaxHeight,
					className: 'vred-geo-maps__leaflet-popup'
				});
			}

			const entry = { location, marker };
			markers.set(String(location.id), entry);
			markerLayers.push(marker);

			marker.on('click', () => {
				setActiveLocation(root, markers, location.id);
				updateMapLegend(getVisibleLocationIds());

				if (location.action === 'link') {
					openLocationLink(location);
				}
			});
		});

		if (typeof layerGroup.addLayers === 'function') {
			layerGroup.addLayers(markerLayers);
		} else {
			markerLayers.forEach((marker) => layerGroup.addLayer(marker));
		}

		map.addLayer(layerGroup);

		const searchInput = root.querySelector('[data-vred-geo-search]');
		const typeFilter = root.querySelector('[data-vred-geo-type-filter]');
		const countryFilter = root.querySelector('[data-vred-geo-country-filter]');
		const regionFilter = root.querySelector('[data-vred-geo-region-filter]');
		const cityFilter = root.querySelector('[data-vred-geo-city-filter]');
		const resetButton = root.querySelector('[data-vred-geo-reset]');
		const filterToggle = root.querySelector('[data-vred-geo-filter-toggle]');
		const mapFilters = root.querySelector('[data-vred-geo-map-filters]');
		const noResults = root.querySelector('[data-vred-geo-no-results]');
		const mapLegend = root.querySelector('[data-vred-geo-map-legend]');
		const mapLegendToggle = mapLegend?.querySelector('[data-vred-geo-map-legend-toggle]');
		const mapLegendBody = mapLegend?.querySelector('[data-vred-geo-map-legend-body]');
		const mapLegendGroups = mapLegend?.querySelectorAll('[data-vred-geo-map-legend-group]') || [];
		const getMapPadding = () => getOverlayPadding(root, canvas);
		let searchTimer = null;

		addLocationControl(map, config.strings, getMapPadding);
		window.L.control.zoom({ position: 'bottomright' }).addTo(map);
		syncBottomRightControlInset(canvas);

		root.querySelectorAll('[data-vred-geo-overlay-block]').forEach((block) => {
			window.L.DomEvent.disableClickPropagation(block);
			window.L.DomEvent.disableScrollPropagation(block);
		});

		const updateGeographicOptions = () => {
			syncGeographicSelect(countryFilter, getGeographicOptions(config.locations, 'country'));

			const country = String(countryFilter?.value || '');
			syncGeographicSelect(regionFilter, getGeographicOptions(config.locations, 'region', { country }));

			const region = String(regionFilter?.value || '');
			syncGeographicSelect(cityFilter, getGeographicOptions(config.locations, 'city', { country, region }));
		};

		const formatString = (template, value) => String(template || '').replace(/%[ds]/, String(value));
		const getVisibleLocationIds = () => new Set(Array.from(markers.keys()).filter((id) => layerGroup.hasLayer(markers.get(id).marker)));

		const updateMapLegend = (visibleLocationIds) => {
			mapLegendGroups.forEach((group) => {
				const locations = Array.from(group.querySelectorAll('[data-vred-geo-map-legend-location]'));
				const visibleLocations = locations.filter((location) => visibleLocationIds.has(String(location.dataset.locationId || '')));
				const limit = Math.max(1, Number.parseInt(mapLegend?.dataset.visibleLimit || '5', 10) || 5);
				let expanded = group.dataset.locationsExpanded === 'true';
				const activeIndex = visibleLocations.findIndex((location) => location.classList.contains('is-active'));
				const count = group.querySelector('[data-vred-geo-map-legend-count]');
				const limitToggle = group.querySelector('[data-vred-geo-map-legend-limit]');

				group.hidden = visibleLocations.length === 0;

				if (visibleLocations.length <= limit) {
					group.dataset.locationsExpanded = 'false';
					expanded = false;
				}

				if (count) {
					count.textContent = String(visibleLocations.length);
				}

				locations.forEach((location) => {
					const visibleIndex = visibleLocations.indexOf(location);
					const withinLimit = expanded || (activeIndex >= limit
						? visibleIndex < limit - 1 || visibleIndex === activeIndex
						: visibleIndex < limit);
					location.hidden = visibleIndex < 0 || !withinLimit;
				});

				if (limitToggle) {
					limitToggle.hidden = visibleLocations.length <= limit;
					limitToggle.setAttribute('aria-expanded', String(expanded));
					limitToggle.textContent = expanded
						? config.strings?.showLess || 'Show less'
						: formatString(config.strings?.viewAll || 'View all (%d)', visibleLocations.length);
				}
			});
		};

		const setMapLegendExpanded = (expanded) => {
			if (!mapLegend || !mapLegendToggle || !mapLegendBody) {
				return;
			}

			mapLegend.classList.toggle('is-collapsed', !expanded);
			mapLegendBody.hidden = !expanded;
			mapLegendToggle.setAttribute('aria-expanded', String(expanded));
			mapLegendToggle.setAttribute('aria-label', expanded
				? config.strings?.collapseLegend || 'Collapse map legend'
				: config.strings?.expandLegend || 'Expand map legend');
			mapLegendToggle.firstElementChild.textContent = expanded ? '−' : '+';
			syncBottomRightControlInset(canvas);
		};

		if (mapLegend) {
			setMapLegendExpanded(!window.matchMedia('(max-width: 767px)').matches);

			mapLegendToggle?.addEventListener('click', () => {
				setMapLegendExpanded(mapLegendToggle.getAttribute('aria-expanded') !== 'true');
			});

			mapLegendGroups.forEach((group) => {
				group.addEventListener('toggle', () => {
					const summary = group.querySelector('summary');
					const name = group.querySelector('.vred-geo-maps__legend-heading strong')?.textContent?.trim() || '';
					if (summary) {
						summary.setAttribute('aria-label', formatString(
							group.open ? config.strings?.collapseGroup || 'Collapse %s' : config.strings?.expandGroup || 'Expand %s',
							name
						));
					}
				});
			});
		}

		const applyFilters = (fitMap = true) => {
			const search = normalizeSearchText(searchInput?.value || '');
			const typeId = String(typeFilter?.value || '');
			const country = String(countryFilter?.value || '');
			const region = String(regionFilter?.value || '');
			const city = String(cityFilter?.value || '');
			const visibleEntries = [];
			const visibleLocationIds = new Set();

			layerGroup.clearLayers();

			markers.forEach((entry) => {
				const matchesSearch = !search || normalizeSearchText(entry.location.searchText).includes(search);
				const matchesType = !typeId || String(entry.location.typeId) === typeId;
				const matchesCountry = !country || normalizeSearchText(entry.location.country) === country;
				const matchesRegion = !region || normalizeSearchText(entry.location.region) === region;
				const matchesCity = !city || normalizeSearchText(entry.location.city) === city;
				const visible = matchesSearch && matchesType && matchesCountry && matchesRegion && matchesCity;
				const items = root.querySelectorAll(`[data-vred-geo-location-item][data-location-id="${CSS.escape(String(entry.location.id))}"]`);

				items.forEach((item) => {
					item.hidden = !visible;

					if (!visible && item instanceof HTMLDetailsElement) {
						item.open = false;
					}
				});

				if (visible) {
					layerGroup.addLayer(entry.marker);
					visibleEntries.push(entry);
					visibleLocationIds.add(String(entry.location.id));
				}
			});

			root.querySelectorAll('[data-vred-geo-legend-group]').forEach((group) => {
				const groupItems = Array.from(group.querySelectorAll('[data-vred-geo-location-item]'));
				const visibleCount = groupItems.filter((item) => !item.hidden).length;
				const countNode = group.querySelector('[data-vred-geo-legend-count]');

				group.hidden = visibleCount === 0;

				if (countNode) {
					countNode.textContent = String(visibleCount);
				}

				if (visibleCount > 0 && (search || typeId || country || region || city) && group instanceof HTMLDetailsElement) {
					group.open = true;
				}
			});

			updateMapLegend(visibleLocationIds);

			if (noResults) {
				noResults.hidden = visibleEntries.length > 0;
			}

			if (fitMap && visibleEntries.length) {
				fitVisible(map, visibleEntries, Number.parseInt(config.zoom, 10), getMapPadding());
			}
		};

		root.addEventListener('click', (event) => {
			const legendLimitToggle = event.target.closest('[data-vred-geo-map-legend-limit]');

			if (legendLimitToggle && root.contains(legendLimitToggle)) {
				const group = legendLimitToggle.closest('[data-vred-geo-map-legend-group]');
				if (group) {
					group.dataset.locationsExpanded = String(group.dataset.locationsExpanded !== 'true');
					updateMapLegend(getVisibleLocationIds());
				}
				return;
			}

			const control = event.target.closest('[data-vred-geo-location-select]');

			if (!control || !root.contains(control)) {
				return;
			}

			const item = control.closest('[data-vred-geo-location-item]');
			const entry = item ? markers.get(String(item.dataset.locationId || '')) : null;

			if (!entry) {
				return;
			}

			if (control instanceof HTMLAnchorElement) {
				event.preventDefault();
			}

			if (item instanceof HTMLDetailsElement && item.classList.contains('vred-geo-maps__card') && !item.open) {
				root.querySelectorAll('.vred-geo-maps__card[open]').forEach((openCard) => {
					if (openCard !== item && openCard instanceof HTMLDetailsElement) {
						openCard.open = false;
					}
				});
			}

			setActiveLocation(root, markers, entry.location.id, { scroll: false });
			updateMapLegend(getVisibleLocationIds());
			revealMarker(map, layerGroup, entry, getMapPadding, () => {
				setActiveLocation(root, markers, entry.location.id, { scroll: false });
			});
		});

		if (searchInput) {
			searchInput.addEventListener('input', () => {
				window.clearTimeout(searchTimer);
				searchTimer = window.setTimeout(() => applyFilters(true), 140);
			});
		}

		if (typeFilter) {
			typeFilter.addEventListener('change', () => applyFilters(true));
		}

		[countryFilter, regionFilter, cityFilter].forEach((filter) => {
			filter?.addEventListener('change', () => {
				updateGeographicOptions();
				applyFilters(true);
			});
		});

		if (resetButton) {
			resetButton.addEventListener('click', () => {
				if (searchInput) {
					searchInput.value = '';
				}
				if (typeFilter) {
					typeFilter.value = '';
				}
				[countryFilter, regionFilter, cityFilter].forEach((filter) => {
					if (filter) {
						filter.value = '';
					}
				});
				updateGeographicOptions();
				applyFilters(true);
			});
		}

		if (filterToggle && mapFilters) {
			filterToggle.addEventListener('click', () => {
				const expanded = !mapFilters.classList.contains('is-expanded');
				mapFilters.classList.toggle('is-expanded', expanded);
				filterToggle.setAttribute('aria-expanded', String(expanded));
				window.requestAnimationFrame(() => applyFilters(true));
			});
		}

		updateGeographicOptions();

		const allEntries = Array.from(markers.values());

		if (config.autoFit) {
			fitVisible(map, allEntries, Number.parseInt(config.zoom, 10), getMapPadding());
		} else {
			const first = allEntries[0];
			map.setView(first.marker.getLatLng(), Number.parseInt(config.zoom, 10) || 6);
			panToVisibleArea(map, first.marker.getLatLng(), getMapPadding());
		}

		window.setTimeout(() => map.invalidateSize(), 0);

		if (typeof window.ResizeObserver === 'function') {
			let resizeFrame = null;
			const resizeObserver = new window.ResizeObserver(() => {
				window.cancelAnimationFrame(resizeFrame);
				resizeFrame = window.requestAnimationFrame(() => {
					map.invalidateSize({ pan: false });
					syncBottomRightControlInset(canvas);
				});
			});
			resizeObserver.observe(canvas);

			const controlCorner = canvas.querySelector('.leaflet-bottom.leaflet-right');
			if (controlCorner) {
				const controlObserver = new window.ResizeObserver(() => syncBottomRightControlInset(canvas));
				controlCorner.querySelectorAll('.leaflet-control').forEach((control) => controlObserver.observe(control));
			}
		}
	};

	const initScope = (scope = document) => {
		if (scope.matches?.('[data-vred-geo-maps]')) {
			initMap(scope);
		}

		scope.querySelectorAll?.('[data-vred-geo-maps]').forEach(initMap);
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => initScope());
	} else {
		initScope();
	}

})();
