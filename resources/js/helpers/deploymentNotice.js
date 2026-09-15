const storageKey = 'schooltool-deployment-id';
const activeStates = ['scheduled', 'maintenance', 'failed'];
const knownStates = ['idle', ...activeStates, 'completed'];

export function startDeploymentNotice({ axiosClient, fetchStatus = window.fetch.bind(window), reload = () => window.location.reload() } = {}) {
    let stopped = false;
    let pending = null;
    let pollTimer = null;
    let requestController = null;
    let banner = null;
    let visibleState = null;
    let recovered = false;
    let deploymentId = null;

    try {
        deploymentId = window.sessionStorage.getItem(storageKey);
    } catch {
        // Browser privacy settings may disable storage.
    }

    let observedDeployment = Boolean(deploymentId);

    function rememberDeployment(id) {
        deploymentId = id;

        try {
            if (id) {
                window.sessionStorage.setItem(storageKey, id);
            } else {
                window.sessionStorage.removeItem(storageKey);
            }
        } catch {
            // The notice also works without persistent browser storage.
        }
    }

    function showNotice(state) {
        if (stopped || state === visibleState) {
            return;
        }

        visibleState = state;

        if (!banner) {
            banner = document.createElement('aside');
            banner.className = 'schooltool-deployment-notice';
            banner.setAttribute('role', 'status');
            banner.setAttribute('aria-live', 'polite');
            banner.setAttribute('aria-atomic', 'true');
            document.body.append(banner);
        }

        const messages = {
            scheduled: ['Aktualisierung angekündigt', 'SchoolTool wird in Kürze aktualisiert. Bitte speichern Sie jetzt Ihre Eingaben.'],
            maintenance: ['SchoolTool wird aktualisiert', 'Speichern ist vorübergehend nicht möglich. Bitte lassen Sie diese Seite geöffnet und warten Sie.'],
            failed: ['Aktualisierung verzögert', 'SchoolTool ist noch nicht wieder freigegeben. Bitte lassen Sie diese Seite geöffnet.'],
            unavailable: ['Verbindung unterbrochen', 'Die Aktualisierung wurde angekündigt. Wir prüfen, wann SchoolTool wieder verfügbar ist. Bitte lassen Sie diese Seite geöffnet.'],
            completed: ['SchoolTool ist wieder verfügbar', 'Bitte prüfen und speichern Sie offene Eingaben, bevor Sie die Seite neu laden.'],
        };
        const [title, description] = messages[state];
        const text = document.createElement('div');
        text.className = 'schooltool-deployment-notice__text';
        const heading = document.createElement('strong');
        heading.textContent = title;
        const paragraph = document.createElement('p');
        paragraph.textContent = description;
        text.append(heading, paragraph);
        banner.replaceChildren(text);
        banner.hidden = false;
        banner.dataset.state = state;

        if (['scheduled', 'completed'].includes(state)) {
            const dismissButton = document.createElement('button');
            dismissButton.type = 'button';
            dismissButton.className = 'schooltool-deployment-notice__dismiss';
            dismissButton.setAttribute('aria-label', 'Hinweis schließen');
            dismissButton.textContent = '×';
            dismissButton.addEventListener('click', () => {
                banner.hidden = true;
            });
            banner.append(dismissButton);
        }

        if (state === 'completed') {
            const actions = document.createElement('div');
            actions.className = 'schooltool-deployment-notice__actions';
            const refreshButton = document.createElement('button');
            refreshButton.type = 'button';
            refreshButton.textContent = 'Jetzt neu laden';
            refreshButton.addEventListener('click', reload);
            const changelog = document.createElement('a');
            changelog.href = '/documentation/releases/';
            changelog.target = '_blank';
            changelog.rel = 'noopener';
            changelog.textContent = 'Was ist neu?';
            actions.append(refreshButton, changelog);
            banner.append(actions);
        }
    }

    async function fetchJson(path) {
        requestController = new AbortController();
        const timeout = window.setTimeout(() => requestController?.abort(), 5000);

        try {
            const response = await fetchStatus(path, {
                cache: 'no-store',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: requestController.signal,
            });

            if (!response.ok) {
                throw new Error('Deployment status is unavailable.');
            }

            return await response.json();
        } finally {
            window.clearTimeout(timeout);
            requestController = null;
        }
    }

    async function updateStatus() {
        try {
            const status = await fetchJson('/deployment-status.php');

            if (stopped) {
                return;
            }

            if (!status || !knownStates.includes(status.state) || !(status.id === null || typeof status.id === 'string')) {
                throw new Error('Invalid deployment status.');
            }

            if (activeStates.includes(status.state)) {
                observedDeployment = true;
                recovered = false;
                rememberDeployment(status.id);
                showNotice(status.state);

                return;
            }

            if (observedDeployment && !recovered) {
                const health = await fetchJson('/up');

                if (health?.status !== 'up') {
                    throw new Error('Application health is not confirmed.');
                }

                if (!stopped) {
                    recovered = true;
                    rememberDeployment(null);
                    showNotice('completed');
                }
            }
        } catch {
            if (observedDeployment && !recovered) {
                showNotice('unavailable');
            }
        }
    }

    function checkNow() {
        if (stopped || pending) {
            return pending ?? Promise.resolve();
        }

        window.clearTimeout(pollTimer);
        pending = updateStatus().finally(() => {
            pending = null;

            if (!stopped) {
                pollTimer = window.setTimeout(checkNow, 10000);
            }
        });

        return pending;
    }

    const interceptorId = axiosClient?.interceptors.response.use(
        (response) => response,
        (error) => {
            if (!error?.response || [500, 502, 503, 504].includes(error.response.status)) {
                void checkNow();
            }

            return Promise.reject(error);
        },
    );

    void checkNow();

    return {
        checkNow,
        stop() {
            stopped = true;
            window.clearTimeout(pollTimer);
            requestController?.abort();
            banner?.remove();

            if (interceptorId !== undefined) {
                axiosClient.interceptors.response.eject(interceptorId);
            }
        },
    };
}
