(() => {
    const API_BASE = '/api';
    const TOKEN_KEY = 'app_sagliano_token';

    let foldersCache = [];
    let searchQuery = '';
    let pendingFileId = null;
    let openModal = null;

    const getToken = () => localStorage.getItem(TOKEN_KEY);

    const showAlert = (message) => {
        const box = document.getElementById('documents-alert');
        if (!box) return;
        box.textContent = message;
        box.classList.remove('d-none');
    };

    const hideAlert = () => {
        const box = document.getElementById('documents-alert');
        if (!box) return;
        box.textContent = '';
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
            headers.Authorization = `Bearer ${token}`;
        }

        const response = await fetch(`${API_BASE}${path}`, {
            ...options,
            headers,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const validationMessage = data?.message || Object.values(data?.errors || {})?.[0]?.[0];
            throw new Error(validationMessage || 'Errore di comunicazione.');
        }

        return data;
    };

    const escapeHtml = (value) =>
        String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

    const formatDateTime = (value) => {
        if (!value) return '-';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '-';
        return date.toLocaleString('it-IT', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const formatBytes = (size) => {
        const bytes = Number(size || 0);
        if (!Number.isFinite(bytes) || bytes <= 0) return '-';
        const units = ['B', 'KB', 'MB', 'GB'];
        const power = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        const value = bytes / 1024 ** power;
        return `${value.toLocaleString('it-IT', { maximumFractionDigits: power === 0 ? 0 : 2 })} ${units[power]}`;
    };

    const getFilteredFolders = () => {
        const query = (searchQuery || '').trim().toLowerCase();

        if (!query) {
            return foldersCache;
        }

        return foldersCache
            .map((folder) => {
                const files = (folder.files || []).filter((file) => {
                    const joined = [
                        file.title,
                        folder.title,
                        file.mime_type,
                    ]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase();

                    return joined.includes(query);
                });

                if (files.length === 0 && !(folder.title || '').toLowerCase().includes(query)) {
                    return null;
                }

                return {
                    ...folder,
                    files,
                };
            })
            .filter(Boolean);
    };

    const renderDocuments = () => {
        const container = document.getElementById('documents-accordion');
        if (!container) return;

        const folders = getFilteredFolders();

        if (!folders.length) {
            container.innerHTML = `
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button">
                            Nessun documento disponibile.
                        </button>
                    </h2>
                </div>
            `;
            return;
        }

        container.innerHTML = folders
            .map((folder, index) => {
                const collapseId = `folder-collapse-${folder.id}`;
                const headingId = `folder-heading-${folder.id}`;
                const filesCount = folder.files?.length || 0;

                const filesHtml = filesCount
                    ? folder.files
                        .map((file) => {
                            const isOpened = !!file.opened_at;
                            const badgeClass = isOpened ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning';
                            const badgeText = isOpened ? 'Aperto' : 'Non aperto';

                            return `
                                <div class="border rounded-3 p-3 mb-2">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <h6 class="mb-1 title-color">${escapeHtml(file.title || 'Documento')}</h6>
                                            <p class="content-color mb-1">Caricato: ${formatDateTime(file.created_at)}</p>
                                            <p class="content-color mb-0">Dimensione: ${formatBytes(file.file_size)}</p>
                                        </div>
                                        <span class="badge ${badgeClass}">${badgeText}</span>
                                    </div>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm theme-btn open-document-btn" data-file-id="${file.id}">
                                            Apri documento
                                        </button>
                                    </div>
                                </div>
                            `;
                        })
                        .join('')
                    : '<p class="content-color mb-0">Nessun file presente in questa cartella.</p>';

                return `
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="${headingId}">
                            <button class="accordion-button ${index === 0 ? '' : 'collapsed'}" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="${index === 0 ? 'true' : 'false'}" aria-controls="${collapseId}">
                                ${escapeHtml(folder.title || 'Cartella')} (${filesCount})
                            </button>
                        </h2>
                        <div id="${collapseId}" class="accordion-collapse collapse ${index === 0 ? 'show' : ''}" aria-labelledby="${headingId}" data-bs-parent="#documents-accordion">
                            <div class="accordion-body">
                                ${filesHtml}
                            </div>
                        </div>
                    </div>
                `;
            })
            .join('');

        container.querySelectorAll('.open-document-btn').forEach((button) => {
            button.addEventListener('click', () => {
                pendingFileId = Number(button.dataset.fileId || 0);
                openConfirmationModal();
            });
        });
    };

    const getFileById = (fileId) => {
        for (const folder of foldersCache) {
            const match = (folder.files || []).find((file) => Number(file.id) === Number(fileId));
            if (match) return match;
        }
        return null;
    };

    const openConfirmationModal = () => {
        const file = getFileById(pendingFileId);
        if (!file) return;

        const message = document.getElementById('open-document-message');
        if (message) {
            message.textContent = `Confermi l'apertura del documento "${file.title || 'Documento'}"?`;
        }

        if (!openModal) {
            const modalEl = document.getElementById('openDocumentModal');
            if (!modalEl) return;
            openModal = new bootstrap.Modal(modalEl);
        }

        openModal.show();
    };

    const parseFileName = (contentDisposition) => {
        if (!contentDisposition) return null;
        const utf8Match = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i);
        if (utf8Match?.[1]) return decodeURIComponent(utf8Match[1]);
        const asciiMatch = contentDisposition.match(/filename=\"?([^\";]+)\"?/i);
        return asciiMatch?.[1] || null;
    };

    const downloadFile = async (fileId, fallbackTitle) => {
        const token = getToken();

        const response = await fetch(`${API_BASE}/documents/files/${fileId}/download`, {
            method: 'GET',
            headers: {
                Accept: '*/*',
                ...(token ? { Authorization: `Bearer ${token}` } : {}),
            },
        });

        if (!response.ok) {
            throw new Error('Impossibile aprire il documento.');
        }

        const blob = await response.blob();
        const contentDisposition = response.headers.get('Content-Disposition');
        const fileName = parseFileName(contentDisposition) || `${fallbackTitle || 'documento'}`;
        const objectUrl = URL.createObjectURL(blob);

        const link = document.createElement('a');
        link.href = objectUrl;
        link.target = '_blank';
        link.rel = 'noopener';
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        link.remove();

        setTimeout(() => URL.revokeObjectURL(objectUrl), 60000);
    };

    const confirmOpenDocument = async () => {
        if (!pendingFileId) return;

        const button = document.getElementById('confirm-open-document');
        const file = getFileById(pendingFileId);

        if (!file) return;

        const originalText = button?.textContent || 'Apri documento';
        if (button) {
            button.disabled = true;
            button.textContent = 'Apertura...';
        }

        hideAlert();

        try {
            const response = await api(`/documents/files/${pendingFileId}/open`, {
                method: 'POST',
                body: JSON.stringify({}),
            });

            const openedAt = response?.opened_at || new Date().toISOString();
            file.opened_at = openedAt;

            await downloadFile(file.id, file.title || 'documento');
            renderDocuments();
            openModal?.hide();
        } catch (error) {
            showAlert(error.message || 'Errore durante apertura documento.');
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = originalText;
            }
            pendingFileId = null;
        }
    };

    const loadDocuments = async () => {
        hideAlert();
        const data = await api('/documents');
        foldersCache = Array.isArray(data) ? data : [];
        renderDocuments();
    };

    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('search-documents');
        const confirmButton = document.getElementById('confirm-open-document');

        if (searchInput) {
            searchInput.addEventListener('input', (event) => {
                searchQuery = event.target.value || '';
                renderDocuments();
            });
        }

        confirmButton?.addEventListener('click', confirmOpenDocument);

        loadDocuments().catch((error) => {
            showAlert(error.message || 'Impossibile caricare i documenti.');
        });
    });
})();
