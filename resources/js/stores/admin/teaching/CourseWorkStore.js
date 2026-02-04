import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useCourseWorkStore = defineStore('AdminCourseWorkStore', {
    state: () => {
        return {
            courseWorks: [],
            selected_courseWork: null,
        }
    },

    actions: {
        async index(courseId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const selectedId = this.selected_courseWork?.id
                const response = await axios.get(`/api/admin/teaching/course_works`, {
                    params: { course_id: courseId },
                })
                this.courseWorks = response.data.data
                if (selectedId) {
                    this.selected_courseWork = this.courseWorks.find((w) => w.id === selectedId) || null
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
                const response = await axios.post(`/api/admin/teaching/course_works`, data)
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
                    message: 'Arbeit-ID fehlt. Bitte Seite neu laden.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            }
            adminStore.is_loading++
            try {
                const response = await axios.put(`/api/admin/teaching/course_works/${data.id}`, data)
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

        async destroy(workId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                await axios.delete(`/api/admin/teaching/course_works/${workId}`)
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

        clearWorks() {
            this.courseWorks = []
            this.selected_courseWork = null
        },
    },
})
