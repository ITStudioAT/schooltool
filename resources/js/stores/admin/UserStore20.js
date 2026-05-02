import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useUserStore = defineStore('AdminUser20Store', {
    state: () => ({
        users: [],
        selected_users: [],
        search_string: '',
        meta: [],
        data: {},
        answer: null,
        switchable_users: [],
        saved_user: null,
        role: '',
    }),

    actions: {
        async index(page = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            const search_string = this.search_string
            const role = this.role
            try {
                const response = await axios.get(`/api/admin/users20/load_users`, { params: { role, search_string, page } })
                this.users = response.data.data
                this.meta = response.data.meta
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async update(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/users20/update`, data)
                this.saved_user = response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async store(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/users20/store`, data)
                this.saved_user = response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async deleteUsers(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                this.answer = await axios.post(`/api/admin/users20/delete_users`, { data })

                notification.notify({
                    message: 'Die Benutzer wurden gelöscht.',
                    type: 'success',
                    timeout: 3000,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async toggleIsActive(userIdOrIds, isActive = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const userIds = Array.isArray(userIdOrIds) ? userIdOrIds.map((id) => Number(id)).filter((id) => id > 0) : null
                const payload = userIds ? { user_ids: userIds } : { user_id: Number(userIdOrIds) }
                if (typeof isActive === 'boolean') payload.is_active = isActive
                this.answer = await axios.post(`/api/admin/users20/toggle_is_active`, payload)
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async markAccountStatus(userIdOrIds, field) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const userIds = Array.isArray(userIdOrIds) ? userIdOrIds.map((id) => Number(id)).filter((id) => id > 0) : [Number(userIdOrIds)]
                const response = await axios.post(`/api/admin/users20/mark_account_status`, {
                    user_ids: userIds,
                    field,
                })
                for (const updatedUser of response.data.data) {
                    const userIndex = this.users.findIndex((user) => user.id === updatedUser.id)
                    if (userIndex >= 0) {
                        this.users[userIndex] = updatedUser
                    }
                }
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },
    },
})
