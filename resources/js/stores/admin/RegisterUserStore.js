import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useRegisterUserStore = defineStore('AdminRegisterUserStore', {
    state: () => ({
        register_users: [],
        selected_register_users: [],
        search_string: '',
        meta: [],
        data: {},
        saved_school: null,
        answer: null,
        switchable_register_users: [],
        school_licences: [],
        school_admins: [],
        register_id: null,
        count_deleted: 0,
        count_deletable_users: 0,
    }),

    actions: {
        async index(page = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            const register_id = this.register_id
            const search_string = this.search_string
            try {
                const response = await axios.get(`/api/admin/register_users`, { params: { register_id, search_string, page } })
                this.register_users = response.data.data
                this.meta = response.data.meta
                this.count_deletable_users = response.data.count_deletable_users
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

        async deleteRegisterUsers() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            const register_id = this.register_id
            try {
                this.answer = await axios.post(`/api/admin/register_users/delete_register_users`, { register_id })
                this.count_deleted = this.answer.count
                notification.notify({
                    message: 'Die Benutzer wurden bereinigt.',
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
    },
})
