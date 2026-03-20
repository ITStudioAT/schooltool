import { defineStore } from 'pinia'
import { useAdminStore } from "@/stores/admin/AdminStore";
import { useNotificationStore } from "@/stores/spa/NotificationStore";

export const useNavigationStore = defineStore("AdminNavigationStore", {

    state: () => ({
        menu: [],
        selection: [],
    }),


    actions: {

        async loadMenu(action) {
            const notification = useNotificationStore();
            const adminStore = useAdminStore();
            adminStore.is_loading++;
            try {
                const response = await axios.get('/api/admin/navigation/' + action, {});
                this.menu = response.data.menu || [];
                this.selection = response.data.selection || [];
                return true;
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: resourceStore.timeout,
                });
                return false;
            } finally {
                adminStore.is_loading--;
            }
        }


    }
})

