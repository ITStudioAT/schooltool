import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useRestaurantBillingStore = defineStore('AdminRestaurantBillingStore', {
    state: () => ({
        billings: [],
        weeks: [],
        isLoaded: false,
        error: null,
    }),

    actions: {
        async load() {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()

            adminStore.is_loading++
            this.error = null

            try {
                const response = await axios.get('/api/admin/restaurant/billings')
                this.billings = response?.data?.data || []
                this.weeks = response?.data?.weeks || []
                this.isLoaded = true

                return true
            } catch (error) {
                this.error = error
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Abrechnungen konnten nicht geladen werden.',
                    type: 'error',
                    timeout: 3000,
                })

                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async create(payload) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()

            adminStore.is_loading++
            this.error = null

            try {
                const response = await axios.post('/api/admin/restaurant/billings', payload)
                const billing = response?.data?.data || null

                await this.load()

                notification.notify({
                    message: response?.data?.message || 'Abrechnung wurde erstellt.',
                    type: 'success',
                    timeout: 2200,
                })

                return billing
            } catch (error) {
                this.error = error
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Abrechnung konnte nicht erstellt werden.',
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
