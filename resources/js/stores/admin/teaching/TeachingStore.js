import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useTeachingStore = defineStore('AdminTeachingStore', {
    state: () => {
        return {
            import116: [],
            selected_import116: [],
            meta: [],
            settings: null,
        }
    },

    getters: {
        schemas: (state) => state.settings?.teaching_schemas || [],
        schemaById: (state) => (id) => {
            return (state.settings?.teaching_schemas || []).find((s) => s.id === id) || null
        },
        worksForSchema: (state) => (schemaId) => {
            const schema = (state.settings?.teaching_schemas || []).find((s) => s.id === schemaId)
            return schema?.works || []
        },
        gradingForSchema: (state) => (schemaId) => {
            const schema = (state.settings?.teaching_schemas || []).find((s) => s.id === schemaId)
            return schema?.grading || {}
        },
        hasTwoSemesters: (state) => {
            const schemas = state.settings?.teaching_schemas || []
            return schemas.some((s) => s.grading?.semester_count === 2)
        },
    },

    actions: {
        async search116(page = null) {
            const notification = useNotificationStore()
            const homepageStore = useAdminStore()
            homepageStore.is_loading++
            const search_string = this.search_string
            try {
                const response = await axios.get(`/api/admin/teaching/search116`, { params: { search_string, page } })
                this.import116 = response.data.data
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
                homepageStore.is_loading--
            }
        },

        async loadSettings() {
            const notification = useNotificationStore()
            const homepageStore = useAdminStore()
            homepageStore.is_loading++
            try {
                const response = await axios.get(`/api/admin/teaching/load_settings`, {})
                this.settings = response.data.settings
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
                homepageStore.is_loading--
            }
        },

        async saveActiveSemester(semester) {
            const adminStore = useAdminStore()
            try {
                await axios.post(`/api/admin/teaching/save_active_semester`, { teaching_active_semester: semester })
                if (adminStore.config?.user) adminStore.config.user.teaching_active_semester = semester
            } catch (error) {
                useNotificationStore().notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
            }
        },

        async saveSemester2Date(date) {
            const adminStore = useAdminStore()
            try {
                const response = await axios.post(`/api/admin/teaching/save_semester_2_date`, { teaching_count_for_semester_2_date: date })
                if (adminStore.config?.user) adminStore.config.user.teaching_count_for_semester_2_date = response.data.teaching_count_for_semester_2_date
                useNotificationStore().notify({
                    message: 'Datum gespeichert.',
                    type: 'success',
                    timeout: 2000,
                })
            } catch (error) {
                useNotificationStore().notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
            }
        },

        async saveSettings(settings) {
            const notification = useNotificationStore()
            const homepageStore = useAdminStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/teaching/save_settings`, settings)
                this.settings = response.data.settings
                notification.notify({
                    message: 'Einstellungen gespeichert.',
                    type: 'success',
                    timeout: 2000,
                })
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
                homepageStore.is_loading--
            }
        },
    },
})
