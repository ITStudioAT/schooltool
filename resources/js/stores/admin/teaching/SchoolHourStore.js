import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useSchoolHourStore = defineStore('AdminSchoolHourStore', {
    state: () => {
        return {
            school_hours: [],
            school_hours_loaded: false,
            school_hours_request_promise: null,
            previous_year_import: null,
        }
    },

    actions: {
        async index() {
            if (this.school_hours_request_promise) {
                return this.school_hours_request_promise
            }

            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            this.school_hours_loaded = false
            this.previous_year_import = null
            const requestPromise = (async () => {
                adminStore.is_loading++
                try {
                    const response = await axios.get('/api/admin/teaching/school_hours')
                    this.school_hours = response.data?.data || []
                    this.previous_year_import = response.data?.meta?.previous_year_import || null
                    this.school_hours_loaded = true
                    return true
                } catch (error) {
                    notification.notify({
                        status: error.response?.status,
                        message: error.response?.data?.message || 'Fehler passiert.',
                        type: 'error',
                        timeout: 3000,
                    })
                    return false
                } finally {
                    adminStore.is_loading--
                }
            })()

            this.school_hours_request_promise = requestPromise

            try {
                return await requestPromise
            } finally {
                this.school_hours_request_promise = null
            }
        },

        async store(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post('/api/admin/teaching/school_hours', data)
                const createdCount = Number(response.data?.created || 0)
                notification.notify({
                    message: createdCount > 1 ? `${createdCount} Schulstunden wurden erstellt.` : 'Schulstunde wurde erstellt.',
                    type: 'success',
                    timeout: 2200,
                })
                return response.data?.data || []
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async importPreviousYear() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post('/api/admin/teaching/school-hour-imports')
                const importedCount = Number(response.data?.imported || 0)
                this.school_hours = response.data?.data || []
                this.previous_year_import = null
                this.school_hours_loaded = true
                notification.notify({
                    message: `${importedCount} ${importedCount === 1 ? 'Schulstunde wurde' : 'Schulstunden wurden'} aus dem Vorjahr übernommen.`,
                    type: 'success',
                    timeout: 2600,
                })
                return this.school_hours
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async update(id, data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.put(`/api/admin/teaching/school_hours/${id}`, data)
                notification.notify({
                    message: 'Schulstunde wurde gespeichert.',
                    type: 'success',
                    timeout: 2200,
                })
                return response.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async destroy(id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                await axios.delete(`/api/admin/teaching/school_hours/${id}`)
                notification.notify({
                    message: 'Schulstunde wurde gelöscht.',
                    type: 'success',
                    timeout: 2200,
                })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
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
