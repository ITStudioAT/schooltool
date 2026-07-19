import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useCurriculumStore = defineStore('AdminCurriculumStore', {
    state: () => ({
        curricula: [],
        imported_curricula: [],
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 0,
        },
        is_loading: false,
    }),

    actions: {
        async index({ page = 1, perPage = null } = {}) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            this.is_loading = true
            try {
                const params = {
                    page,
                    per_page: perPage ?? this.meta.per_page,
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

        async loadImportedCurricula() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get('/api/admin/teaching/imported-curricula')
                this.imported_curricula = response.data?.data || []
                return this.imported_curricula
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return []
            } finally {
                adminStore.is_loading--
            }
        },

        async importCurriculum(file) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const formData = new FormData()
                formData.append('file', file)
                const response = await axios.post('/api/admin/teaching/imported-curricula/import', formData)
                await this.loadImportedCurricula()
                notification.notify({
                    message: 'Curriculum wurde importiert.',
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
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async adoptImportedCurriculum(id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/teaching/imported-curricula/${id}/adopt`)
                this.imported_curricula = this.imported_curricula.map((curriculum) => (
                    Number(curriculum?.id) === Number(id)
                        ? {
                            ...curriculum,
                            adopted_curriculum_id: response.data?.data?.id || curriculum.adopted_curriculum_id || null,
                        }
                        : curriculum
                ))
                notification.notify({
                    message: 'Importiertes Curriculum wurde als eigenes Curriculum übernommen.',
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
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async destroyImportedCurriculum(id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                await axios.delete(`/api/admin/teaching/imported-curricula/${id}`)
                this.imported_curricula = this.imported_curricula.filter((curriculum) => Number(curriculum?.id) !== Number(id))
                notification.notify({
                    message: 'Importiertes Curriculum wurde gelöscht.',
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
