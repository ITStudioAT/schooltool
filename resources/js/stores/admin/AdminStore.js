import { defineStore } from 'pinia'
import { update as updateAdminShellColorPreference } from '@/actions/App/Http/Controllers/Admin/AdminShellColorPreferenceController'
import { createResourceStore } from './ResourceStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

const resourceStore = createResourceStore('users') // <-- first create it

async function ensureCsrfCookie() {
    if (typeof window.ensureCsrfCookie === 'function') {
        await window.ensureCsrfCookie()
        return
    }

    await axios.get('/sanctum/csrf-cookie')
}

export const useAdminStore = defineStore('AdminAdminStore', {
    state: () => ({
        ...resourceStore.state(), // merge the base state
        config: null,
        is_loading: 0,
        api_response: null,
        show_navigation_drawer: true,
        is_navigation_locked: false,
        is_struktur_modus: false,
        user_roles: [],
        selected_school: null,
        selected_schoolyear: null,
        selected_register: null,
        selected_school_id: null,
        selected_active_register: null,
        action: '',
        action_2: '',
        data: {},
        roles: [],
        health: null,
        main_menu: '',
        main_action: '',
        schools: null,
        impersonatable_schools: [],
        impersonatable_users: [],
        impersonatable_users_meta: [],
        config_request_promise: null,
    }),

    actions: {
        ...resourceStore.actions(),

        async loadConfig(options = {}) {
            if (this.config_request_promise) {
                return this.config_request_promise
            }

            const notification = useNotificationStore()
            const requestPromise = (async () => {
                this.is_loading++
                this.api_response = null
                try {
                    const params = {}
                    if (options.includeSchoolInfos) params.include_school_infos = 1
                    if (options.includeEnvironmentVersions) params.include_environment_versions = 1

                    this.api_response = await axios.get('/api/admin/config', Object.keys(params).length ? { params } : {})
                    this.config = this.api_response.data
                    this.selected_school = this.config?.selected_school
                    this.selected_schoolyear = this.config?.selected_schoolyear
                    this.selected_register = this.config?.selected_register
                    this.health = this.config?.health

                    return this.api_response.data
                } catch (error) {
                    notification.notify({
                        status: error.response.status,
                        message: error.response.data.message || 'Fehler passiert.',
                        type: 'error',
                        timeout: this.config?.timeout,
                    })
                    return false
                } finally {
                    this.is_loading--
                }
            })()

            this.config_request_promise = requestPromise

            try {
                return await requestPromise
            } finally {
                this.config_request_promise = null
            }
        },

        async saveAdminShellColorPreference(useSchoolColorForAdminUi) {
            const notification = useNotificationStore()
            this.is_loading++

            try {
                const response = await axios.put(updateAdminShellColorPreference.url(), {
                    use_school_color_for_admin_ui: useSchoolColorForAdminUi,
                })
                await this.loadConfig()

                const savedPreference = typeof response.data?.use_school_color_for_admin_ui === 'boolean'
                    ? response.data.use_school_color_for_admin_ui
                    : useSchoolColorForAdminUi

                if (this.config?.user) {
                    this.config.user.use_school_color_for_admin_ui = savedPreference
                }

                notification.notify({
                    message: 'Darstellung wurde gespeichert.',
                    type: 'success',
                    timeout: this.config?.timeout,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })

                return false
            } finally {
                this.is_loading--
            }
        },

        async loadImpersonatableSchools() {
            const notification = useNotificationStore()
            this.is_loading++
            try {
                const response = await axios.get('/api/admin/impersonation/schools')
                this.impersonatable_schools = response.data?.data || []
                return this.impersonatable_schools
            } catch (error) {
                this.impersonatable_schools = []
                notification.notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async loadImpersonatableUsers(search_string = '', school_id = null, page = null) {
            const notification = useNotificationStore()
            this.is_loading++
            try {
                const params = { search_string }
                if (school_id) params.school_id = school_id
                if (page) params.page = page
                const response = await axios.get('/api/admin/impersonation/users', { params })
                this.impersonatable_users = response.data?.data || []
                this.impersonatable_users_meta = response.data?.meta || []
                return this.impersonatable_users
            } catch (error) {
                this.impersonatable_users = []
                this.impersonatable_users_meta = []
                notification.notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async startImpersonation(user_id) {
            const notification = useNotificationStore()
            this.is_loading++
            try {
                await axios.post('/api/admin/impersonation/start', { user_id })
                await this.loadConfig()
                notification.notify({
                    message: 'Benutzer-Übernahme gestartet.',
                    type: 'success',
                    timeout: 3000,
                })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async stopImpersonation() {
            const notification = useNotificationStore()
            this.is_loading++
            try {
                await axios.post('/api/admin/impersonation/stop')
                await this.loadConfig()
                notification.notify({
                    message: 'Benutzer-Übernahme beendet.',
                    type: 'success',
                    timeout: 3000,
                })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async registerStep1(data) {
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null
            try {
                this.api_response = await axios.post('/api/admin/register_step_1', { data })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async registerStep2(data) {
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null
            try {
                this.api_response = await axios.post('/api/admin/register_step_2', { data })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async registerStep3(data) {
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null
            try {
                this.api_response = await axios.post('/api/admin/register_step_3', { data })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async passwordUnknownStepSchool(data) {
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null
            try {
                this.api_response = await axios.post('/api/admin/password_unknown_step_school', { data })
                this.data = this.api_response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async passwordUnknownStepToken(data) {
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null
            try {
                this.api_response = await axios.post('/api/admin/password_unknown_step_token', { data })
                this.data = this.api_response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async passwordUnknownStepToken2(data) {
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null
            try {
                this.api_response = await axios.post('/api/admin/password_unknown_step_token_2', { data })
                this.data = this.api_response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async passwordUnknownStepPassword(data) {
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null
            try {
                this.api_response = await axios.post('/api/admin/password_unknown_step_password', { data })
                this.data = this.api_response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async newTeacherStepEmail(data) {
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null

            try {
                await ensureCsrfCookie()
                this.api_response = await axios.post('/api/admin/new_teacher_step_email', data)
                this.data = this.api_response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async newTeacherStepSchool(data) {
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null

            try {
                await ensureCsrfCookie()
                this.api_response = await axios.post('/api/admin/new_teacher_step_school', data)
                this.data = this.api_response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async newTeacherStepCode(data) {
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null

            try {
                await ensureCsrfCookie()
                this.api_response = await axios.post('/api/admin/new_teacher_step_code', data)
                this.data = this.api_response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async passwordUnknownStepEmail(data) {
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null
            try {
                await ensureCsrfCookie()
                this.api_response = await axios.post('/api/admin/password_unknown_step_email', { data })
                this.data = this.api_response.data
                this.schools = this.data?.schools
                delete this.data.schools
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async loginStepEmail(data) {
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null
            try {
                await ensureCsrfCookie()
                this.api_response = await axios.post('/api/admin/login_step_email', { data })
                this.data = this.api_response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async loginStep2(data) {
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null

            try {
                await ensureCsrfCookie()
                this.api_response = await axios.post('/api/admin/login_step_2', { data })
                this.data = this.api_response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async loginStep3(data) {
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null
            try {
                await ensureCsrfCookie()
                this.api_response = await axios.post('/api/admin/login_step_3', { data })
                this.data = this.api_response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async loginTwoFactorChallenge(payload) {
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null

            try {
                await ensureCsrfCookie()
                const response = await axios.post('/api/admin/two-factor-challenge', payload)
                return response.data
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.status === 429
                        ? 'Zu viele Versuche. Bitte warten Sie kurz.'
                        : error.response?.data?.message || 'Der Sicherheitscode ist ungültig.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async executeLogout() {
            await ensureCsrfCookie()
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null
            try {
                this.api_response = await axios.post('/api/admin/execute_logout', {})
                await ensureCsrfCookie()

                this.config = this.api_response.data
                this.selected_school = this.config?.selected_school
                this.selected_schoolyear = this.config?.selected_schoolyear
                this.selected_register = this.config?.selected_register

                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async loadRoles() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/load_roles`, {})
                this.roles = response.data
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
