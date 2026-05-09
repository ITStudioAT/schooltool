import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useRequestStore = defineStore('AdminTutoringRequestStore', {
    state: () => {
        return {
            requests: null,
            selected_requests: [],
            search_string: '',
            meta: null,
            select_status: 'all',
        }
    },

    actions: {
        async index(page = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get(`/api/admin/tutoring/requests`, {
                    params: {
                        search_string: this.search_string,
                        page,
                        select_status: this.select_status,
                    },
                })
                this.requests = response.data.data
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
    },
})
