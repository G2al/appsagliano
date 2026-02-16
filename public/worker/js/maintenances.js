(() => {
    const API_BASE = '/api';
    const TOKEN_KEY = 'app_sagliano_token';
    const USER_KEY = 'app_sagliano_user';
    const LAST_VEHICLE_KEY = 'app_sagliano_last_vehicle';

    let maintenancesCache = [];
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

    const renderMaintenances = (list) => {
        const container = document.getElementById('maintenances-list');
        if (!container) return;

        container.innerHTML = '';

        if (!list.length) {
            container.innerHTML = `<div class="col-12"><p class="content-color mb-0">Nessuna manutenzione ancora.</p></div>`;
            return;
        }

        const cards = list.map((item) => {
            const userName = item?.user?.full_name || item?.user?.name || 'Operatore';
            const vehicleLabel = item?.vehicle ? `${item.vehicle.plate || ''}${item.vehicle.plate ? ' - ' : ''}${item.vehicle.name || ''}`.trim() || 'N/D' : 'N/D';
            const supplierName = item?.supplier?.name || 'N/D';
            const invoice = item?.invoice_number || 'N/D';
            const { date: dateStr, time: timeStr } = formatDateTime(item?.date || item?.created_at);
            const notes = item?.notes || '';
            const attachment = item?.attachment_url || '';
            const kmCurrent = item?.km_current ?? '';
            const kmAfter = item?.km_after ?? '';
            const price = item?.price ? `${item.price} €` : '';
            const photo = attachment || 'images/profile/p6.png';

            return `
                <div class="col-12">
                    <div class="coupon-box">
                        <div class="coupon-details">
                            <div class="coupon-content">
                                <div class="coupon-name">
                                    <img class="img-fluid coupon-img" src="${photo}" alt="allegato">
                                    <div>
                                        <h5 class="fw-normal title-color" style="color: #2f4c94 !important; font-weight: 500!important;">${userName}</h5>
                                        <p class="content-color mb-0 role-label">Manutenzione</p>
                                    </div>
                                </div>
                                <div class="price-badge">${invoice}</div>
                            </div>
                            <p class="mb-1">${dateStr ? `Data: ${dateStr}` : 'Data non indicata'}${timeStr ? ` · Ora: ${timeStr}` : ''}</p>
                            <p class="mb-1 content-color">Fornitore: ${supplierName}</p>
                            ${price ? `<p class="mb-1 content-color">Prezzo: ${price}</p>` : ''}
                            <ul class="content-list">
                                <li><i class="iconsax icon" data-icon="car"></i>${vehicleLabel}</li>
                                <li><i class="iconsax icon" data-icon="map"></i>Km manutenzione: ${kmCurrent}</li>
                            </ul>
                            <div class="flex-align-center pt-2">
                                <h6 class="content-color fw-normal">Dettagli:</h6>
                            </div>
                            <p class="content-color mb-0">${notes}</p>
                        </div>
                        ${attachment ? `<div class="coupon-discount"><a href="${attachment}" target="_blank" rel="noreferrer">Allegato</a></div>` : ''}
                    </div>
                </div>
            `;
        });

        container.innerHTML = cards.join('');
    };

    const applyFilters = () => {
        const normalized = (searchQuery || '').toLowerCase();
        const filtered = maintenancesCache.filter((item) => {
            const matchesVehicle = selectedVehicleId ? String(item?.vehicle_id) === String(selectedVehicleId) : true;
            if (!matchesVehicle) return false;
            if (!normalized) return true;
            const text = [
                item?.supplier?.name,
                item?.vehicle?.name,
                item?.vehicle?.plate,
                item?.invoice_number,
                item?.notes,
                item?.user?.name,
            ].filter(Boolean).join(' ').toLowerCase();
            return text.includes(normalized);
        });
        renderMaintenances(filtered);
    };

    const filterMaintenances = (query) => {
        searchQuery = query.toLowerCase();
        applyFilters();
    };

    const showSkeleton = () => {
        const container = document.getElementById('maintenances-list');
        if (!container) return;
        const skeletons = Array.from({ length: 3 }).map(() => `
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
        `).join('');
        container.innerHTML = skeletons;
    };

    const deriveVehiclesFromMaintenances = () => {
        const map = new Map();
        maintenancesCache.forEach((item) => {
            if (!item?.vehicle_id || !item?.vehicle) return;
            if (!map.has(item.vehicle_id)) {
                map.set(item.vehicle_id, {
                    id: item.vehicle_id,
                    plate: item.vehicle?.plate,
                    name: item.vehicle?.name,
                    current_km: item.vehicle?.current_km,
                });
            }
        });
        return Array.from(map.values());
    };

    const renderVehicleTabs = (vehicles) => {
        const tabs = document.getElementById('vehicle-tabs');
        if (!tabs) return;

        const items = [{ id: '', label: 'Tutti' }, ...(vehicles || [])];
        tabs.innerHTML = items.map((v) => {
            const label = v.label || v.plate || v.name || 'Senza nome';
            const isActive = String(v.id) === String(selectedVehicleId);
            const klass = isActive ? 'btn theme-btn btn-sm' : 'btn btn-light btn-sm';
            return `<button class="${klass}" data-vehicle-id="${v.id}" type="button">${label}</button>`;
        }).join('');

        tabs.querySelectorAll('button').forEach((btn) => {
            btn.addEventListener('click', () => {
                selectedVehicleId = btn.dataset.vehicleId;
                renderVehicleTabs(vehicles);
                applyFilters();
            });
        });
    };

    const loadMaintenances = async () => {
        showSkeleton();
        const data = await api('/maintenances?per_page=all');
        maintenancesCache = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
        if (!isAdmin) {
            renderVehicleTabs(deriveVehiclesFromMaintenances());
        }
        applyFilters();
    };

    const normalizeInteger = (value) => {
        if (!value) return '';
        return value.toString().replace(/[^\d]/g, '');
    };

    const formatInteger = (value) => {
        const normalized = normalizeInteger(value);
        if (!normalized) return 'N/D';
        return new Intl.NumberFormat('it-IT').format(Number(normalized));
    };

    const loadOptions = async () => {
        try {
            const [suppliers, vehicles] = await Promise.all([
                api('/suppliers'),
                api('/vehicles'),
            ]);

            vehiclesById = new Map(vehicles.map((v) => [String(v.id), v]));
            const supplierSelect = document.getElementById('supplier-select');
            const vehicleSelect = document.getElementById('vehicle-select');

            if (supplierSelect) {
                supplierSelect.innerHTML = `<option value="">Seleziona</option>` +
                    suppliers.map((s) => `<option value="${s.id}">${s.name}</option>`).join('');
            }

            if (vehicleSelect) {
                const vehicleOptions = vehicles.map((v) => {
                    const label = [v.plate, v.name].filter(Boolean).join(' - ') || 'Senza nome';
                    return `<option value="${v.id}">${label}</option>`;
                }).join('');
                vehicleSelect.innerHTML = `<option value="">Seleziona</option>` + vehicleOptions;
                updateKmCurrent();
                vehicleSelect.addEventListener('change', () => {
                    updateKmCurrent();
                    if (vehicleSelect.value) setLastVehicle(vehicleSelect.value);
                });
                renderVehicleTabs(isAdmin ? vehicles.map((v) => ({ id: v.id, label: v.plate || v.name })) : deriveVehiclesFromMaintenances());
            }
        } catch (_) {
            // fail silent
        }
    };

    const updateKmCurrent = () => {
        const vehicleSelect = document.getElementById('vehicle-select');
        const kmInput = document.querySelector('#maintenance-form [name="km"]');
        const lastKmInfo = document.getElementById('last-maintenance-km');
        if (!vehicleSelect || !kmInput) return;
        const vehicle = vehiclesById.get(vehicleSelect.value);
        if (vehicle) {
            kmInput.value = vehicle.maintenance_km ?? '';
        }
        if (lastKmInfo) {
            const lastKmValue = vehicle?.maintenance_km;
            lastKmInfo.textContent = `Ultimi km manutenzione: ${formatInteger(lastKmValue)}`;
        }
        updateMaintenanceSteps();
    };

    const updateMaintenanceSteps = () => {
        const form = document.getElementById('maintenance-form');
        if (!form) return;
        const hasValue = (field) => field && field.value !== null && field.value !== '';

        let stage = 1;
        if (hasValue(form.supplier_id)) stage = 2;
        if (hasValue(form.vehicle_id)) stage = 3;
        if (hasValue(form.km)) stage = 4;
        if (hasValue(form.invoice_number)) stage = 5;
        if (hasValue(form.price)) stage = 6;
        if (hasValue(form.notes) || ((form.attachment?.files || []).length > 0)) stage = 6;

        form.querySelectorAll('.maintenance-step').forEach((el) => {
            const step = Number(el.dataset.maintenanceStep || '1');
            el.classList.toggle('d-none', stage < step);
        });
    };

    const setDefaultDate = () => {
        const dateInput = document.querySelector('#maintenance-form [name="date"]');
        if (dateInput && !dateInput.value) {
            const now = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            const formatted = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
            dateInput.value = formatted;
        }
    };

    const submitMaintenance = async () => {
        const form = document.getElementById('maintenance-form');
        const alertBox = document.getElementById('maintenance-alert');
        const button = document.getElementById('maintenance-submit');
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

        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));

        const requiredFields = [
            { name: 'date', label: 'Data' },
            { name: 'supplier_id', label: 'Fornitore' },
            { name: 'vehicle_id', label: 'Veicolo' },
            { name: 'km', label: 'Km manutenzione' },
            { name: 'price', label: 'Prezzo' },
            { name: 'invoice_number', label: 'Numero bolla' },
            { name: 'notes', label: 'Dettagli intervento' },
        ];

        const showError = (el, msg) => {
            if (alertBox) {
                alertBox.textContent = msg;
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
            const el = form.elements[field.name];
            if (!el || !el.value) {
                showError(el, `È obbligatorio inserire il campo ${field.label}.`);
                return;
            }
        }

        if (!form.attachment?.files?.length) {
            showError(form.attachment, 'È obbligatorio caricare l\'allegato.');
            return;
        }

        const formData = new FormData();
        ['date', 'supplier_id', 'vehicle_id', 'km', 'price', 'invoice_number', 'notes'].forEach((field) => {
            const raw = form.elements[field]?.value;
            if (raw !== undefined) {
                const value = field === 'km' ? normalizeInteger(raw) : raw;
                formData.append(field, value);
            }
        });
        const kmAfterRaw = form.elements['km_after']?.value;
        if (kmAfterRaw !== undefined && kmAfterRaw !== null && kmAfterRaw !== '') {
            formData.append('km_after', normalizeInteger(kmAfterRaw));
        }
        const nextMaintenanceDateRaw = form.elements['next_maintenance_date']?.value;
        if (nextMaintenanceDateRaw) {
            formData.append('next_maintenance_date', nextMaintenanceDateRaw);
        }
        formData.append('attachment', form.attachment.files[0]);

        try {
            alertBox?.classList.add('d-none');
            await api('/maintenances', { method: 'POST', body: formData });

            const selectedVehicle = form.vehicle_id?.value;
            const kmValue = normalizeInteger(form.km.value);
            form.reset();
            setDefaultDate();
            const lastVehicle = getLastVehicle();
            if (lastVehicle && form.vehicle_id) form.vehicle_id.value = lastVehicle;
            if (selectedVehicle && kmValue) {
                const existing = vehiclesById.get(selectedVehicle) || {};
                vehiclesById.set(selectedVehicle, { ...existing, maintenance_km: kmValue });
            }
            updateKmCurrent();
            updateMaintenanceSteps();
            const modal = bootstrap.Modal.getInstance(document.getElementById('maintenanceModal'));
            modal?.hide();
            await loadMaintenances();
        } catch (error) {
            showError(null, error.message);
        } finally {
            stopLoading();
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        isAdmin = getCurrentUser()?.role === 'admin';

        const searchInput = document.getElementById('search-maintenances');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => filterMaintenances(e.target.value || ''));
        }

        document.getElementById('maintenance-submit')?.addEventListener('click', submitMaintenance);
        document.getElementById('maintenanceModal')?.addEventListener('show.bs.modal', () => {
            const form = document.getElementById('maintenance-form');
            const alertBox = document.getElementById('maintenance-alert');
            if (form) {
                form.reset();
            }
            if (alertBox) {
                alertBox.classList.add('d-none');
                alertBox.textContent = '';
            }
            setDefaultDate();
            loadOptions();
            updateMaintenanceSteps();
        });

        ['supplier_id', 'vehicle_id', 'km', 'km_after', 'next_maintenance_date', 'invoice_number', 'price', 'notes', 'attachment'].forEach((name) => {
            const input = document.querySelector(`#maintenance-form [name=\"${name}\"]`);
            if (!input) return;
            const evt = input.type === 'file' ? 'change' : 'input';
            input.addEventListener(evt, updateMaintenanceSteps);
        });

        loadOptions().then(setDefaultDate);
        loadMaintenances();
        updateMaintenanceSteps();
    });
})();
