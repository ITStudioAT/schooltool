// stores/NotificationStore.js
import { defineStore } from 'pinia';
import { OBSOLETE_AUTH_CONTEXT_STATUS } from '@/helpers/impersonationTransition';

export const useNotificationStore = defineStore('notification', {
    state: () => ({
        show: false,
        status: null,
        message: '',
        type: 'success', // success, error, warning, info
        timeout: 3000,
        persistent: false,
    }),

    actions: {
        notify({ status, message, type = 'success', timeout = 3000, persistent = false }) {
            if (status === OBSOLETE_AUTH_CONTEXT_STATUS) {
                return;
            }
            this.status = status;
            this.message = message;
            this.type = type;
            this.timeout = timeout;
            this.persistent = persistent;
            this.show = true;
        },
        close() {
            this.show = false;
        }
    }
});
