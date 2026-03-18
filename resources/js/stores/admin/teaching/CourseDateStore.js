import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useCourseDateStore = defineStore('AdminCourseDateStore', {
    state: () => {
        return {
            courseDates: [],
            selected_courseDate: null,
        }
    },

    actions: {
        async index(courseId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const selectedId = this.selected_courseDate?.id
                const response = await axios.get(`/api/admin/teaching/course_dates`, {
                    params: { course_id: courseId },
                })
                this.courseDates = response.data.data
                if (selectedId) {
                    this.selected_courseDate = this.courseDates.find((d) => d.id === selectedId) || null
                }
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
                const response = await axios.post(`/api/admin/teaching/course_dates`, data)
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

        async update(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            if (!data?.id) {
                notification.notify({
                    status: 422,
                    message: 'Termin-ID fehlt. Bitte Seite neu laden.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            }
            adminStore.is_loading++
            try {
                const response = await axios.put(`/api/admin/teaching/course_dates/${data.id}`, data)
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

        async destroy(dateId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                await axios.delete(`/api/admin/teaching/course_dates/${dateId}`)
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

        async updateStatus(dateId, statusOrPayload) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const payload = Array.isArray(statusOrPayload) ? { status: statusOrPayload } : statusOrPayload || {}
                const response = await axios.patch(`/api/admin/teaching/course_dates/${dateId}/status`, payload)
                return response?.data?.data || response?.data || null
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

        clearDates() {
            this.courseDates = []
        },
    },
})
