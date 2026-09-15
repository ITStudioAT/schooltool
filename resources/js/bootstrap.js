import axios from 'axios';
import { startDeploymentNotice } from './helpers/deploymentNotice';
import '../css/deployment-notice.css';
window.axios = axios;
axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;
axios.defaults.baseURL = window.location.origin;
window.axios.defaults.headers.common['Accept'] = 'application/json';
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.axios.interceptors.request.use((config) => {
    if (window.location.protocol === 'https:' && typeof config.url === 'string' && config.url.startsWith('http://')) {
        config.url = config.url.replace(/^http:\/\//, 'https://');
    }
    return config;
});

let csrfRefreshPromise = null;

function clearCsrfHeaders(config) {
    if (!config?.headers) {
        return;
    }

    delete config.headers['X-CSRF-TOKEN'];
    delete config.headers['x-csrf-token'];
    delete config.headers['X-XSRF-TOKEN'];
    delete config.headers['x-xsrf-token'];
}

async function refreshCsrfCookie() {
    if (csrfRefreshPromise) {
        return csrfRefreshPromise;
    }

    csrfRefreshPromise = window.axios.get('/sanctum/csrf-cookie', {
        __skipCsrfRetry: true,
    }).finally(() => {
        csrfRefreshPromise = null;
    });

    return csrfRefreshPromise;
}

window.ensureCsrfCookie = async function () {
    if (document.cookie.split('; ').some((cookie) => cookie.startsWith('XSRF-TOKEN='))) {
        return null;
    }

    return refreshCsrfCookie();
};

window.axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const response = error?.response;
        const originalConfig = error?.config;

        if (!response || response.status !== 419 || !originalConfig) {
            return Promise.reject(error);
        }

        if (originalConfig.__skipCsrfRetry || originalConfig.__retriedAfterCsrfRefresh) {
            return Promise.reject(error);
        }

        originalConfig.__retriedAfterCsrfRefresh = true;
        clearCsrfHeaders(originalConfig);

        try {
            await refreshCsrfCookie();
            return window.axios(originalConfig);
        } catch (refreshError) {
            return Promise.reject(error);
        }
    }
);

const deploymentNotice = startDeploymentNotice({ axiosClient: window.axios });

if (import.meta.hot) {
    import.meta.hot.dispose(() => deploymentNotice.stop());
}
