import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { updateClassHead } from '@/actions/App/Http/Controllers/Admin/TeacherController'

export const useTeacherStore = defineStore('AdminTeacherStore', {
    state: () => ({
        teachers: [],
        classes: [],
        selected_teachers: [],
        search_string: '',
        meta: [],
        data: {},
        answer: null,
        switchable_teachers: [],
        saved_teacher: null,
        role: '',
    }),

    actions: {
        async index(page = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            const search_string = this.search_string
            const role = this.role
            try {
                const response = await axios.get(`/api/admin/teachers`, { params: { role, search_string, page } })
                this.teachers = response.data.data
                this.classes = response.data.classes ?? []
                this.meta = response.data.meta
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

        async saveClassHeads(teacherId, classNames) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++

            try {
                const response = await axios.put(updateClassHead.url(teacherId), { class_names: classNames })
                const teacher = this.teachers.find((item) => item.id === teacherId)
                if (teacher) {
                    teacher.class_head_classes = response.data.class_names
                }
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Klassenvorstand konnte nicht gespeichert werden.',
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
            adminStore.is_loading++
            try {
                const response = await axios.put(`/api/admin/teachers/${data.id}`, data)
                this.saved_teacher = response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
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
                const response = await axios.post(`/api/admin/teachers`, data)
                this.saved_teacher = response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async deleteTeachers(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                this.answer = await axios.post(`/api/admin/teachers/delete_teachers`, { data })

                notification.notify({
                    message: 'Löschbare Benutzer wurden gelöscht.',
                    type: 'success',
                    timeout: 3000,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async toggleIsActive(user_id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                this.answer = await axios.post(`/api/admin/users20/toggle_is_active`, { user_id })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },
    },
})
