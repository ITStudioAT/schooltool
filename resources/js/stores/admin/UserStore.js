import { defineStore } from 'pinia'
import { useAdminStore } from "@/stores/admin/AdminStore";
import { createResourceStore } from "./ResourceStore";
import { useNotificationStore } from "@/stores/spa/NotificationStore";

const resourceStore = createResourceStore('users');

export const useUserStore = defineStore("AdminUserStore", {
    state: () => ({
        ...resourceStore.state(),
    }),

    actions: {
        ...resourceStore.actions(),

        async updateProfile(data) {
            const notification = useNotificationStore();
            const adminStore = useAdminStore();
            adminStore.is_loading++;
            try {
                const response = await axios.put('/api/admin/users/update_profile/' + data.id, data);
                if (response.data.answer) {
                    this.api_answer = response.data;
                } else {
                    this.item = response.data;
                    notification.notify({
                        message: 'Das Profil wurde erfolreich gespeichert.',
                        type: 'success',
                        timeout: resourceStore.timeout,
                    });
                }
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
        },

        async updateWithCode(data) {
            const notification = useNotificationStore();
            const adminStore = useAdminStore();
            adminStore.is_loading++;
            try {
                const response = await axios.post('/api/admin/users/update_with_code/', data);
                this.item = response.data;
                notification.notify({
                    message: 'Die Profil mit geänderter E-Mail wurde erfolreich gespeichert.',
                    type: 'success',
                    timeout: resourceStore.timeout,
                });
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
        },

        async savePassword(data) {
            const notification = useNotificationStore();
            const adminStore = useAdminStore();
            adminStore.is_loading++;
            this.api_answer = null;
            try {
                const response = await axios.post('/api/admin/users/save_password/', data);
                if (response.data.step) {
                    return response.data.step;
                }
                return true;
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.adminStore?.timeout,
                });
                return false;
            } finally {
                adminStore.is_loading--;
            }
        },

        async savePasswordWithCode(data) {
            const notification = useNotificationStore();
            const adminStore = useAdminStore();
            adminStore.is_loading++;
            this.api_answer = null;
            try {
                const response = await axios.post('/api/admin/users/save_password_with_code/', data);
                notification.notify({
                    message: 'Das Kennwort wurde erfolreich gespeichert.',
                    type: 'success',
                    timeout: resourceStore.timeout,
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
        },


        async save2Fa(data) {
            const notification = useNotificationStore();
            const adminStore = useAdminStore();
            adminStore.is_loading++;
            this.api_answer = null;
            try {
                const response = await axios.post('/api/admin/users/save_2fa/', data);
                await this.show(data.id);
                if (response.data.result) {
                    return response.data.result;
                }
                return true;
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.adminStore?.timeout,
                });
                return false;
            } finally {
                adminStore.is_loading--;
            }
        },

        async save2FaWithCode(data) {
            console.log(data);
            const notification = useNotificationStore();
            const adminStore = useAdminStore();
            adminStore.is_loading++;
            this.api_answer = null;
            try {
                const response = await axios.post('/api/admin/users/save_2fa_with_code/', data);
                await this.show(data.id);
                if (response.data.result) {
                    return response.data.result;
                }
                return true;
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.adminStore?.timeout,
                });
                return false;
            } finally {
                adminStore.is_loading--;
            }
        },

        async confirm(ids) {
            const notification = useNotificationStore();
            const adminStore = useAdminStore();
            adminStore.is_loading++;
            this.api_response = null;
            try {
                this.api_answer = await axios.post("/api/admin/users/confirm", { ids });

                notification.notify({
                    message: 'Die Benutzer wurden bestätigt.',
                    type: 'success',
                    timeout: resourceStore.timeout,
                });
                return this.api_answer;
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                });
                return false;
            } finally {
                adminStore.is_loading--;
            }
        },

        async sendVerificationEmail(ids) {
            const notification = useNotificationStore();
            const adminStore = useAdminStore();
            adminStore.is_loading++;
            this.api_response = null;
            try {
                this.api_answer = await axios.post("/api/admin/users/send_verification_email", { ids });

                notification.notify({
                    message: 'Die E-Mail/s zur Verifikation wurde/n versandt.',
                    type: 'success',
                    timeout: resourceStore.timeout,
                });
                return this.api_answer;
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                });
                return false;
            } finally {
                adminStore.is_loading--;
            }
        },

        async emailVerification(email, uuid) {
            const notification = useNotificationStore();
            const adminStore = useAdminStore();
            adminStore.is_loading++;
            try {
                this.api_answer = (await axios.post("/api/admin/users/email_verification", { email, uuid })).data;
                return this.api_answer;
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                });
                return false;
            } finally {
                adminStore.is_loading--;
            }
        },

        async sendVerificationEmailInitializedFromUser(email) {
            const notification = useNotificationStore();
            const adminStore = useAdminStore();
            adminStore.is_loading++;
            this.api_response = null;
            try {
                this.api_answer = (await axios.post("/api/admin/users/send_verification_email_initialized_from_user", { email })).data;
                return this.api_answer;
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                });
                return false;
            } finally {
                adminStore.is_loading--;
            }
        },

        async saveUserRoles(user_ids, role_ids) {
            const notification = useNotificationStore();
            const adminStore = useAdminStore();
            adminStore.is_loading++;
            try {
                this.api_answer = (await axios.post("/api/admin/users/save_user_roles", { user_ids, role_ids })).data;

                notification.notify({
                    message: 'Den Benutzern wurden die neuen Rollen zugeordnet.',
                    type: 'success',
                    timeout: resourceStore.timeout,
                });

                return this.api_answer;
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                });
                return false;
            } finally {
                adminStore.is_loading--;
            }
        },
    },
});
