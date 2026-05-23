import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

const baseUrls = {
    admins: '/api/admin/students-timetables/admin-users',
    moderators: '/api/admin/students-timetables/moderator-users',
}

export const useStudentsTimetablesAdminUserStore = defineStore('AdminStudentsTimetablesAdminUserStore', {
    state: () => ({
        admin_users: [],
        selected_admin_users: [],
        search_string: '',
        role_key: 'admins',
        meta: [],
        data: {},
        answer: null,
        saved_admin_user: null,
    }),

    actions: {
        baseUrl(roleKey = null) {
            const resolvedRoleKey = roleKey || this.role_key

            return baseUrls[resolvedRoleKey] || baseUrls.admins
        },

        async index(page = null, roleKey = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            const search_string = this.search_string

            try {
                const response = await axios.get(this.baseUrl(roleKey), { params: { search_string, page } })
                this.admin_users = response.data.data
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

        async store(data, roleKey = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++

            try {
                const response = await axios.post(this.baseUrl(roleKey), data)
                this.saved_admin_user = response.data

                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: adminStore.config?.timeout,
                })

                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async update(data, roleKey = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++

            try {
                const response = await axios.put(`${this.baseUrl(roleKey)}/${data.id}`, data)
                this.saved_admin_user = response.data

                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: adminStore.config?.timeout,
                })

                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async toggleIsActive(user_id, roleKey = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++

            try {
                this.answer = await axios.post(`${this.baseUrl(roleKey)}/toggle-active`, { user_id })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: adminStore.config?.timeout,
                })

                return false
            } finally {
                adminStore.is_loading--
            }
        },
    },
})
