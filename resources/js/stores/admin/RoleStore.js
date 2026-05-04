import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useRoleStore = defineStore('AdminRoleStore', {
    state: () => ({
        roles: [],
        selected_role: null,
        roles_load_promise: null,
    }),

    actions: {
        async loadRoles(force = false) {
            if (!force && this.roles.length > 0) return true
            if (this.roles_load_promise) {
                try {
                    return await this.roles_load_promise
                } catch (_) {
                    return false
                }
            }

            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++

            this.roles_load_promise = (async () => {
                const response = await axios.get(`/api/admin/roles/load_roles`, {})
                this.roles = response.data
                return true
            })()

            try {
                return await this.roles_load_promise
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                this.roles_load_promise = null
                adminStore.is_loading--
            }
        },
    },
})
