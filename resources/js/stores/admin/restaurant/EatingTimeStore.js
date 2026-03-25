import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useEatingTimeStore = defineStore('AdminRestaurantEatingTimeStore', {
    state: () => ({
        eatingTimes: [],
        isLoaded: false,
    }),

    getters: {
        eatingTimeCount: (state) => state.eatingTimes.length,
        sortedEatingTimes: (state) => [...state.eatingTimes].sort((a, b) => a.eating_time.localeCompare(b.eating_time)),
    },

    actions: {
        async load() {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()

            adminStore.is_loading++

            try {
                const response = await axios.get('/api/admin/restaurant/eating-times')

                this.eatingTimes = response?.data?.data || []
                this.isLoaded = true

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Speisezeiten konnten nicht geladen werden.',
                    type: 'error',
                    timeout: 3000,
                })

                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async store(eatingTime) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()

            adminStore.is_loading++

            try {
                const response = await axios.post('/api/admin/restaurant/eating-times', {
                    eating_time: eatingTime,
                })

                const newEntry = response?.data?.data

                if (newEntry) {
                    this.eatingTimes.push(newEntry)
                }

                notification.notify({
                    message: response?.data?.message || 'Speisezeit wurde gespeichert.',
                    type: 'success',
                    timeout: 2200,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Speisezeit konnte nicht gespeichert werden.',
                    type: 'error',
                    timeout: 3000,
                })

                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async update(id, eatingTime) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()

            adminStore.is_loading++

            try {
                const response = await axios.put(`/api/admin/restaurant/eating-times/${id}`, {
                    eating_time: eatingTime,
                })

                const updated = response?.data?.data

                if (updated) {
                    const index = this.eatingTimes.findIndex((entry) => entry.id === id)

                    if (index !== -1) {
                        this.eatingTimes[index] = updated
                    }
                }

                notification.notify({
                    message: response?.data?.message || 'Speisezeit wurde aktualisiert.',
                    type: 'success',
                    timeout: 2200,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Speisezeit konnte nicht aktualisiert werden.',
                    type: 'error',
                    timeout: 3000,
                })

                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async destroy(id) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()

            adminStore.is_loading++

            try {
                const response = await axios.delete(`/api/admin/restaurant/eating-times/${id}`)

                this.eatingTimes = this.eatingTimes.filter((entry) => entry.id !== id)

                notification.notify({
                    message: response?.data?.message || 'Speisezeit wurde gelöscht.',
                    type: 'success',
                    timeout: 2200,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Speisezeit konnte nicht gelöscht werden.',
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
