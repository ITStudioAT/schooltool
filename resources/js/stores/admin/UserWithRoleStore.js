import { defineStore } from 'pinia'
import { useAdminStore } from "@/stores/admin/AdminStore";
import { createResourceStore } from "./ResourceStore";
import { useNotificationStore } from "@/stores/spa/NotificationStore";
const resourceStore = createResourceStore('users_with_roles');

export const useUserWithRoleStore = defineStore("AdminUserWithRoleStore", {
    state: () => ({
        ...resourceStore.state(),
        roles: [],

    }),

    actions: {
        ...resourceStore.actions,

        async loadRoles() {
            const notification = useNotificationStore();
            const adminStore = useAdminStore();
            adminStore.is_loading++;
            this.api_answer = null;
            try {
                const response = await axios.get('/api/admin/users_with_roles/roles', {});
                this.roles = response.data.roles;
                return true;
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: adminStore.config?.timeout,
                });
                return false;
            } finally {
                adminStore.is_loading--;
            }
        },

        async saveUserRoles(data) {
            const notification = useNotificationStore();
            const adminStore = useAdminStore();
            adminStore.is_loading++;

            try {
                const response = await axios.post('/api/admin/users_with_roles/roles', data);
                notification.notify({
                    message: 'Das Speichern war erfolgreich',
                    type: 'success',
                    timeout: this.timeout,
                });
                return true;
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: adminStore.config?.timeout,
                });
                return false;
            } finally {
                adminStore.is_loading--;
            }
        }
    },
});
