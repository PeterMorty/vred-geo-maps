(() => {
	'use strict';

	const root = document.querySelector('[data-vred-geo-admin]');
	const config = window.vredGeoMapsAdmin || {};

	if (!root || !config.ajaxUrl || !config.nonce) {
		return;
	}

	const statusNode = root.querySelector('[data-vred-geo-status]');
	const cardsContainer = root.querySelector('[data-vred-geo-sortable]');
	const searchInput = root.querySelector('[data-vred-geo-admin-search]');
	let draggingCard = null;
	const geocodeRequests = new WeakMap();
	const tileProviderSetting = root.querySelector('[data-vred-geo-tile-provider-setting]');
	const cartoApiKeyField = root.querySelector('[data-vred-geo-carto-api-key-field]');
	const showListSetting = root.querySelector('[data-vred-geo-show-list-setting]');
	const listSettings = root.querySelector('[data-vred-geo-list-settings]');
	const listStyleSetting = root.querySelector('[data-vred-geo-list-style-setting]');
	const listPositionSetting = root.querySelector('[data-vred-geo-list-position-setting]');
	const listWidthField = root.querySelector('[data-vred-geo-list-width-field]');
	const listIndicatorSlot = root.querySelector('[data-vred-geo-list-indicator-slot]');
	const filtersPositionSetting = root.querySelector('[data-vred-geo-filters-position-setting]');
	const filtersMapPositionField = root.querySelector('[data-vred-geo-filters-map-position-field]');
	const showFiltersSetting = root.querySelector('[data-vred-geo-show-filters-setting]');
	const filterSettings = root.querySelector('[data-vred-geo-filter-settings]');
	const showMapLegendSetting = root.querySelector('[data-vred-geo-show-map-legend-setting]');
	const mapLegendSettings = root.querySelector('[data-vred-geo-map-legend-settings]');
	const legendIndicatorSlot = root.querySelector('[data-vred-geo-legend-indicator-slot]');
	const typeIndicatorField = root.querySelector('[data-vred-geo-type-indicator-field]');

	const syncLayoutSettings = () => {
		const listEnabled = Boolean(showListSetting?.checked);
		const filtersEnabled = Boolean(showFiltersSetting?.checked);
		const mapLegendEnabled = Boolean(showMapLegendSetting?.checked);
		const listNeedsIndicator = listEnabled && ['legend', 'grouped'].includes(listStyleSetting?.value || '');

		if (listSettings) {
			listSettings.hidden = !listEnabled;
		}

		if (filterSettings) {
			filterSettings.hidden = !filtersEnabled;
		}

		if (mapLegendSettings) {
			mapLegendSettings.hidden = !mapLegendEnabled;
		}

		if (typeIndicatorField) {
			const indicatorSlot = listNeedsIndicator ? listIndicatorSlot : mapLegendEnabled ? legendIndicatorSlot : listIndicatorSlot;

			if (indicatorSlot && typeIndicatorField.parentElement !== indicatorSlot) {
				indicatorSlot.append(typeIndicatorField);
			}

			typeIndicatorField.hidden = !listNeedsIndicator && !mapLegendEnabled;
		}

		if (listWidthField) {
			listWidthField.hidden = !['left', 'right'].includes(listPositionSetting?.value || '');
		}

		if (filtersMapPositionField) {
			filtersMapPositionField.hidden = filtersPositionSetting?.value !== 'map';
		}

		if (cartoApiKeyField) {
			cartoApiKeyField.hidden = !String(tileProviderSetting?.value || '').startsWith('carto_');
		}
	};

	const setStatus = (message = '', isError = false) => {
		if (!statusNode) {
			return;
		}

		statusNode.textContent = message;
		statusNode.classList.toggle('is-error', isError);
	};

	const setSaveStatus = (form, message = '', isError = false) => {
		const node = form.querySelector('[data-vred-geo-save-status]');
		if (!node) {
			return;
		}
		node.textContent = message;
		node.classList.toggle('is-error', isError);
	};

	const ajax = async (action, data = new FormData()) => {
		data.set('action', action);
		data.set('nonce', config.nonce);

		let response;

		try {
			response = await fetch(config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			});
		} catch (error) {
			throw new Error(config.strings?.networkError || config.strings?.error || '');
		}

		let payload = null;

		try {
			payload = await response.json();
		} catch (error) {
			throw new Error(config.strings?.error || '');
		}

		if (!response.ok || !payload?.success) {
			throw new Error(payload?.data?.message || config.strings?.error || '');
		}

		return payload.data || {};
	};

	const updateEmptyState = () => {
		if (!cardsContainer) {
			return;
		}

		const empty = cardsContainer.querySelector('[data-vred-geo-empty]');
		if (empty) {
			empty.hidden = Boolean(cardsContainer.querySelector('[data-vred-geo-card]'));
		}
	};

	const savePopupEditor = (textarea) => {
		if (!textarea?.id) {
			return;
		}

		const editor = window.tinymce?.get(textarea.id);
		if (editor && !editor.isHidden()) {
			editor.save();
		}
	};

	const syncPopupEditorContent = (form) => {
		form?.querySelectorAll('[data-vred-geo-popup-textarea]').forEach(savePopupEditor);
	};

	const removePopupEditor = (textarea) => {
		if (!textarea?.id || textarea.dataset.editorInitialized !== '1') {
			return;
		}

		savePopupEditor(textarea);

		if (window.wp?.editor?.remove) {
			window.wp.editor.remove(textarea.id);
		}

		delete textarea.dataset.editorInitialized;
	};

	const initializePopupEditor = (textarea) => {
		if (!textarea?.id || textarea.dataset.editorInitialized === '1' || !window.wp?.editor?.initialize) {
			return;
		}

		const defaults = window.wp.editor.getDefaultSettings?.() || {};
		const tinymceSettings = defaults.tinymce && typeof defaults.tinymce === 'object'
			? {
				...defaults.tinymce,
				height: 130,
				menubar: false,
				wpautop: true,
				toolbar1: 'formatselect bold italic | bullist numlist | blockquote | alignleft aligncenter alignright | link unlink'
			}
			: false;

		window.wp.editor.initialize(textarea.id, {
			tinymce: tinymceSettings,
			quicktags: defaults.quicktags || true,
			mediaButtons: true
		});

		textarea.dataset.editorInitialized = '1';
	};

	const syncPopupEditor = (form) => {
		const action = form?.querySelector('[data-vred-geo-action-select]');
		const toggle = form?.querySelector('[data-vred-geo-popup-custom]');
		const wrapper = form?.querySelector('[data-vred-geo-popup-editor]');
		const textarea = wrapper?.querySelector('[data-vred-geo-popup-textarea]');

		if (!action || !toggle || !wrapper || !textarea) {
			return;
		}

		const active = action.value === 'popup' && toggle.checked;
		wrapper.hidden = !active;

		const cardBody = form.closest('[data-vred-geo-card-body]');
		if (active && !cardBody?.hidden) {
			initializePopupEditor(textarea);
		} else if (!active) {
			removePopupEditor(textarea);
		}
	};

	const syncActionFields = (form) => {
		const select = form.querySelector('[data-vred-geo-action-select]');
		if (!select) {
			return;
		}

		form.querySelectorAll('[data-vred-geo-action-fields]').forEach((group) => {
			group.hidden = group.dataset.vredGeoActionFields !== select.value;
		});

		syncPopupEditor(form);
	};

	const syncOverride = (toggle) => {
		const wrapper = toggle.closest('.vred-geo-admin-override');
		if (!wrapper) {
			return;
		}

		wrapper.querySelectorAll('[data-vred-geo-override-input], [data-vred-geo-override-control]').forEach((control) => {
			control.disabled = !toggle.checked;
		});
	};

	const setGeocodeStatus = (form, message = '', isError = false) => {
		const node = form?.querySelector('[data-vred-geo-geocode-status]');
		if (!node) {
			return;
		}

		node.textContent = message;
		node.classList.toggle('is-error', isError);
	};

	const geocodeAddress = async (form, force = false) => {
		const addressInput = form?.querySelector('[data-vred-geo-address]');
		const latitudeInput = form?.querySelector('[name="latitude"]');
		const longitudeInput = form?.querySelector('[name="longitude"]');
		const cityInput = form?.querySelector('[name="city"]');
		const regionInput = form?.querySelector('[name="region"]');
		const countryInput = form?.querySelector('[name="country"]');

		if (!addressInput || !latitudeInput || !longitudeInput) {
			return false;
		}

		if (!force && form.dataset.manualCoordinates === '1') {
			return false;
		}

		const address = String(addressInput.value || '').trim();
		const originalAddress = String(addressInput.dataset.originalAddress || '').trim();
		const geocodedAddress = String(addressInput.dataset.geocodedAddress || '').trim();

		if (address.length < 3) {
			if (force) {
				setGeocodeStatus(form, config.strings?.addressTooShort || '', true);
			}
			return false;
		}

		if (!force && (address === geocodedAddress || (address === originalAddress && latitudeInput.value && longitudeInput.value))) {
			return false;
		}

		const active = geocodeRequests.get(form);
		if (active?.address === address) {
			return active.promise;
		}

		const button = form.querySelector('[data-vred-geo-geocode]');
		if (button) {
			button.disabled = true;
			button.classList.add('is-loading');
		}

		setGeocodeStatus(form, config.strings?.findingCoordinates || '');

		const data = new FormData();
		data.set('address', address);

		const promise = ajax('vred_geo_maps_geocode_address', data)
			.then((result) => {
				const hasGeographicData = Boolean(result.city || result.region || result.country);

				latitudeInput.value = result.latitude || '';
				longitudeInput.value = result.longitude || '';
				if (cityInput) {
					cityInput.value = result.city || '';
				}
				if (regionInput) {
					regionInput.value = result.region || '';
				}
				if (countryInput) {
					countryInput.value = result.country || '';
				}
				addressInput.dataset.geocodedAddress = address;
				form.dataset.manualCoordinates = '';
				setGeocodeStatus(
					form,
					hasGeographicData
						? config.strings?.coordinatesAndGeographicDataUpdated || config.strings?.coordinatesUpdated || ''
						: config.strings?.coordinatesUpdated || ''
				);
				return true;
			})
			.catch((error) => {
				setGeocodeStatus(form, error.message || config.strings?.coordinatesNotFound || '', true);
				return false;
			})
			.finally(() => {
				const current = geocodeRequests.get(form);
				if (current?.promise === promise) {
					geocodeRequests.delete(form);
				}

				if (button) {
					button.disabled = false;
					button.classList.remove('is-loading');
				}
			});

		geocodeRequests.set(form, { address, promise });
		return promise;
	};

	const setMediaAttachment = (wrapper, attachment) => {
		const idInput = wrapper.querySelector('[data-vred-geo-media-id]');
		const preview = wrapper.querySelector('[data-vred-geo-media-preview]');
		const emptySelect = wrapper.querySelector('[data-vred-geo-media-empty-select]');
		const removeButton = wrapper.querySelector('[data-vred-geo-media-remove]');

		if (!idInput || !preview || !emptySelect || !removeButton) {
			return;
		}

		const attachmentId = Number.parseInt(attachment?.id, 10) || 0;
		const imageUrl = attachment?.sizes?.thumbnail?.url || attachment?.url || '';
		idInput.value = attachmentId > 0 ? String(attachmentId) : '';
		preview.replaceChildren();

		if (attachmentId > 0 && imageUrl) {
			const image = document.createElement('img');
			image.src = imageUrl;
			image.alt = '';
			preview.append(image);
			preview.hidden = false;
			emptySelect.hidden = true;
			removeButton.hidden = false;
			wrapper.classList.add('has-image');
		} else {
			preview.hidden = true;
			emptySelect.hidden = false;
			removeButton.hidden = true;
			wrapper.classList.remove('has-image');
		}
	};

	const syncColorText = (textInput) => {
		const wrapper = textInput.closest('[data-vred-geo-color-control]');
		const picker = wrapper?.querySelector('[data-vred-geo-color-picker]');
		const value = String(textInput.value || '').trim();

		if (picker && /^#[0-9a-f]{6}$/i.test(value)) {
			picker.value = value;
		}
	};

	const syncColorPicker = (picker) => {
		const wrapper = picker.closest('[data-vred-geo-color-control]');
		const textInput = wrapper?.querySelector('[data-vred-geo-color-text]');

		if (textInput) {
			textInput.value = picker.value;
		}
	};

	const updateSummary = (card, summary) => {
		if (!card || !summary) {
			return;
		}

		const title = card.querySelector('[data-vred-geo-summary-title]');
		const meta = card.querySelector('[data-vred-geo-summary-meta]');

		if (title) {
			title.textContent = summary.title || '';
		}
		if (meta) {
			meta.textContent = summary.meta || '';
		}
		card.dataset.search = summary.search || '';
	};

	const applySearch = () => {
		if (!cardsContainer) {
			return;
		}

		const query = String(searchInput?.value || '').trim().toLowerCase();
		cardsContainer.classList.toggle('is-filtered', Boolean(query));

		cardsContainer.querySelectorAll('[data-vred-geo-card]').forEach((card) => {
			card.hidden = Boolean(query) && !String(card.dataset.search || '').toLowerCase().includes(query);
			card.draggable = false;
		});
	};

	const openCard = (card, expanded) => {
		const body = card.querySelector('[data-vred-geo-card-body]');
		const toggle = card.querySelector('[data-vred-geo-toggle]');

		if (!body || !toggle) {
			return;
		}

		const form = body.querySelector('form');

		if (!expanded && form) {
			syncPopupEditorContent(form);
			form.querySelectorAll('[data-vred-geo-popup-textarea]').forEach(removePopupEditor);
		}

		body.hidden = !expanded;
		toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');

		if (expanded && form) {
			syncActionFields(form);
			form.querySelectorAll('[data-vred-geo-override-toggle]').forEach(syncOverride);
		}
	};

	const reorder = async () => {
		if (!cardsContainer || cardsContainer.classList.contains('is-filtered')) {
			return;
		}

		const type = cardsContainer.dataset.vredGeoSortable;
		const order = Array.from(cardsContainer.querySelectorAll('[data-vred-geo-card]')).map((card) => card.dataset.id);
		const data = new FormData();
		order.forEach((id) => data.append('order[]', id));

		try {
			await ajax(type === 'type' ? 'vred_geo_maps_reorder_types' : 'vred_geo_maps_reorder_locations', data);
			setStatus(config.strings?.saved || '');
		} catch (error) {
			setStatus(error.message || config.strings?.error || '', true);
		}
	};

	root.addEventListener('click', async (event) => {
		const geocodeButton = event.target.closest('[data-vred-geo-geocode]');
		if (geocodeButton) {
			const form = geocodeButton.closest('[data-vred-geo-edit-form="location"]');
			if (form) {
				await geocodeAddress(form, true);
			}
			return;
		}

		const mediaSelect = event.target.closest('[data-vred-geo-media-select]');
		if (mediaSelect) {
			const wrapper = mediaSelect.closest('[data-vred-geo-media]');
			if (!wrapper || !window.wp?.media) {
				return;
			}

			const frame = window.wp.media({
				title: config.strings?.selectImage || '',
				button: { text: config.strings?.selectImage || '' },
				library: { type: 'image' },
				multiple: false
			});

			frame.on('select', () => {
				const attachment = frame.state().get('selection').first()?.toJSON();
				if (!attachment) {
					return;
				}

				setMediaAttachment(wrapper, attachment);
				const override = wrapper.closest('.vred-geo-admin-override');
				const toggle = override?.querySelector('[data-vred-geo-override-toggle]');
				if (toggle) {
					toggle.checked = true;
					syncOverride(toggle);
				}
			});
			frame.open();
			return;
		}

		const mediaRemove = event.target.closest('[data-vred-geo-media-remove]');
		if (mediaRemove) {
			const wrapper = mediaRemove.closest('[data-vred-geo-media]');
			if (!wrapper) {
				return;
			}

			setMediaAttachment(wrapper, null);
			const override = wrapper.closest('.vred-geo-admin-override');
			const toggle = override?.querySelector('[data-vred-geo-override-toggle]');
			if (toggle) {
				toggle.checked = false;
				syncOverride(toggle);
			}
			return;
		}
		const toggle = event.target.closest('[data-vred-geo-toggle]');
		if (toggle) {
			const card = toggle.closest('[data-vred-geo-card]');
			if (card) {
				openCard(card, toggle.getAttribute('aria-expanded') !== 'true');
			}
			return;
		}

		if (event.target.closest('[data-vred-geo-expand-all]')) {
			root.querySelectorAll('[data-vred-geo-card]:not([hidden])').forEach((card) => openCard(card, true));
			return;
		}

		if (event.target.closest('[data-vred-geo-collapse-all]')) {
			root.querySelectorAll('[data-vred-geo-card]').forEach((card) => openCard(card, false));
			return;
		}

		const duplicate = event.target.closest('[data-vred-geo-duplicate]');
		if (duplicate) {
			const card = duplicate.closest('[data-vred-geo-card]');
			if (!card || !cardsContainer) {
				return;
			}

			const data = new FormData();
			data.set('id', card.dataset.id || '');
			setStatus(config.strings?.saving || '');

			try {
				const result = await ajax('vred_geo_maps_duplicate_location', data);
				cardsContainer.insertAdjacentHTML('beforeend', result.html || '');
				updateEmptyState();
				applySearch();
				setStatus(config.strings?.saved || '');
			} catch (error) {
				setStatus(error.message || config.strings?.error || '', true);
			}
			return;
		}

		const deleteButton = event.target.closest('[data-vred-geo-delete]');
		if (deleteButton) {
			const card = deleteButton.closest('[data-vred-geo-card]');
			if (!card) {
				return;
			}

			const type = card.querySelector('[data-vred-geo-edit-form="type"]') ? 'type' : 'location';
			const confirmation = type === 'type' ? config.strings?.deleteType : config.strings?.deleteLocation;

			if (!window.confirm(confirmation || config.strings?.deleteItem || '')) {
				return;
			}

			const data = new FormData();
			data.set('id', card.dataset.id || '');

			try {
				await ajax(type === 'type' ? 'vred_geo_maps_delete_type' : 'vred_geo_maps_delete_location', data);
				card.remove();
				updateEmptyState();
				applySearch();
				setStatus(config.strings?.saved || '');
			} catch (error) {
				setStatus(error.message || config.strings?.error || '', true);
			}
		}
	});

	root.addEventListener('input', (event) => {
		if (event.target.matches('[data-vred-geo-color-text]')) {
			syncColorText(event.target);
		}

		if (event.target.matches('[data-vred-geo-color-picker]')) {
			syncColorPicker(event.target);
		}

		const locationForm = event.target.closest('[data-vred-geo-edit-form="location"]');
		if (!locationForm) {
			return;
		}

		if (event.target.matches('[data-vred-geo-address]')) {
			locationForm.dataset.manualCoordinates = '';
		}

		if (event.target.matches('[name="latitude"], [name="longitude"]')) {
			locationForm.dataset.manualCoordinates = '1';
		}
	});

	root.addEventListener('change', (event) => {
		if (event.target.matches('[data-vred-geo-action-select]')) {
			const form = event.target.closest('form');
			if (form) {
				syncActionFields(form);
			}
		}

		if (event.target.matches('[data-vred-geo-popup-custom]')) {
			const form = event.target.closest('form');
			if (form) {
				syncPopupEditor(form);
			}
		}

		if (event.target.matches('[data-vred-geo-override-toggle]')) {
			syncOverride(event.target);
		}
	});

	root.addEventListener('submit', async (event) => {
		const addForm = event.target.closest('[data-vred-geo-add-form]');
		if (addForm) {
			event.preventDefault();
			if (!cardsContainer) {
				return;
			}

			const type = addForm.dataset.vredGeoAddForm;
			const data = new FormData(addForm);
			setStatus(config.strings?.saving || '');

			try {
				const result = await ajax(type === 'type' ? 'vred_geo_maps_add_type' : 'vred_geo_maps_add_location', data);
				cardsContainer.insertAdjacentHTML('beforeend', result.html || '');
				addForm.reset();
				updateEmptyState();
				setStatus(config.strings?.saved || '');
				const newCard = cardsContainer.querySelector(`[data-vred-geo-card][data-id="${CSS.escape(String(result.id))}"]`);
				if (newCard) {
					openCard(newCard, true);
					newCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
				}
			} catch (error) {
				setStatus(error.message || config.strings?.error || '', true);
			}
			return;
		}

		const editForm = event.target.closest('[data-vred-geo-edit-form]');
		if (!editForm) {
			return;
		}

		event.preventDefault();
		const type = editForm.dataset.vredGeoEditForm;

		if (type === 'location') {
			await geocodeAddress(editForm);
			syncPopupEditorContent(editForm);
		}

		const data = new FormData(editForm);
		setSaveStatus(editForm, config.strings?.saving || '');

		try {
			const result = await ajax(type === 'type' ? 'vred_geo_maps_save_type' : 'vred_geo_maps_save_location', data);
			updateSummary(editForm.closest('[data-vred-geo-card]'), result.summary);
			applySearch();
			const addressInput = editForm.querySelector('[data-vred-geo-address]');
			if (addressInput) {
				addressInput.dataset.originalAddress = String(addressInput.value || '').trim();
			}
			setSaveStatus(editForm, result.message || config.strings?.saved || '');
		} catch (error) {
			setSaveStatus(editForm, error.message || config.strings?.error || '', true);
		}
	});

	root.addEventListener('keydown', (event) => {
		if (event.key !== 'Enter' || !event.target.matches('[data-vred-geo-address]')) {
			return;
		}

		event.preventDefault();
		const form = event.target.closest('[data-vred-geo-edit-form="location"]');
		if (form) {
			geocodeAddress(form, true);
		}
	});

	if (searchInput) {
		searchInput.addEventListener('input', applySearch);
	}

	if (cardsContainer) {
		cardsContainer.addEventListener('pointerdown', (event) => {
			const handle = event.target.closest('[data-vred-geo-drag-handle]');
			if (!handle) {
				return;
			}

			if (cardsContainer.classList.contains('is-filtered')) {
				setStatus(config.strings?.reorderDisabled || '', true);
				return;
			}

			const card = handle.closest('[data-vred-geo-card]');
			if (card) {
				card.draggable = true;
			}
		});

		cardsContainer.addEventListener('dragstart', (event) => {
			const card = event.target.closest('[data-vred-geo-card]');
			if (!card || cardsContainer.classList.contains('is-filtered')) {
				event.preventDefault();
				return;
			}

			draggingCard = card;
			card.classList.add('is-dragging');
			event.dataTransfer.effectAllowed = 'move';
		});

		cardsContainer.addEventListener('dragover', (event) => {
			if (!draggingCard) {
				return;
			}

			event.preventDefault();
			const target = event.target.closest('[data-vred-geo-card]');
			if (!target || target === draggingCard) {
				return;
			}

			const rect = target.getBoundingClientRect();
			const before = event.clientY < rect.top + rect.height / 2;
			cardsContainer.insertBefore(draggingCard, before ? target : target.nextSibling);
		});

		cardsContainer.addEventListener('dragend', async () => {
			if (!draggingCard) {
				return;
			}

			draggingCard.classList.remove('is-dragging');
			draggingCard.draggable = false;
			draggingCard = null;
			await reorder();
		});
	}

	root.querySelectorAll('[data-vred-geo-edit-form]').forEach(syncActionFields);
	root.querySelectorAll('[data-vred-geo-override-toggle]').forEach(syncOverride);
	syncLayoutSettings();
	[tileProviderSetting, showListSetting, listStyleSetting, listPositionSetting, showMapLegendSetting, showFiltersSetting, filtersPositionSetting].forEach((setting) => {
		setting?.addEventListener('change', syncLayoutSettings);
	});
	updateEmptyState();
})();
