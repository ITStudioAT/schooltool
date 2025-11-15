import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useHealthStore = defineStore('AdminHealthStore', {
    state: () => ({
        data: null,
        data_2: null,
    }),

    actions: {
        async testQueue() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get(`/api/admin/test-queue`, {})
                this.data = response.data
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

        async checkQueueStatus(test_id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            //adminStore.is_loading++
            try {
                const response = await axios.get(`/api/admin/test-queue/check`, { params: { test_id } })
                this.data_2 = response.data
                return response.data
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                //adminStore.is_loading--
            }
        },
    },
})
