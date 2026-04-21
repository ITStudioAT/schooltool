import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useCurriculumStore = defineStore('AdminCurriculumStore', {
    state: () => ({
        curricula: [],
        free_weeks_template: {
            week_keys: [],
            named_ranges: [],
        },
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
        },
        search: '',
        is_loading: false,
    }),

    actions: {
        async index({ page = 1, perPage = null, search = null } = {}) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            this.is_loading = true
            try {
                const params = {
                    page,
                    per_page: perPage ?? this.meta.per_page,
                    search: search ?? this.search,
                }
                const response = await axios.get('/api/admin/teaching/curricula', { params })
                this.curricula = response.data?.data || []
                this.meta = response.data?.meta || this.meta
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
                this.is_loading = false
            }
        },

        async store(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post('/api/admin/teaching/curricula', data)
                notification.notify({
                    message: 'Curriculum wurde angelegt.',
                    type: 'success',
                    timeout: 2200,
                })
                return response.data?.data || null
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

        async show(id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get(`/api/admin/teaching/curricula/${id}`)
                return response.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async loadFreeWeeksTemplate() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get('/api/admin/teaching/curricula/free-weeks-template')
                this.free_weeks_template = {
                    week_keys: response.data?.data?.week_keys || [],
                    named_ranges: response.data?.data?.named_ranges || [],
                }
                return this.free_weeks_template
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async saveFreeWeeksTemplate(template) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.put('/api/admin/teaching/curricula/free-weeks-template', {
                    free_weeks_template: {
                        week_keys: Array.isArray(template?.week_keys) ? template.week_keys : [],
                        named_ranges: Array.isArray(template?.named_ranges) ? template.named_ranges : [],
                    },
                })
                this.free_weeks_template = {
                    week_keys: response.data?.data?.week_keys || [],
                    named_ranges: response.data?.data?.named_ranges || [],
                }
                if (adminStore.config?.user) {
                    adminStore.config.user.teaching_curriculum_free_weeks_template = {
                        week_keys: [...this.free_weeks_template.week_keys],
                        named_ranges: [...this.free_weeks_template.named_ranges],
                    }
                }
                notification.notify({
                    message: 'Vorlage gespeichert.',
                    type: 'success',
                    timeout: 2200,
                })
                return this.free_weeks_template
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async update(id, data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.put(`/api/admin/teaching/curricula/${id}`, data)
                notification.notify({
                    message: 'Curriculum wurde aktualisiert.',
                    type: 'success',
                    timeout: 2200,
                })
                return response.data?.data || null
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
                await axios.delete(`/api/admin/teaching/curricula/${id}`)
                notification.notify({
                    message: 'Curriculum wurde gelöscht.',
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
