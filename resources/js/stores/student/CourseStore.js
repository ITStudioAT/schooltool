import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useCourseStore = defineStore('StudentCourseStore', {
    state: () => {
        return {
            courses: [],
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
    },
})
