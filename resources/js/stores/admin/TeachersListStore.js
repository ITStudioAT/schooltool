import { defineStore } from 'pinia'
import { teachersListApi } from '@/domains/teachersList/api'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useTeachersListStore = defineStore('AdminTeachersListStore', {
    state: () => ({
        teachers: [],
        selected_teachers: [],
        search_string: '',
        meta: {
            current_page: 1,
            per_page: 0,
            total: 0,
            last_page: 1,
            from: 0,
            to: 0,
        },
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
                const response = await axios.get(teachersListApi.index(), { params: { role, search_string, page } })
                this.teachers = response.data.items ?? []
                const pagination = response.data.pagination ?? {}
                const currentPage = Number(pagination.current_page || 1)
                const perPage = Number(pagination.per_page || 0)
                const total = Number(pagination.total || 0)
                this.meta = {
                    current_page: currentPage,
                    per_page: perPage,
                    total,
                    last_page: Number(pagination.last_page || 1),
                    from: total === 0 ? 0 : (currentPage - 1) * perPage + 1,
                    to: total === 0 ? 0 : Math.min(currentPage * perPage, total),
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
            adminStore.is_loading++
            try {
                const response = await axios.put(teachersListApi.update(data.id), data)
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
                const response = await axios.post(teachersListApi.store(), data)
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
                this.answer = await axios.post(teachersListApi.deleteTeachers(), { data })

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
    },
})
