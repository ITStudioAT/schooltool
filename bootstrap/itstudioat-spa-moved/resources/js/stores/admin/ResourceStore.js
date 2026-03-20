import { useAdminStore } from "@/stores/admin/AdminStore";
import { useNotificationStore } from "@/stores/spa/NotificationStore";

export function createResourceStore(modelName) {
    return {
        state: () => ({
            items: [],
            item: null,
            is_loading: false,
            error: {
                status: null,
                message: null,
                is_error: false,
                is_success: false,
            },
            router: null,
            api_answer: null,
            timeout: 3000,
            reload: 0,
        }),

        actions() {
            return {
                initialize(router) {
                    this.router = router;
                },

                async index() {
                    const notification = useNotificationStore();
                    const adminStore = useAdminStore();
                    adminStore.is_loading++;
                    try {
                        const response = await axios.get(`/api/admin/${modelName}`);
                        this.items = response.data;
                        return true;
                    } catch (error) {
                        notification.notify({
                            status: error.response.status,
                            message: error.response.data.message || 'Fehler passiert.',
                            type: 'error',
                            timeout: this.timeout,
                        });
                        return false;
                    } finally {
                        this.is_loading = false;
                    }
                },

                async show(id) {
                    const notification = useNotificationStore();
                    const adminStore = useAdminStore();
                    adminStore.is_loading++;
                    try {
                        const response = await axios.get(`/api/admin/${modelName}/${id}`);
                        this.item = response.data;
                        return true;
                    } catch (error) {
                        notification.notify({
                            status: error.response.status,
                            message: error.response.data.message || 'Fehler passiert.',
                            type: 'error',
                            timeout: this.timeout,
                        });
                        return false;
                    } finally {
                        adminStore.is_loading--;
                    }
                },

                async update(data) {

                    const notification = useNotificationStore();
                    const adminStore = useAdminStore();
                    adminStore.is_loading++;
                    try {
                        const response = await axios.put(`/api/admin/${modelName}/${data.id}`, data);
                        if (response.data.answer) {
                            this.api_answer = response.data;
                        } else {
                            this.item = response.data;
                        }
                        return true;
                    } catch (error) {
                        notification.notify({
                            status: error.response.status,
                            message: error.response.data.message || 'Fehler passiert.',
                            type: 'error',
                            timeout: this.timeout,
                        });
                        return false;
                    } finally {
                        adminStore.is_loading--;
                    }
                },

                async destroy(data) {
                    const notification = useNotificationStore();
                    const adminStore = useAdminStore();
                    adminStore.is_loading++;
                    try {
                        const response = await axios.delete('/api/admin/users/' + data.id, {});
                        return true;
                    } catch (error) {
                        notification.notify({
                            status: error.response.status,
                            message: error.response.data.message || 'Fehler passiert.',
                            type: 'error',
                            timeout: this.timeout,
                        });
                        return false;
                    } finally {
                        adminStore.is_loading--;
                    }
                },

            };
        }
    };
}
