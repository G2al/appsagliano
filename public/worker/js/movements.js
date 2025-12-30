(() => {
    const API_BASE = '/api';
    const TOKEN_KEY = 'app_sagliano_token';
    const USER_KEY = 'app_sagliano_user';
    const LAST_VEHICLE_KEY = 'app_sagliano_last_vehicle';
    let movementsCache = [];
    let vehiclesCache = [];
    let vehiclesById = new Map();
    let stationsById = new Map();
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
    const setLastVehicle = (vehicleId) => {
        if (vehicleId) {
            localStorage.setItem(LAST_VEHICLE_KEY, vehicleId);
        }
    };

    const normalizeInteger = (value) => {
        if (!value) return '';
        return value.toString().replace(/[^\d]/g, '');
    };

    const formatInteger = (value) => {
        const normalized = normalizeInteger(value);
        if (!normalized) return '';
        return new Intl.NumberFormat('it-IT').format(Number(normalized));
    };

    const formatMoney = (value) => {
        const numberValue = Number(value);
        if (!Number.isFinite(numberValue)) return null;
        return numberValue.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const formatKmPerLiter = (value) => {
        const numberValue = Number(value);
        if (!Number.isFinite(numberValue) || numberValue < 0) return null;
        return numberValue.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const getKmPerLiterBadgeClass = (value) => {
        if (value === null || value === undefined) return 'bg-secondary';
        const numberValue = Number(value);
        if (!Number.isFinite(numberValue) || numberValue < 0) return 'bg-secondary';
        if (numberValue < 3) return 'bg-danger';
        if (numberValue < 3.5) return 'bg-warning text-dark';
        return 'bg-success';
    };

    const updateStationCreditFromSelection = () => {
        const box = document.getElementById('station-credit');
        if (!box) return;
        // Non mostrare il credito a frontend
        box.innerHTML = '';
        box.classList.add('d-none');
    };

    const api = async (path, options = {}) => {
        const token = getToken();
        const headers = {
            Accept: 'application/json',
            ...(options.headers || {}),
        };

        if (!(options.body instanceof FormData)) {
            headers['Content-Type'] = headers['Content-Type'] || 'application/json';
        }

        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        const response = await fetch(`${API_BASE}${path}`, {
            ...options,
            headers,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const message = data?.message || Object.values(data?.errors || {})?.[0]?.[0];
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

    const renderMovements = (list) => {
        const container = document.getElementById('movements-list');
        if (!container) return;

        container.innerHTML = '';

        if (!list.length) {
            container.innerHTML = `<div class="col-12"><p class="content-color mb-0">Nessun movimento ancora.</p></div>`;
            return;
        }

        const cards = list.map((movement) => {
            const userName = movement?.user?.full_name || movement?.user?.name || 'Operatore';
            const stationName = movement?.station?.name || 'Nessuna stazione';
            const vehicleName = movement?.vehicle?.name || movement?.vehicle?.plate || 'Nessun veicolo';
            const vehiclePlate = movement?.vehicle?.plate || movement?.vehicle?.name || 'N/D';
            const { date: dateStr, time: timeStr } = formatDateTime(movement?.date || movement?.created_at);
            const liters = movement?.liters ? `${movement.liters} L` : '—';
            const price = movement?.price ? `${movement.price} €` : '—';
            const adblue = movement?.adblue ? `${movement.adblue} L AdBlue` : null;
            const ticketKmPerLiter = movement?.km_per_liter ?? null;
            const ticketKmPerLiterFormatted = formatKmPerLiter(ticketKmPerLiter);
            const ticketKmPerLiterBadgeClass = getKmPerLiterBadgeClass(ticketKmPerLiter);
            const ticketKmPerLiterBadge = ticketKmPerLiterFormatted
                ? `<span class="badge ${ticketKmPerLiterBadgeClass}">${ticketKmPerLiterFormatted} km/L</span>`
                : `<span class="badge bg-secondary">N/D</span>`;
            const photo = movement?.photo_url || 'images/profile/p6.png';
            const roleLabel = movement?.user?.role === 'admin' ? 'Admin' : 'Operaio';
            const updatedByAdmin = movement?.updated_by && movement?.updated_by !== movement?.user_id;
            const updatedAt = formatDateTime(movement?.updated_at);
            const metaRow = updatedByAdmin
                ? `Modificato da admin il ${updatedAt.date} alle ${updatedAt.time}`
                : `Creato da ${userName}`;

            return `
                <div class="col-12">
                    <div class="coupon-box">
                        <div class="coupon-details">
                            <div class="coupon-content">
                                <div class="coupon-name">
                                    <img class="img-fluid coupon-img" src="${photo}" alt="ricevuta">
                                    <div>
                                        <h5 class="fw-normal title-color" style="
                                                color: #2f4c94 !important;
                                                font-weight: 500!important;
                                            ">${userName}</h5>
                                        <p class="content-color mb-0 role-label">${roleLabel}</p>
                                    </div>
                                </div>
                                <div class="price-badge">${price}</div>
                            </div>
                            <p class="mb-1">${dateStr ? `Data: ${dateStr}` : 'Data non indicata'}${timeStr ? ` · Ora: ${timeStr}` : ''}</p>
                            <p class="mb-1 content-color">${metaRow}</p>

                            <ul class="content-list">
                                <li><i class="iconsax icon" data-icon="map"></i>${stationName}</li>
                                <li><i class="iconsax icon" data-icon="car"></i>${vehicleName} (${vehiclePlate})</li>
                                <li><i class="iconsax icon" data-icon="speedometer"></i>Media ticket: ${ticketKmPerLiterBadge}</li>
                                ${adblue ? `<li><i class="iconsax icon" data-icon="drop"></i>${adblue}</li>` : ''}
                            </ul>
                            <div class="flex-align-center pt-2">
                                <h6 class="content-color fw-normal">Litri: ${liters}</h6>
                            </div>
                        </div>
                        <div class="coupon-discount">${price}</div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = cards.join('');
    };

    const applyFilters = () => {
        const normalized = (searchQuery || '').toLowerCase();
        const filtered = movementsCache.filter((movement) => {
            const matchesVehicle = selectedVehicleId ? String(movement?.vehicle_id) === String(selectedVehicleId) : true;
            if (!matchesVehicle) return false;

            if (!normalized) return true;

            const text = [
                movement?.station?.name,
                movement?.vehicle?.name,
                movement?.vehicle?.plate,
                movement?.notes,
                movement?.user?.name,
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return text.includes(normalized);
        });

        renderMovements(filtered);
    };

    const filterMovements = (query) => {
        searchQuery = query.toLowerCase();
        applyFilters();
    };

    const showSkeleton = () => {
        const container = document.getElementById('movements-list');
        if (!container) return;

        container.innerHTML = '';
        const skeletons = Array.from({ length: 3 })
            .map(() => `
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
                            <div class="skeleton-line" style="width: 70%;"></div>
                        </div>
                    </div>
                </div>
            `)
            .join('');
        container.innerHTML = skeletons;
    };

    const renderVehicleTabs = () => {
        const tabs = document.getElementById('vehicle-tabs');
        if (!tabs) return;

        const activeClass = 'btn theme-btn btn-sm';
        const inactiveClass = 'btn btn-light btn-sm';

        const items = [
            { id: '', label: 'Tutti' },
            ...vehiclesCache.map((v) => ({ id: v.id, label: v.plate || v.name || 'Senza nome' })),
        ];

        tabs.innerHTML = items
            .map((item) => {
                const isActive = String(item.id) === String(selectedVehicleId);
                const klass = isActive ? activeClass : inactiveClass;
                return `<button class="${klass}" data-vehicle-id="${item.id}" type="button">${item.label}</button>`;
            })
            .join('');

        tabs.querySelectorAll('button').forEach((btn) => {
            btn.addEventListener('click', () => {
                selectedVehicleId = btn.dataset.vehicleId;
                renderVehicleTabs();
                applyFilters();
            });
        });
    };

    const deriveVehiclesFromMovements = () => {
        const map = new Map();
        movementsCache.forEach((movement) => {
            if (!movement?.vehicle_id || !movement?.vehicle) return;
            if (!map.has(movement.vehicle_id)) {
                map.set(movement.vehicle_id, {
                    id: movement.vehicle_id,
                    plate: movement.vehicle?.plate,
                    name: movement.vehicle?.name,
                });
            }
        });
        return Array.from(map.values());
    };

    const loadMovements = async () => {
        showSkeleton();
        const data = await api('/movements');
        movementsCache = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
        if (!isAdmin) {
            vehiclesCache = deriveVehiclesFromMovements();
            if (selectedVehicleId && !vehiclesCache.find((v) => String(v.id) === String(selectedVehicleId))) {
                selectedVehicleId = '';
            }
            renderVehicleTabs();
        }
        applyFilters();
    };

    const updateKmStartFromSelection = () => {
        const vehicleSelect = document.getElementById('vehicle-select');
        const kmStartInput = document.querySelector('#movement-form [name="km_start"]');
        const kmEndInput = document.querySelector('#movement-form [name="km_end"]');
        if (!vehicleSelect || !kmStartInput) return;
        const vehicle = vehiclesById.get(vehicleSelect.value);
        if (vehicle && typeof vehicle.current_km !== 'undefined') {
            kmStartInput.value = vehicle.current_km;
            if (kmEndInput) {
                kmEndInput.min = vehicle.current_km;
            }
        }
        updateMovementSteps();
    };

    const updateVehicleEfficiencyFromSelection = () => {
        const box = document.getElementById('vehicle-efficiency');
        if (!box) return;
        // Mostra solo la media del ticket corrente: nascondiamo la media storica del veicolo per non confondere.
        box.innerHTML = '';
        box.classList.add('d-none');
    };

    const updateMovementSteps = () => {
        const form = document.getElementById('movement-form');
        if (!form) return;
        const hasValue = (field) => field && field.value !== null && field.value !== '';

        let stage = 1;
        if (hasValue(form.station_id)) stage = 2;
        if (hasValue(form.vehicle_id)) stage = 3;
        if (hasValue(form.km_start)) stage = 4;
        if (hasValue(form.km_end)) stage = 5;
        if (hasValue(form.liters)) stage = 6;
        if (hasValue(form.price)) stage = 7;
        if (hasValue(form.adblue) || hasValue(form.notes) || ((form.photo?.files || []).length > 0)) {
            stage = 7;
        }

        form.querySelectorAll('.movement-step').forEach((el) => {
            const step = Number(el.dataset.movementStep || '1');
            el.classList.toggle('d-none', stage < step);
        });
    };

    const updateTicketEfficiencyFromForm = () => {
        const form = document.getElementById('movement-form');
        const box = document.getElementById('ticket-efficiency');
        const alertBox = document.getElementById('movement-alert');
        if (!form || !box) return;

        const kmStartRaw = form.elements['km_start']?.value ?? '';
        const kmEndRaw = form.elements['km_end']?.value ?? '';
        const litersRaw = form.elements['liters']?.value ?? '';

        const kmStartNormalized = normalizeInteger(kmStartRaw);
        const kmEndNormalized = normalizeInteger(kmEndRaw);
        const litersNormalized = String(litersRaw).trim().replace(',', '.');

        const kmStart = kmStartNormalized === '' ? Number.NaN : Number(kmStartNormalized);
        const kmEnd = kmEndNormalized === '' ? Number.NaN : Number(kmEndNormalized);
        const liters = litersNormalized === '' ? Number.NaN : Number(litersNormalized);

        const isValidNumbers =
            Number.isFinite(kmStart) &&
            Number.isFinite(kmEnd) &&
            Number.isFinite(liters);

        if (isValidNumbers && kmEnd < kmStart) {
            if (alertBox) {
                alertBox.textContent = 'I km finali devono essere maggiori o uguali ai km iniziali.';
                alertBox.classList.remove('d-none');
                alertBox.dataset.clientError = 'km';
            }
            box.innerHTML = '';
            box.classList.add('d-none');
            return;
        }

        if (alertBox && alertBox.dataset.clientError === 'km') {
            alertBox.classList.add('d-none');
            alertBox.textContent = '';
            delete alertBox.dataset.clientError;
        }

        const isValid =
            isValidNumbers &&
            liters > 0 &&
            kmEnd >= kmStart;

        if (!isValid) {
            box.innerHTML = '';
            box.classList.add('d-none');
            return;
        }

        const avg = Math.round((((kmEnd - kmStart) / liters) + Number.EPSILON) * 100) / 100;
        const formatted = formatKmPerLiter(avg);
        const badgeClass = getKmPerLiterBadgeClass(avg);

        box.classList.remove('d-none');
        box.innerHTML = `<span class="badge ${badgeClass}">Media ticket: ${formatted} km/L</span>`;
    };

    const loadOptions = async () => {
        try {
            const [stations, vehicles] = await Promise.all([
                api('/stations'),
                api('/vehicles'),
            ]);

            stationsById = new Map(
                stations.map((s) => [String(s.id), s])
            );

            vehiclesById = new Map(
                vehicles.map((v) => [String(v.id), v])
            );

            const stationSelect = document.getElementById('station-select');
            const vehicleSelect = document.getElementById('vehicle-select');

            if (stationSelect) {
                const stationOptions = stations.map((s) => {
                    return `<option value="${s.id}">${s.name || 'Senza nome'}</option>`;
                }).join('');

                stationSelect.innerHTML = `<option value="">Seleziona</option>` + stationOptions;
                stationSelect.onchange = () => {
                    updateStationCreditFromSelection();
                    updateMovementSteps();
                };
                updateStationCreditFromSelection();
            }

            if (vehicleSelect) {
                const vehicleOptions = vehicles.map((v) => {
                    const label = [v.plate, v.name].filter(Boolean).join(' - ') || 'Senza nome';
                    return `<option value="${v.id}">${label}</option>`;
                }).join('');
                vehicleSelect.innerHTML = `<option value="">Seleziona</option>` + vehicleOptions;

                // Ripristina l'ultimo veicolo selezionato
                const lastVehicle = getLastVehicle();
                if (lastVehicle) {
                    vehicleSelect.value = lastVehicle;
                }

                vehicleSelect.onchange = () => {
                    updateKmStartFromSelection();
                    updateVehicleEfficiencyFromSelection();
                    updateTicketEfficiencyFromForm();
                    updateMovementSteps();
                    if (vehicleSelect.value) {
                        setLastVehicle(vehicleSelect.value);
                    }
                };
                updateKmStartFromSelection();
                updateVehicleEfficiencyFromSelection();
                updateTicketEfficiencyFromForm();
                updateMovementSteps();
                if (isAdmin) {
                    vehiclesCache = vehicles;
                    renderVehicleTabs();
                }
            }
        } catch (error) {
            // silenzioso, verrà ritentato all'apertura modale se serve
        }
    };

    const submitMovement = async () => {
        const form = document.getElementById('movement-form');
        const alertBox = document.getElementById('movement-alert');
        const button = document.getElementById('movement-submit');

        if (!form || !button) return;

        const stopLoading = (() => {
            const original = button.dataset.originalText || button.textContent;
            button.dataset.originalText = original;
            button.disabled = true;
            button.textContent = 'Salvataggio...';
            return () => {
                button.disabled = false;
                button.textContent = original;
            };
        })();

        const requiredFields = [
            { name: 'date', label: 'Data' },
            { name: 'station_id', label: 'Stazione' },
            { name: 'vehicle_id', label: 'Veicolo' },
            { name: 'km_start', label: 'Km iniziali' },
            { name: 'km_end', label: 'Km finali' },
            { name: 'liters', label: 'Litri' },
            { name: 'price', label: 'Prezzo' },
        ];

        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));

        const focusAndError = (el, message) => {
            if (alertBox) {
                alertBox.textContent = message;
                alertBox.classList.remove('d-none');
            }
            if (el) {
                el.classList.add('is-invalid');
                el.focus({ preventScroll: true });
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            stopLoading();
        };

        for (const field of requiredFields) {
            const value = form.elements[field.name]?.value;
            if (!value) {
                focusAndError(form.elements[field.name], `È obbligatorio inserire il campo ${field.label}.`);
                return;
            }
        }

        const kmStart = Number(normalizeInteger(form.elements['km_start']?.value || ''));
        const kmEnd = Number(normalizeInteger(form.elements['km_end']?.value || ''));
        if (Number.isFinite(kmStart) && Number.isFinite(kmEnd) && kmEnd < kmStart) {
            focusAndError(form.elements['km_end'], 'I km finali devono essere maggiori o uguali ai km iniziali.');
            return;
        }

        if (!form.photo?.files?.length) {
            focusAndError(form.photo, 'È obbligatorio caricare la foto della ricevuta.');
            return;
        }

        const formData = new FormData();

        ['date', 'station_id', 'vehicle_id', 'km_start', 'km_end', 'liters', 'price', 'adblue', 'notes'].forEach((field) => {
            const rawValue = form.elements[field]?.value;
            if (!rawValue) return;
            const value = ['km_start', 'km_end'].includes(field) ? normalizeInteger(rawValue) : rawValue;
            if (value !== '') formData.append(field, value);
        });

        if (form.photo && form.photo.files.length) {
            formData.append('photo', form.photo.files[0]);
            }

            if (form.vehicle_id?.value) {
                setLastVehicle(form.vehicle_id.value);
            }

            try {
                if (alertBox) alertBox.classList.add('d-none');
                await api('/movements', {
                    method: 'POST',
                    body: formData,
            });

            const stationIdForLocal = form.station_id?.value;
            const priceForLocal = Number(form.price?.value);
            if (stationIdForLocal && Number.isFinite(priceForLocal)) {
                const station = stationsById.get(stationIdForLocal);
                if (station && station.credit_balance !== null && station.credit_balance !== undefined && station.credit_balance !== '') {
                    const current = Number(station.credit_balance);
                    const nextBalance = Number.isFinite(current) ? current - priceForLocal : station.credit_balance;
                    stationsById.set(stationIdForLocal, { ...station, credit_balance: nextBalance });
                }
            }

            const modal = bootstrap.Modal.getInstance(document.getElementById('movementModal'));
            modal?.hide();
            const selectedVehicle = form.vehicle_id?.value;
            const kmEndValue = normalizeInteger(form.km_end.value);
            form.reset();
            const lastVehicle = getLastVehicle();
            if (lastVehicle && form.vehicle_id) {
                form.vehicle_id.value = lastVehicle;
            }
            if (selectedVehicle && kmEndValue) {
                const existing = vehiclesById.get(selectedVehicle) || {};
                vehiclesById.set(selectedVehicle, { ...existing, current_km: kmEndValue });
            }
            updateKmStartFromSelection();
            updateVehicleEfficiencyFromSelection();
            updateTicketEfficiencyFromForm();
            updateStationCreditFromSelection();
            updateMovementSteps();
            await loadMovements();
        } catch (error) {
            if (alertBox) {
                alertBox.textContent = error.message;
                alertBox.classList.remove('d-none');
            }
        } finally {
            stopLoading();
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        isAdmin = getCurrentUser()?.role === 'admin';
        const searchInput = document.getElementById('search-movements');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => filterMovements(e.target.value || ''));
        }

        const submitBtn = document.getElementById('movement-submit');
        if (submitBtn) {
            submitBtn.addEventListener('click', submitMovement);
        }

        document.getElementById('movementModal')?.addEventListener('show.bs.modal', loadOptions);
        document.getElementById('movementModal')?.addEventListener('show.bs.modal', () => {
            const form = document.getElementById('movement-form');
            const alertBox = document.getElementById('movement-alert');
            if (form) {
                form.reset();
                updateStationCreditFromSelection();
                updateVehicleEfficiencyFromSelection();
                updateTicketEfficiencyFromForm();
            }
            if (alertBox) {
                alertBox.classList.add('d-none');
                alertBox.textContent = '';
                delete alertBox.dataset.clientError;
            }
            const dateInput = document.querySelector('#movement-form [name="date"]');
            if (dateInput && !dateInput.value) {
                const now = new Date();
                const pad = (n) => String(n).padStart(2, '0');
                const formatted = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
                dateInput.value = formatted;
            }
            updateMovementSteps();
        });
        const movementModal = document.getElementById('movementModal');
        if (movementModal) {
            movementModal.addEventListener('hidden.bs.modal', function () {
                const focusedElement = this.querySelector(':focus');
                if (focusedElement) {
                    focusedElement.blur();
                }
            });
        }

        const kmInputs = ['km_start', 'km_end'];
        kmInputs.forEach((name) => {
            const input = document.querySelector(`#movement-form [name="${name}"]`);
            if (!input) return;
            input.addEventListener('focus', () => {
                input.value = normalizeInteger(input.value);
            });
            input.addEventListener('blur', () => {
                input.value = formatInteger(input.value);
            });
        });

        ['km_start', 'km_end', 'liters', 'price', 'adblue', 'notes'].forEach((name) => {
            const input = document.querySelector(`#movement-form [name="${name}"]`);
            if (!input) return;
            input.addEventListener('input', () => updateTicketEfficiencyFromForm());
            input.addEventListener('blur', () => updateTicketEfficiencyFromForm());
            input.addEventListener('input', updateMovementSteps);
        });
        const photoInput = document.querySelector('#movement-form [name="photo"]');
        photoInput?.addEventListener('change', updateMovementSteps);

        const stationSelect = document.querySelector('#movement-form [name="station_id"]');
        stationSelect?.addEventListener('change', updateMovementSteps);
        const vehicleSelectDom = document.querySelector('#movement-form [name="vehicle_id"]');
        vehicleSelectDom?.addEventListener('change', updateMovementSteps);
        const litersInput = document.querySelector('#movement-form [name="liters"]');
        litersInput?.addEventListener('input', updateMovementSteps);
        const kmEndInput = document.querySelector('#movement-form [name="km_end"]');
        kmEndInput?.addEventListener('input', updateMovementSteps);

        // Precarica opzioni veicoli per le tab e carica movimenti subito
        loadOptions();
        loadMovements();
        updateMovementSteps();
    });
})();
