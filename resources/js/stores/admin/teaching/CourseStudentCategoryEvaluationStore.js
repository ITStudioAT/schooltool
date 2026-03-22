import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useCourseStudentCategoryEvaluationStore = defineStore('AdminCourseStudentCategoryEvaluationStore', {
    state: () => {
        return {
            evaluations: [],
        }
    },

    actions: {
        async indexByCourse(courseId, semester, userId = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get('/api/admin/teaching/course_student_category_evaluations', {
                    params: {
                        course_id: courseId,
                        semester,
                        user_id: userId,
                    },
                })
                this.evaluations = response.data.data
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
                const response = await axios.post('/api/admin/teaching/course_student_category_evaluations', data)
                const evaluation = response?.data?.data
                if (evaluation) {
                    const matchIndex = this.evaluations.findIndex((item) =>
                        String(item.teaching_course_id) === String(evaluation.teaching_course_id)
                        && String(item.user_id) === String(evaluation.user_id)
                        && String(item.semester) === String(evaluation.semester)
                        && String(item.category_name) === String(evaluation.category_name)
                    )

                    if (matchIndex >= 0) {
                        this.evaluations = this.evaluations.map((item, index) => (index === matchIndex ? evaluation : item))
                    } else {
                        this.evaluations = [...this.evaluations, evaluation]
                    }
                }

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
    },
})
