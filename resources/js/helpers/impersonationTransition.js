import { readonly, shallowRef } from 'vue';
import { CanceledError } from 'axios';

export const OBSOLETE_AUTH_CONTEXT_STATUS = -1;

const transition = shallowRef('');
export const impersonationTransition = readonly(transition);

export function startImpersonationTransition({ axiosClient, state = transition, origin = window.location.origin }) {
    let requestId = 0;

    function ownUrl(config) {
        try {
            const url = new URL(config.url, config.baseURL || origin);
            return url.origin === origin ? url : null;
        } catch {
            return null;
        }
    }

    function obsoleteRequest(config) {
        const error = new CanceledError('Account context changed.', config);
        // Older store catches require a response; this internal status never represents a server response.
        error.response = { status: OBSOLETE_AUTH_CONTEXT_STATUS, data: { message: '' } };
        return error;
    }

    function isObsolete(config) {
        return typeof config?.__authContextGeneration === 'number' && config.__authContextGeneration !== requestId;
    }

    function actionFor(config) {
        if (String(config?.method).toLowerCase() !== 'post') {
            return '';
        }
        const url = ownUrl(config);
        if (url) {
            if (['/api/admin/hopper_accounts/switch', '/api/admin/schools/switch_school'].includes(url.pathname)) {
                return 'switch';
            }
            if (url.pathname === '/api/admin/impersonation/stop') {
                return 'stop';
            }
            return ['/api/admin/impersonation/start', '/api/admin/students-timetables/robot/students/impersonate']
                .includes(url.pathname) ? 'start' : '';
        }
        return '';
    }

    const requestInterceptor = axiosClient.interceptors.request.use(config => {
        const action = actionFor(config);
        if (action) {
            config.__impersonationTransitionId = ++requestId;
            state.value = action;
        }
        const url = ownUrl(config);
        if (url && (url.pathname.startsWith('/api/') || url.pathname === '/sanctum/csrf-cookie')) {
            config.__authContextGeneration = requestId;
            const navigationConfig = url.pathname === '/api/admin/config' && config.__authContextNavigation === true;
            if (state.value && !action && !navigationConfig && url.pathname !== '/sanctum/csrf-cookie') {
                throw obsoleteRequest(config);
            }
        }
        return config;
    });
    const responseInterceptor = axiosClient.interceptors.response.use(response => {
        if (isObsolete(response.config)) {
            return Promise.reject(obsoleteRequest(response.config));
        }
        return response;
    }, error => {
        if (isObsolete(error.config)) {
            return Promise.reject(obsoleteRequest(error.config));
        }
        if (error?.config?.__impersonationTransitionId === requestId && actionFor(error.config)) {
            state.value = '';
        }
        return Promise.reject(error);
    });
    const restore = event => {
        if (event.persisted) {
            requestId++;
            if (state.value) {
                window.location.reload();
            }
        }
    };
    window.addEventListener('pageshow', restore);

    return {
        stop() {
            requestId++;
            axiosClient.interceptors.request.eject(requestInterceptor);
            axiosClient.interceptors.response.eject(responseInterceptor);
            window.removeEventListener('pageshow', restore);
            state.value = '';
        },
    };
}
