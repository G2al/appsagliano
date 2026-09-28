(() => {
    const API_BASE = '/api';
    const TOKEN_KEY = 'mega_token';
    const USER_KEY = 'mega_user';
    const LAST_VEHICLE_KEY = 'mega_last_vehicle';

    let tripsCache = [];
    let vehiclesCache = [];
    let vehiclesById = new Map();
    let selectedVehicleId = '';
    let searchQuery = '';
    let isAdmin = false;

    const getToken = () => localStorage.getItem(TOKEN_KEY);
    const getCurrentUser = () => {
        try {
            const raw = localStorage.getItem(USER_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch {
            return null;
        }
    };
    const getLastVehicle = () => localStorage.getItem(LAST_VEHICLE_KEY) || '';
    const setLastVehicle = (vehicleId) => vehicleId && localStorage.setItem(LAST_VEHICLE_KEY, vehicleId);

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const formatVehicleLabel = (vehicle) => {
        if (!vehicle) return 'Senza nome';
        return [vehicle.plate, vehicle.name].filter(Boolean).join(' - ') || 'Senza nome';
    };

    const goodsTypeLabel = (value) => (value === 'freschi' ? 'Freschi' : value === 'secco' ? 'Secco' : '');

    const api = async (path, options = {}) => {
        const token = getToken();
        const headers = {
            Accept: 'application/json',
            ...(options.headers || {}),
        };
        if (!(options.body instanceof FormData)) {
            headers['Content-Type'] = headers['Content-Type'] || 'application/json';
        }
        if (token) headers['Authorization'] = `Bearer ${token}`;

        const response = await fetch(`${API_BASE}${path}`, { ...options, headers });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const firstError = Object.values(data?.errors || {})?.[0];
            const message = (Array.isArray(firstError) ? firstError[0] : firstError) || data?.message;
            throw new Error(message || 'Errore di comunicazione.');
        }
        return data;
    };

    const formatDateTime = (value) => {
        if (!value) return { date: '', time: '' };
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return { date: '', time: '' };
        return {
            date: date.toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' }),
            time: date.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' }),
        };
    };

    /* ---------- selezione veicolo con ricerca ---------- */

    const getVehicleSearchQuery = () =>
        String(document.getElementById('trip-vehicle-search')?.value || '').trim().toLowerCase();

    const getFilteredVehicles = () => {
        const query = getVehicleSearchQuery();
        if (!query) return vehiclesCache;

        return vehiclesCache.filter((vehicle) => [vehicle?.plate, vehicle?.name]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(query));
    };

    const updateVehicleHint = (selectedId = null) => {
        const hint = document.getElementById('trip-last-vehicle-hint');
        if (!hint) return;

        const query = getVehicleSearchQuery();
        const effectiveId = String(selectedId ?? document.getElementById('vehicle-select')?.value ?? '');
        const selectedVehicle = vehiclesById.get(effectiveId);
        const lastVehicle = vehiclesById.get(String(getLastVehicle()));

        if (query && getFilteredVehicles().length === 0) {
            hint.textContent = 'Nessun veicolo trovato per questa ricerca.';
        } else if (selectedVehicle && effectiveId !== '') {
            hint.textContent = `Veicolo selezionato: ${formatVehicleLabel(selectedVehicle)}`;
        } else if (lastVehicle) {
            hint.textContent = `Ultimo veicolo usato: ${formatVehicleLabel(lastVehicle)}`;
        } else {
            hint.textContent = 'Cerca per targa o nome del veicolo.';
        }
    };

    const syncVehicleSelectOptions = (preferredVehicleId = null) => {
        const vehicleSelect = document.getElementById('vehicle-select');
        if (!vehicleSelect) return;

        const nextValue = String(preferredVehicleId ?? vehicleSelect.value ?? '');
        const filteredVehicles = getFilteredVehicles();

        vehicleSelect.innerHTML = `<option value="">Seleziona</option>` + filteredVehicles
            .map((vehicle) => `<option value="${vehicle.id}">${escapeHtml(formatVehicleLabel(vehicle))}</option>`)
            .join('');

        vehicleSelect.value = filteredVehicles.some((vehicle) => String(vehicle.id) === nextValue) ? nextValue : '';
        updateVehicleHint(vehicleSelect.value);
    };

    /* ---------- destinazioni multiple ---------- */

    const addDestinationRow = (value = '') => {
        const list = document.getElementById('destinations-list');
        if (!list) return;

        const row = document.createElement('div');
        row.className = 'input-group destination-row';
        row.innerHTML = `
            <input type="text" class="form-control" name="destinations[]" maxlength="255" placeholder="Destinazione" autocomplete="off">
            <button type="button" class="btn btn-outline-secondary remove-destination" aria-label="Rimuovi destinazione">&times;</button>
        `;
        row.querySelector('input').value = value;
        row.querySelector('.remove-destination').addEventListener('click', () => {
            if (list.querySelectorAll('.destination-row').length > 1) {
                row.remove();
            } else {
                row.querySelector('input').value = '';
            }
        });
        list.appendChild(row);
    };

    const resetDestinations = () => {
        const list = document.getElementById('destinations-list');
        if (!list) return;
        list.innerHTML = '';
        addDestinationRow();
    };

    const getDestinations = () => Array.from(document.querySelectorAll('#destinations-list input'))
        .map((input) => input.value.trim())
        .filter(Boolean);

    /* ---------- lista viaggi ---------- */

    const renderTrips = (list) => {
        const container = document.getElementById('trips-list');
        if (!container) return;

        if (!list.length) {
            container.innerHTML = `<div class="col-12"><p class="content-color mb-0">Nessun viaggio ancora.</p></div>`;
            return;
        }

        container.innerHTML = list.map((trip) => {
            const userName = escapeHtml(trip?.user?.full_name || trip?.user?.name || 'Operatore');
            const vehiclePlate = escapeHtml(trip?.vehicle?.plate || trip?.vehicle?.name || 'N/D');
            const vehicleTitle = escapeHtml(formatVehicleLabel(trip?.vehicle));
            const platformName = escapeHtml(trip?.platform?.name || 'N/D');
            const destinations = (Array.isArray(trip?.destinations) ? trip.destinations : []).map(escapeHtml).join(' → ') || 'N/D';
            const goodsType = goodsTypeLabel(trip?.goods_type);
            const { date: dateStr, time: timeStr } = formatDateTime(trip?.date || trip?.created_at);
            const attachment = trip?.attachment_url || '';
            const isCertified = Boolean(trip?.is_certified);
            const statusBadge = isCertified
                ? `<span class="badge bg-success">Certificato</span>`
                : `<span class="badge bg-warning text-dark">Da certificare</span>`;
            const photo = attachment && !/\.pdf($|\?)/i.test(attachment) ? escapeHtml(attachment) : 'images/profile/p6.png';

            return `
                <div class="col-12">
                    <div class="coupon-box">
                        <div class="coupon-details">
                            <div class="coupon-content">
                                <div class="coupon-name">
                                    <img class="img-fluid coupon-img" src="${photo}" alt="allegato">
                                    <div>
                                        <h5 class="fw-normal title-color" style="color: #1A2A9C !important; font-weight: 500!important;">${userName}</h5>
                                        <p class="content-color mb-0 role-label">Viaggio</p>
                                    </div>
                                </div>
                                <div class="d-flex flex-column align-items-end gap-1">
                                    <div class="price-badge">Bolla ${escapeHtml(trip?.delivery_note_number || 'N/D')}</div>
                                    ${statusBadge}
                                </div>
                            </div>
                            <p class="mb-1">${dateStr ? `Data: ${dateStr}` : 'Data non indicata'}${timeStr ? ` · Ora: ${timeStr}` : ''}</p>
                            <p class="mb-1 content-color">Destinazioni: ${destinations}</p>
                            <ul class="content-list">
                                <li title="${vehicleTitle}"><i class="iconsax icon" data-icon="car"></i><span>${vehiclePlate}</span></li>
                                <li title="${platformName}"><i class="iconsax icon" data-icon="map"></i><span>${platformName}</span></li>
                                ${goodsType ? `<li><i class="iconsax icon" data-icon="box"></i><span>${goodsType}</span></li>` : ''}
                            </ul>
                        </div>
                        ${attachment ? `<div class="coupon-discount"><a href="${escapeHtml(attachment)}" target="_blank" rel="noreferrer">Allegato</a></div>` : ''}
                    </div>
                </div>
            `;
        }).join('');

    };

    const applyFilters = () => {
        const normalized = (searchQuery || '').toLowerCase();
        const filtered = tripsCache.filter((trip) => {
            if (selectedVehicleId && String(trip?.vehicle_id) !== String(selectedVehicleId)) return false;
            if (!normalized) return true;

            const text = [
                trip?.platform?.name,
                trip?.vehicle?.name,
                trip?.vehicle?.plate,
                trip?.delivery_note_number,
                trip?.user?.name,
                goodsTypeLabel(trip?.goods_type),
                ...(Array.isArray(trip?.destinations) ? trip.destinations : []),
            ].filter(Boolean).join(' ').toLowerCase();

            return text.includes(normalized);
        });
        renderTrips(filtered);
    };

    const showSkeleton = () => {
        const container = document.getElementById('trips-list');
        if (!container) return;
        container.innerHTML = Array.from({ length: 3 }).map(() => `
            <div class="col-12">
                <div class="coupon-box skeleton">
                    <div class="coupon-details">
                        <div class="coupon-content">
                            <div class="coupon-name gap-3">
                                <div class="skeleton-circle"></div>
                                <div class="flex-1">
                                    <div class="skeleton-line" style="width: 140px;"></div>
                                    <div class="skeleton-line" style="width: 200px;"></div>
                                </div>
                            </div>
                            <div class="skeleton-line" style="width: 60px;"></div>
                        </div>
                        <div class="skeleton-line" style="width: 180px;"></div>
                        <div class="skeleton-line" style="width: 90%;"></div>
                    </div>
                </div>
            </div>
        `).join('');
    };

    const deriveVehiclesFromTrips = () => {
        const map = new Map();
        tripsCache.forEach((trip) => {
            if (!trip?.vehicle_id || !trip?.vehicle || map.has(trip.vehicle_id)) return;
            map.set(trip.vehicle_id, { id: trip.vehicle_id, plate: trip.vehicle.plate, name: trip.vehicle.name });
        });
        return Array.from(map.values());
    };

    const renderVehicleTabs = (vehicles) => {
        const tabs = document.getElementById('vehicle-tabs');
        if (!tabs) return;

        const items = [{ id: '', label: 'Tutti' }, ...(vehicles || [])];
        tabs.innerHTML = items.map((vehicle) => {
            const label = escapeHtml(vehicle.label || vehicle.plate || vehicle.name || 'Senza nome');
            const isActive = String(vehicle.id) === String(selectedVehicleId);
            const klass = isActive ? 'btn theme-btn btn-sm' : 'btn btn-light btn-sm';
            return `<button class="${klass}" data-vehicle-id="${vehicle.id}" type="button">${label}</button>`;
        }).join('');

        tabs.querySelectorAll('button').forEach((btn) => {
            btn.addEventListener('click', () => {
                selectedVehicleId = btn.dataset.vehicleId;
                renderVehicleTabs(vehicles);
                applyFilters();
            });
        });
    };

    const loadTrips = async () => {
        showSkeleton();
        try {
            const data = await api('/trips?per_page=all');
            tripsCache = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
        } catch (error) {
            tripsCache = [];
        }
        if (!isAdmin) {
            renderVehicleTabs(deriveVehiclesFromTrips());
        }
        applyFilters();
    };

    const loadOptions = async () => {
        try {
            const [platforms, vehicles] = await Promise.all([
                api('/platforms'),
                api('/vehicles'),
            ]);

            vehiclesCache = Array.isArray(vehicles) ? vehicles : [];
            vehiclesById = new Map(vehiclesCache.map((vehicle) => [String(vehicle.id), vehicle]));

            const platformSelect = document.getElementById('platform-select');
            if (platformSelect) {
                const previous = platformSelect.value;
                platformSelect.innerHTML = `<option value="">Seleziona</option>` +
                    (Array.isArray(platforms) ? platforms : [])
                        .map((platform) => `<option value="${platform.id}">${escapeHtml(platform.name)}</option>`)
                        .join('');
                platformSelect.value = previous;
            }

            const vehicleSelect = document.getElementById('vehicle-select');
            if (vehicleSelect) {
                vehicleSelect.onchange = () => {
                    updateVehicleHint(vehicleSelect.value);
                    if (vehicleSelect.value) setLastVehicle(vehicleSelect.value);
                };
                syncVehicleSelectOptions(getLastVehicle() || vehicleSelect.value || '');
                if (isAdmin) {
                    renderVehicleTabs(vehiclesCache.map((vehicle) => ({ id: vehicle.id, label: vehicle.plate || vehicle.name })));
                }
            }
        } catch (_) {
            // fail silent
        }
    };

    const setDefaultDate = () => {
        const dateInput = document.querySelector('#trip-form [name="date"]');
        if (dateInput && !dateInput.value) {
            const now = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            dateInput.value = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
        }
    };

    const submitTrip = async () => {
        const form = document.getElementById('trip-form');
        const alertBox = document.getElementById('trip-alert');
        const button = document.getElementById('trip-submit');
        if (!form || !button) return;

        const original = button.dataset.originalText || button.textContent;
        button.dataset.originalText = original;
        const stopLoading = () => {
            button.disabled = false;
            button.textContent = original;
        };
        button.disabled = true;
        button.textContent = 'Salvataggio...';

        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));

        const showError = (el, message) => {
            if (alertBox) {
                alertBox.textContent = message;
                alertBox.classList.remove('d-none');
            }
            if (el) {
                el.classList.add('is-invalid');
                el.focus?.({ preventScroll: true });
                el.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
            }
            stopLoading();
        };

        const dateField = form.elements['date'];
        if (!dateField?.value) return showError(dateField, 'È obbligatorio inserire la data.');

        const platformField = form.elements['platform_id'];
        if (!platformField?.value) return showError(platformField, 'È obbligatorio selezionare la piattaforma.');

        const vehicleField = form.elements['vehicle_id'];
        if (!vehicleField?.value) return showError(vehicleField, 'È obbligatorio selezionare la targa.');

        const destinations = getDestinations();
        if (!destinations.length) {
            return showError(document.querySelector('#destinations-list input'), 'Inserisci almeno una destinazione.');
        }

        const goodsType = form.querySelector('[name="goods_type"]:checked')?.value;
        if (!goodsType) return showError(document.getElementById('goods-secco'), 'Seleziona la dicitura (Secco o Freschi).');

        const noteField = form.elements['delivery_note_number'];
        const noteValue = String(noteField?.value || '').trim();
        if (!/^\d{1,30}$/.test(noteValue)) {
            return showError(noteField, 'La bolla deve contenere solo numeri.');
        }

        const attachmentField = form.elements['attachment'];
        if (!attachmentField?.files?.length) return showError(attachmentField, 'È obbligatorio caricare l\'allegato.');

        const formData = new FormData();
        formData.append('date', dateField.value);
        formData.append('platform_id', platformField.value);
        formData.append('vehicle_id', vehicleField.value);
        destinations.forEach((destination) => formData.append('destinations[]', destination));
        formData.append('goods_type', goodsType);
        formData.append('delivery_note_number', noteValue);
        formData.append('attachment', attachmentField.files[0]);

        try {
            alertBox?.classList.add('d-none');
            await api('/trips', { method: 'POST', body: formData });

            setLastVehicle(vehicleField.value);
            form.reset();
            resetDestinations();
            setDefaultDate();
            bootstrap.Modal.getInstance(document.getElementById('tripModal'))?.hide();
            await loadTrips();
        } catch (error) {
            showError(null, error.message);
        } finally {
            stopLoading();
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        isAdmin = getCurrentUser()?.role === 'admin';

        document.getElementById('search-trips')?.addEventListener('input', (event) => {
            searchQuery = (event.target.value || '').toLowerCase();
            applyFilters();
        });

        document.getElementById('trip-vehicle-search')?.addEventListener('input', () => syncVehicleSelectOptions());
        document.getElementById('add-destination')?.addEventListener('click', () => addDestinationRow());
        document.getElementById('trip-submit')?.addEventListener('click', submitTrip);

        document.getElementById('tripModal')?.addEventListener('show.bs.modal', () => {
            const form = document.getElementById('trip-form');
            const alertBox = document.getElementById('trip-alert');
            form?.reset();
            form?.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
            const vehicleSearch = document.getElementById('trip-vehicle-search');
            if (vehicleSearch) vehicleSearch.value = '';
            if (alertBox) {
                alertBox.classList.add('d-none');
                alertBox.textContent = '';
            }
            resetDestinations();
            setDefaultDate();
            loadOptions();
        });

        resetDestinations();
        loadOptions().then(setDefaultDate);
        loadTrips();
    });
})();
