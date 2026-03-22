import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useCourseStore = defineStore('StudentCourseStore', {
    state: () => {
        return {
            courses: [],
            course: null,
            entries: [],
            categoryEvaluations: [],
            gradingCategories: [],
            categoryEvaluationValues: [],
            categoryEvaluationDefaultValue: null,
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

        async getCourse(courseId) {
            try {
                const response = await axios.get(`/api/homepage/student/courses/${courseId}`)
                this.course = response.data?.course ?? null
                return true
            } catch (error) {
                const notification = useNotificationStore()
                notification.notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'Fehler beim Laden des Fachs.',
                    type: 'error',
                    timeout: 3000,
                })
                this.course = null
                return false
            }
        },

        async getCourseEntries(courseId) {
            try {
                const response = await axios.get(`/api/homepage/student/courses/${courseId}/entries`)
                this.entries = response.data?.entries ?? []
                this.categoryEvaluations = response.data?.category_evaluations ?? []
                this.gradingCategories = response.data?.grading_categories ?? []
                this.categoryEvaluationValues = response.data?.category_evaluation_values ?? []
                this.categoryEvaluationDefaultValue = response.data?.category_evaluation_default_value ?? null
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
                this.categoryEvaluations = []
                this.gradingCategories = []
                this.categoryEvaluationValues = []
                this.categoryEvaluationDefaultValue = null
                this.typeLabels = {}
                return false
            }
        },
    },
})
