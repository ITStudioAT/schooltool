import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useTutoringUserStore = defineStore('AdminTutoringUserStore', {
    state: () => ({
        users: [],
        selected_users: [],
        search_string: '',
        meta: [],
        user: null,
        error: null,
        data: {},
        count_deletable_users: 0,
        selected_filter: null,
    }),

    actions: {
        async index(page = null) {
            this.error = null
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            const search_string = this.search_string
            const selected_filter = this.selected_filter
            try {
                const response = await axios.get('/api/admin/tutoring/users', { params: { search_string, selected_filter, page } })
                this.users = response.data.data
                this.meta = response.data.meta
                this.count_deletable_users = response.data.count_deletable_users
                return true
            } catch (error) {
                this.error = error
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Laden der Fächer.',
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
                const response = await axios.put(`/api/admin/tutoring/users/${data.id}`, data)
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
                const response = await axios.post(`/api/admin/tutoring/users`, data)
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
                this.answer = await axios.post(`/api/admin/tutoring/delete_users`, { data })

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

        async cleanUsers() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/tutoring/clean_users`, {})
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
