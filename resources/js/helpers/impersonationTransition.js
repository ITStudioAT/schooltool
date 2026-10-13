import { readonly, shallowRef } from 'vue';

const transition = shallowRef('');
export const impersonationTransition = readonly(transition);

export function startImpersonationTransition({ axiosClient, state = transition, origin = window.location.origin }) {
    let requestId = 0;

    function actionFor(config) {
        if (String(config?.method).toLowerCase() !== 'post') {
            return '';
        }
        try {
            const url = new URL(config.url, config.baseURL || origin);
            if (url.origin !== origin) {
                return '';
            }
            if (url.pathname === '/api/admin/impersonation/stop') {
                return 'stop';
            }
            return ['/api/admin/impersonation/start', '/api/admin/students-timetables/robot/students/impersonate']
                .includes(url.pathname) ? 'start' : '';
        } catch {
            return '';
        }
    }

    const requestInterceptor = axiosClient.interceptors.request.use(config => {
        const action = actionFor(config);
        if (action) {
            config.__impersonationTransitionId = ++requestId;
            state.value = action;
        }
        return config;
    });
    const responseInterceptor = axiosClient.interceptors.response.use(response => response, error => {
        if (error?.config?.__impersonationTransitionId === requestId && actionFor(error.config)) {
            state.value = '';
        }
        return Promise.reject(error);
    });
    const restore = event => {
        if (event.persisted) {
            state.value = '';
        }
    };
    window.addEventListener('pageshow', restore);

    return {
        stop() {
            axiosClient.interceptors.request.eject(requestInterceptor);
            axiosClient.interceptors.response.eject(responseInterceptor);
            window.removeEventListener('pageshow', restore);
            state.value = '';
        },
    };
}
