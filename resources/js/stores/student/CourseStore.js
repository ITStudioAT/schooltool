import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useCourseStore = defineStore('StudentCourseStore', {
    state: () => {
        return {
            courses: [],
            entries: [],
            typeLabels: {},
        }
    },

    actions: {
        async getCourses() {
            try {
                const response = await axios.get('/api/homepage/student/courses')
                this.courses = response.data?.courses ?? []
                return true
            } catch (error) {
                const notification = useNotificationStore()
                notification.notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'Fehler beim Laden der Fächer.',
                    type: 'error',
                    timeout: 3000,
                })
                this.courses = []
                return false
            }
        },

        async getCourseEntries(courseId) {
            try {
                const response = await axios.get(`/api/homepage/student/courses/${courseId}/entries`)
                this.entries = response.data?.entries ?? []
                this.typeLabels = response.data?.type_labels ?? {}
                return true
            } catch (error) {
                const notification = useNotificationStore()
                notification.notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'Fehler beim Laden der Einträge.',
                    type: 'error',
                    timeout: 3000,
                })
                this.entries = []
                this.typeLabels = {}
                return false
            }
        },
    },
})
