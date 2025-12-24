(() => {
    const API_BASE = '/api';
    const TOKEN_KEY = 'app_sagliano_token';
    const USER_KEY = 'app_sagliano_user';
    const REMEMBER_KEY = 'app_sagliano_remember';

    const showAlert = (element, message) => {
        if (!element) return;
        element.textContent = message;
        element.classList.remove('d-none');
        element.classList.remove('alert-success', 'alert-danger');
    };

    const hideAlert = (element) => {
        if (!element) return;
        element.textContent = '';
        element.classList.add('d-none');
    };

    const setLoading = (button, loadingText) => {
        if (!button) return () => {};

        const original = button.dataset.originalText ?? button.textContent;
        button.dataset.originalText = original;
        button.disabled = true;
        button.textContent = loadingText;

        return () => {
            button.disabled = false;
            button.textContent = button.dataset.originalText ?? original;
        };
    };

    const saveSession = (token, user) => {
        localStorage.setItem(TOKEN_KEY, token);
        localStorage.setItem(USER_KEY, JSON.stringify(user));
    };

    const clearSession = () => {
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
    };

    const getToken = () => localStorage.getItem(TOKEN_KEY);

    const saveRememberedCredentials = (fullName, password) => {
        localStorage.setItem(REMEMBER_KEY, JSON.stringify({ full_name: fullName, password }));
    };

    const clearRememberedCredentials = () => {
        localStorage.removeItem(REMEMBER_KEY);
    };

    const loadRememberedCredentials = () => {
        const raw = localStorage.getItem(REMEMBER_KEY);
        if (!raw) return null;
        try {
            return JSON.parse(raw);
        } catch {
            return null;
        }
    };

    const api = async (path, options = {}) => {
        const token = getToken();
        const headers = {
            'Accept': 'application/json',
            ...(options.headers || {}),
        };

        if (options.body && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
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
            const validationMessage = data?.message || Object.values(data?.errors || {})?.[0]?.[0];
            throw new Error(validationMessage || 'Si è verificato un errore. Riprova.');
        }

        return data;
    };

    const normalizePhone = (value) => {
        let digits = (value || '').replace(/\D+/g, '');
        if (digits.startsWith('00')) {
            digits = digits.slice(2);
        }
        if (digits.startsWith('39')) {
            digits = digits.slice(2);
        }
        return `+39${digits}`;
    };

    const handleLogin = () => {
        const form = document.getElementById('login-form');
        if (!form) return;

        const alertBox = document.getElementById('login-alert');
        const submit = document.getElementById('login-submit');
        const rememberCheckbox = document.getElementById('flexCheckDefault');

        const remembered = loadRememberedCredentials();
        if (remembered) {
            form.full_name.value = remembered.full_name || '';
            form.password.value = remembered.password || '';
            if (rememberCheckbox) {
                rememberCheckbox.checked = true;
            }
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            hideAlert(alertBox);

            const stopLoading = setLoading(submit, 'Accesso...');

            try {
                const data = await api('/auth/login', {
                    method: 'POST',
                    body: JSON.stringify({
                        full_name: form.full_name.value.trim(),
                        password: form.password.value,
                    }),
                });

                saveSession(data.token, data.user);

                if (rememberCheckbox?.checked) {
                    saveRememberedCredentials(form.full_name.value.trim(), form.password.value);
                } else {
                    clearRememberedCredentials();
                }

                window.location.href = 'home.html';
            } catch (error) {
                showAlert(alertBox, error.message);
            } finally {
                stopLoading();
            }
        });
    };

    const handleRegister = () => {
        const form = document.getElementById('register-form');
        if (!form) return;

        const alertBox = document.getElementById('register-alert');
        const submit = document.getElementById('register-submit');

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            hideAlert(alertBox);

            if (form.password.value !== form.password_confirmation.value) {
                showAlert(alertBox, 'Le password non coincidono.');
                return;
            }

            const stopLoading = setLoading(submit, 'Registrazione...');

            try {
                await api('/auth/register', {
                    method: 'POST',
                    body: JSON.stringify({
                        name: form.name.value.trim(),
                        surname: form.surname.value.trim(),
                        phone: normalizePhone(form.phone.value.trim()),
                        password: form.password.value,
                        password_confirmation: form.password_confirmation.value,
                    }),
                });

                if (alertBox) {
                    alertBox.textContent = 'Registrazione inviata. Attendi approvazione dall\'admin.';
                    alertBox.classList.remove('d-none', 'alert-danger');
                    alertBox.classList.add('alert-success');
                }
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 1200);
            } catch (error) {
                if (alertBox) {
                    alertBox.classList.remove('alert-success');
                    alertBox.classList.add('alert-danger');
                }
                showAlert(alertBox, error.message);
            } finally {
                stopLoading();
            }
        });
    };

    const hydrateUser = (user) => {
        const name = document.getElementById('sidebar-user-name');
        const email = document.getElementById('sidebar-user-email');
        const greeting = document.getElementById('user-greeting');
        const fullName = user?.full_name || [user?.name, user?.surname].filter(Boolean).join(' ').trim() || 'Operatore';

        if (name) {
            name.textContent = fullName;
        }

        if (email) {
            email.textContent = user?.email || '';
        }

        if (greeting) {
            greeting.textContent = `Ciao ${fullName}`;
        }
    };

    const requireAuth = async () => {
        if (!document.body.dataset.requireAuth) return;

        const token = getToken();

        if (!token) {
            clearSession();
            window.location.href = 'login.html';
            return;
        }

        try {
            const user = await api('/me');
            saveSession(token, user);
            hydrateUser(user);
        } catch (error) {
            clearSession();
            window.location.href = 'login.html';
        }
    };

    const handleLogout = () => {
        const logoutLink = document.getElementById('logout-link');
        if (!logoutLink) return;

        logoutLink.addEventListener('click', async (event) => {
            event.preventDefault();
            try {
                await api('/logout', { method: 'POST' });
            } catch (_) {
                // ignore error, proceed to clear session
            } finally {
                clearSession();
                window.location.href = 'login.html';
            }
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        handleLogin();
        handleRegister();
        handleLogout();
        requireAuth();
    });
})();
