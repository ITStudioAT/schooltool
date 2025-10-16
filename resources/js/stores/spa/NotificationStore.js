// stores/NotificationStore.js
import { defineStore } from 'pinia';

export const useNotificationStore = defineStore('notification', {
    state: () => ({
        show: false,
        status: null,
        message: '',
        type: 'success', // success, error, warning, info
        timeout: 3000,
    }),

    actions: {
        notify({ status, message, type = 'success', timeout = 3000 }) {
            this.status = status;
            this.message = message;
            this.type = type;
            this.timeout = timeout;
            this.show = true;
        }
    }
});
