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

	const getMarkerIcon = (location) => {
		const rawSize = Number.parseInt(location.marker?.size, 10);
		const size = Number.isFinite(rawSize) ? Math.max(16, Math.min(96, rawSize)) : 34;
		const color = /^#[0-9a-f]{6}$/i.test(location.marker?.color || '') ? location.marker.color : '#2f6fed';
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

		const item = root.querySelector(`[data-vred-geo-location-item][data-location-id="${CSS.escape(String(id))}"]`);

		if (item) {
			item.classList.add('is-active');

			const legendGroup = item.closest('[data-vred-geo-legend-group]');
			if (legendGroup instanceof HTMLDetailsElement) {
				legendGroup.open = true;
			}

			if (options.scroll !== false) {
				item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			}
		}
	};

	const fitVisible = (map, visibleEntries, initialZoom) => {
		if (!visibleEntries.length) {
			return;
		}

		if (visibleEntries.length === 1) {
			map.setView(visibleEntries[0].marker.getLatLng(), Math.max(10, initialZoom || 10));
			return;
		}

		const bounds = window.L.latLngBounds(visibleEntries.map((entry) => entry.marker.getLatLng()));

		if (bounds.isValid()) {
			map.fitBounds(bounds, { padding: [28, 28], maxZoom: 15 });
		}
	};

	const revealMarker = (map, layerGroup, entry) => {
		const open = () => {
			map.panTo(entry.marker.getLatLng(), { animate: true });
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

		const provider = tileProviders[config.tileProvider] || tileProviders.openstreetmap;
		const map = window.L.map(canvas, {
			zoomControl: true,
			scrollWheelZoom: true
		});

		window.L.tileLayer(provider.url, provider.options).addTo(map);

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
		const popupMaxHeight = Math.max(180, Math.min(420, canvas.clientHeight - 80));

		config.locations.forEach((location) => {
			const marker = window.L.marker([location.latitude, location.longitude], {
				icon: getMarkerIcon(location),
				title: location.title || ''
			});

			if (location.action === 'popup' && location.popupHtml) {
				marker.bindPopup(location.popupHtml, {
					maxWidth: 520,
					maxHeight: popupMaxHeight,
					className: 'vred-geo-maps__leaflet-popup'
				});
			}

			const entry = { location, marker };
			markers.set(String(location.id), entry);
			markerLayers.push(marker);

			marker.on('click', () => {
				setActiveLocation(root, markers, location.id);

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
		const noResults = root.querySelector('[data-vred-geo-no-results]');
		let searchTimer = null;

		const updateGeographicOptions = () => {
			syncGeographicSelect(countryFilter, getGeographicOptions(config.locations, 'country'));

			const country = String(countryFilter?.value || '');
			syncGeographicSelect(regionFilter, getGeographicOptions(config.locations, 'region', { country }));

			const region = String(regionFilter?.value || '');
			syncGeographicSelect(cityFilter, getGeographicOptions(config.locations, 'city', { country, region }));
		};

		const applyFilters = (fitMap = true) => {
			const search = normalizeSearchText(searchInput?.value || '');
			const typeId = String(typeFilter?.value || '');
			const country = String(countryFilter?.value || '');
			const region = String(regionFilter?.value || '');
			const city = String(cityFilter?.value || '');
			const visibleEntries = [];

			layerGroup.clearLayers();

			markers.forEach((entry) => {
				const matchesSearch = !search || normalizeSearchText(entry.location.searchText).includes(search);
				const matchesType = !typeId || String(entry.location.typeId) === typeId;
				const matchesCountry = !country || normalizeSearchText(entry.location.country) === country;
				const matchesRegion = !region || normalizeSearchText(entry.location.region) === region;
				const matchesCity = !city || normalizeSearchText(entry.location.city) === city;
				const visible = matchesSearch && matchesType && matchesCountry && matchesRegion && matchesCity;
				const item = root.querySelector(`[data-vred-geo-location-item][data-location-id="${CSS.escape(String(entry.location.id))}"]`);

				if (item) {
					item.hidden = !visible;

					if (!visible && item instanceof HTMLDetailsElement) {
						item.open = false;
					}
				}

				if (visible) {
					layerGroup.addLayer(entry.marker);
					visibleEntries.push(entry);
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


			if (noResults) {
				noResults.hidden = visibleEntries.length > 0;
			}

			if (fitMap && visibleEntries.length) {
				fitVisible(map, visibleEntries, Number.parseInt(config.zoom, 10));
			}
		};

		root.addEventListener('click', (event) => {
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
			revealMarker(map, layerGroup, entry);
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

		updateGeographicOptions();

		const allEntries = Array.from(markers.values());

		if (config.autoFit) {
			fitVisible(map, allEntries, Number.parseInt(config.zoom, 10));
		} else {
			const first = allEntries[0];
			map.setView(first.marker.getLatLng(), Number.parseInt(config.zoom, 10) || 6);
		}

		window.setTimeout(() => map.invalidateSize(), 0);
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
