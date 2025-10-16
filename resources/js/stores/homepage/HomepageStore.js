import { defineStore } from "pinia";
import { useNotificationStore } from "@/stores/spa/NotificationStore";
import { useAdminStore } from "@/stores/admin/AdminStore";
export const useHomepageStore = defineStore("HomepageStore", {
    state: () => {
        return {
            router: null,
            config: null,
            is_loading: 0,
            error: {
                is_error: false,
                status: null,
                message: null,
                timeout: 3000,
            },
            response: null,
        };
    },

    actions: {
        async loadConfig(school = null, app = null) {
            const adminStore = useAdminStore();
            const notification = useNotificationStore();
            adminStore.is_loading++;
            console.log(school, app);
            try {
                console.log(1);
                this.response = await axios.get("/api/homepage/config", {
                    params: { school, app },
                });
                console.log(this.response);
                this.config = this.response.data;
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || "Fehler passiert.",
                    type: "error",
                    timeout: this.config?.timeout,
                });
                return false;
            } finally {
                adminStore.is_loading--;
            }
        },
    },
});
