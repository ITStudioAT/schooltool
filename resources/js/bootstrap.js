import axios from 'axios';
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
