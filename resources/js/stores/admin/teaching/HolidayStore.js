import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useHolidayStore = defineStore('AdminHolidayStore', {
    state: () => {
        return {
            holidays: [],
            my_holidays: [],
        }
    },

    actions: {
        async index() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get('/api/admin/teaching/holidays')
                this.holidays = (response.data?.data || []).filter((holiday) => holiday?.scope === 'school')
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

        async store(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post('/api/admin/teaching/holidays', data)
                notification.notify({
                    message: 'Freie Tage wurden gespeichert.',
                    type: 'success',
                    timeout: 2200,
                })
                return response.data
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
                await axios.delete(`/api/admin/teaching/holidays/${id}`)
                notification.notify({
                    message: 'Freier Tag wurde gelöscht.',
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
        async destroyMany(ids) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const uniqueIds = [
                ...new Set(
                    (ids || [])
                        .map((id) => Number(id))
                        .filter((id) => Number.isInteger(id) && id > 0),
                ),
            ]
            if (!uniqueIds.length) return { success: false, deleted: 0, failed: 0 }

            adminStore.is_loading++
            try {
                const results = await Promise.allSettled(uniqueIds.map((id) => axios.delete(`/api/admin/teaching/holidays/${id}`)))
                const failed = results.filter((result) => result.status === 'rejected').length
                const deleted = uniqueIds.length - failed

                if (failed === 0) {
                    notification.notify({
                        message: `${deleted} freie Tage wurden gelöscht.`,
                        type: 'success',
                        timeout: 2200,
                    })
                } else {
                    notification.notify({
                        message: `${deleted} freie Tage gelöscht, ${failed} konnten nicht gelöscht werden.`,
                        type: 'error',
                        timeout: 3200,
                    })
                }

                return { success: failed === 0, deleted, failed }
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return { success: false, deleted: 0, failed: uniqueIds.length }
            } finally {
                adminStore.is_loading--
            }
        },

        async indexMine() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get('/api/admin/teaching/my_holidays')
                this.my_holidays = response.data?.data || []
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

        async storeMine(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post('/api/admin/teaching/my_holidays', data)
                notification.notify({
                    message: 'Eigene freie Tage wurden gespeichert.',
                    type: 'success',
                    timeout: 2200,
                })
                return response.data
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

        async destroyMine(id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                await axios.delete(`/api/admin/teaching/my_holidays/${id}`)
                notification.notify({
                    message: 'Eigener freier Tag wurde gelöscht.',
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
