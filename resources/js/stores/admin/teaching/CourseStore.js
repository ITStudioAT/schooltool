import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useCourseStore = defineStore('AdminCourseStore', {
    state: () => {
        return {
            courses: [],
            classes: [],
            selected_course: null,
            selected_course_id: null,
            selected_course_student: null,
            previous_selected_student: null,
            previous_show_infos: null,
            previous_show_dates: null,
            show_my_courses: true,
            show_students: true,
            show_infos: true,
            show_works: true,
            show_dates: true,
        }
    },

    actions: {
        ensureCourseStudentCollections(course) {
            if (!course) return
            const normalizeIds = (items) =>
                (Array.isArray(items) ? items : [])
                    .map((item) => {
                        if (item == null) return null
                        if (typeof item === 'object') return item.id ?? null
                        return item
                    })
                    .filter((id) => id !== null && id !== undefined)

            // students -> students_info
            if (Array.isArray(course.students) && course.students.length && typeof course.students[0] === 'object') {
                course.students_info = course.students
                course.students = normalizeIds(course.students_info)
            } else if (course.students && typeof course.students === 'object' && !Array.isArray(course.students)) {
                course.students_info = Array.isArray(course.students.data) ? course.students.data : []
                course.students = normalizeIds(course.students_info)
            } else {
                if (!Array.isArray(course.students)) course.students = []
                if (!Array.isArray(course.students_info)) course.students_info = []
                // critical: backfill ids from info to avoid accidental empty payloads on save
                if (course.students.length === 0 && course.students_info.length > 0) {
                    course.students = normalizeIds(course.students_info)
                }
            }

            // students_deleted -> students_deleted_info
            if (Array.isArray(course.students_deleted) && course.students_deleted.length && typeof course.students_deleted[0] === 'object') {
                course.students_deleted_info = course.students_deleted
                course.students_deleted = normalizeIds(course.students_deleted_info)
            } else if (course.students_deleted && typeof course.students_deleted === 'object' && !Array.isArray(course.students_deleted)) {
                course.students_deleted_info = Array.isArray(course.students_deleted.data) ? course.students_deleted.data : []
                course.students_deleted = normalizeIds(course.students_deleted_info)
            } else {
                if (!Array.isArray(course.students_deleted)) course.students_deleted = []
                if (!Array.isArray(course.students_deleted_info)) course.students_deleted_info = []
                if (course.students_deleted.length === 0 && course.students_deleted_info.length > 0) {
                    course.students_deleted = normalizeIds(course.students_deleted_info)
                }
            }
        },

        async index() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const selectedId = this.selected_course?.id
                const response = await axios.get(`/api/admin/teaching/courses`, {})
                this.courses = response.data.data
                this.classes = response.data.classes
                if (selectedId) {
                    this.selected_course = this.courses.find((c) => c.id === selectedId) || null
                    this.ensureCourseStudentCollections(this.selected_course)
                }
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
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

            if (!data?.teaching_schema_id) {
                notification.notify({
                    status: 422,
                    message: 'Bitte ein Benotungsschema auswählen.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            }

            adminStore.is_loading++
            try {
                this.ensureCourseStudentCollections(data)
                const response = await axios.put(`/api/admin/teaching/courses/${data.id}`, data)
                return response.data
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                this.error = error
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async store(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()

            if (!data?.teaching_schema_id) {
                notification.notify({
                    status: 422,
                    message: 'Bitte ein Benotungsschema auswählen.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            }

            adminStore.is_loading++
            try {
                this.ensureCourseStudentCollections(data)
                const response = await axios.post(`/api/admin/teaching/courses`, data)
                this.saved_offer = response.data
                return response.data
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                this.error = error
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
                await axios.delete(`/api/admin/teaching/courses/${id}`)
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
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
