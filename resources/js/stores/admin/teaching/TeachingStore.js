import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import {
    normalizeTeachingCategoryEvaluationValueItems,
    teachingCategoryEvaluationColorForValue,
    teachingCategoryEvaluationValueLabels,
} from '@/helpers/teachingCategoryEvaluation'

export const useTeachingStore = defineStore('AdminTeachingStore', {
    state: () => {
        return {
            import116: [],
            selected_import116: [],
            meta: [],
            settings: null,
            settings_request_promise: null,
        }
    },

    getters: {
        schemas: (state) => state.settings?.teaching_schemas || [],
        schemaById: (state) => (id) => {
            return (state.settings?.teaching_schemas || []).find((s) => String(s.id) === String(id)) || null
        },
        worksForSchema: (state) => (schemaId) => {
            const schema = (state.settings?.teaching_schemas || []).find((s) => String(s.id) === String(schemaId))
            return schema?.works || []
        },
        gradingForSchema: (state) => (schemaId) => {
            const schema = (state.settings?.teaching_schemas || []).find((s) => String(s.id) === String(schemaId))
            return schema?.grading || {}
        },
        categoryEvaluationValueItemsForSchema: (state) => (schemaId) => {
            const schema = (state.settings?.teaching_schemas || []).find((s) => String(s.id) === String(schemaId))
            if (!schema) {
                return []
            }

            return normalizeTeachingCategoryEvaluationValueItems(schema?.grading?.category_evaluation_values, { fallbackToDefaults: false })
        },
        categoryEvaluationValuesForSchema: (state) => (schemaId) => {
            const schema = (state.settings?.teaching_schemas || []).find((s) => String(s.id) === String(schemaId))
            if (!schema) {
                return []
            }

            return teachingCategoryEvaluationValueLabels(schema?.grading?.category_evaluation_values, { fallbackToDefaults: false })
        },
        categoryEvaluationValueColorForSchema: (state) => (schemaId, value) => {
            const schema = (state.settings?.teaching_schemas || []).find((s) => String(s.id) === String(schemaId))
            if (!schema) {
                return ''
            }

            return teachingCategoryEvaluationColorForValue(schema?.grading?.category_evaluation_values, value)
        },
        defaultCategoryEvaluationValueForSchema: (state) => (schemaId) => {
            const schema = (state.settings?.teaching_schemas || []).find((s) => String(s.id) === String(schemaId))
            return schema?.grading?.default_category_evaluation_value || null
        },
        hasTwoSemesters: (state) => {
            const schemas = state.settings?.teaching_schemas || []
            return schemas.some((s) => Number(s.grading?.semester_count) === 2)
        },
        behaviour: (state) => state.settings?.teaching_behaviour || [],
        notifications: (state) => state.settings?.teaching_notifications || [],
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
                const status = error?.response?.status
                const message = error?.response?.data?.message || 'Fehler passiert.'
                notification.notify({
                    status,
                    message,
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                homepageStore.is_loading--
            }
        },

        async loadSettings() {
            if (this.settings_request_promise) {
                return this.settings_request_promise
            }

            const notification = useNotificationStore()
            const homepageStore = useAdminStore()
            const requestPromise = (async () => {
                homepageStore.is_loading++
                try {
                    const response = await axios.get(`/api/admin/teaching/load_settings`, {})
                    this.settings = response.data.settings
                    return true
                } catch (error) {
                    const status = error?.response?.status
                    const message = error?.response?.data?.message || 'Fehler passiert.'
                    notification.notify({
                        status,
                        message,
                        type: 'error',
                        timeout: 3000,
                    })
                    return false
                } finally {
                    homepageStore.is_loading--
                }
            })()

            this.settings_request_promise = requestPromise

            try {
                return await requestPromise
            } finally {
                this.settings_request_promise = null
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
                if (adminStore.config?.selected_schoolyear) adminStore.config.selected_schoolyear.sem_2_start = response.data.schoolyear_sem_2_start
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

        async saveSettings(settings, options = {}) {
            const notification = useNotificationStore()
            const homepageStore = useAdminStore()
            const notifySuccess = options.notifySuccess !== false
            homepageStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/teaching/save_settings`, settings)
                this.settings = response.data.settings
                if (homepageStore.config?.user && response?.data?.settings?.teaching_grade_columns) {
                    homepageStore.config.user.teaching_grade_columns = response.data.settings.teaching_grade_columns
                }
                if (homepageStore.config?.user && response?.data?.settings?.teaching_student_grade_columns) {
                    homepageStore.config.user.teaching_student_grade_columns = response.data.settings.teaching_student_grade_columns
                }
                if (notifySuccess) {
                    notification.notify({
                        message: 'Einstellungen gespeichert.',
                        type: 'success',
                        timeout: 2000,
                    })
                }
                return true
            } catch (error) {
                const status = error?.response?.status
                const message = error?.response?.data?.message || 'Fehler passiert.'
                notification.notify({
                    status,
                    message,
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                homepageStore.is_loading--
            }
        },

        async importBehaviour() {
            const notification = useNotificationStore()
            const homepageStore = useAdminStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post('/api/admin/teaching/import_behaviour')
                this.settings = response.data.settings
                notification.notify({
                    message: 'Verhalten importiert.',
                    type: 'success',
                    timeout: 2000,
                })
                return true
            } catch (error) {
                const status = error?.response?.status
                const message = error?.response?.data?.message || 'Fehler passiert.'
                notification.notify({
                    status,
                    message,
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                homepageStore.is_loading--
            }
        },

        async resetBehaviour() {
            const notification = useNotificationStore()
            const homepageStore = useAdminStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post('/api/admin/teaching/reset_behaviour')
                this.settings = response.data.settings
                notification.notify({
                    message: 'Verhalten zurückgesetzt.',
                    type: 'success',
                    timeout: 2000,
                })
                return true
            } catch (error) {
                const status = error?.response?.status
                const message = error?.response?.data?.message || 'Fehler passiert.'
                notification.notify({
                    status,
                    message,
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                homepageStore.is_loading--
            }
        },

        async importNotifications() {
            const notification = useNotificationStore()
            const homepageStore = useAdminStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post('/api/admin/teaching/import_notifications')
                this.settings = response.data.settings
                notification.notify({
                    message: 'Verständigungen importiert.',
                    type: 'success',
                    timeout: 2000,
                })
                return true
            } catch (error) {
                const status = error?.response?.status
                const message = error?.response?.data?.message || 'Fehler passiert.'
                notification.notify({
                    status,
                    message,
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                homepageStore.is_loading--
            }
        },

        async resetNotifications() {
            const notification = useNotificationStore()
            const homepageStore = useAdminStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post('/api/admin/teaching/reset_notifications')
                this.settings = response.data.settings
                notification.notify({
                    message: 'Verständigungen zurückgesetzt.',
                    type: 'success',
                    timeout: 2000,
                })
                return true
            } catch (error) {
                const status = error?.response?.status
                const message = error?.response?.data?.message || 'Fehler passiert.'
                notification.notify({
                    status,
                    message,
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                homepageStore.is_loading--
            }
        },

        async importSchema(selectedSchemaId) {
            const notification = useNotificationStore()
            const homepageStore = useAdminStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post('/api/admin/teaching/import_schema', {
                    selected_schema_id: selectedSchemaId,
                })
                this.settings = response.data.settings
                notification.notify({
                    message: 'Benotungsschema importiert.',
                    type: 'success',
                    timeout: 2000,
                })
                return true
            } catch (error) {
                const status = error?.response?.status
                const message = error?.response?.data?.message || 'Fehler passiert.'
                notification.notify({
                    status,
                    message,
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                homepageStore.is_loading--
            }
        },

        async resetSchema(selectedSchemaId) {
            const notification = useNotificationStore()
            const homepageStore = useAdminStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post('/api/admin/teaching/reset_schema', {
                    selected_schema_id: selectedSchemaId,
                })
                this.settings = response.data.settings
                notification.notify({
                    message: 'Benotungsschema zurückgesetzt.',
                    type: 'success',
                    timeout: 2000,
                })
                return true
            } catch (error) {
                const status = error?.response?.status
                const message = error?.response?.data?.message || 'Fehler passiert.'
                notification.notify({
                    status,
                    message,
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
