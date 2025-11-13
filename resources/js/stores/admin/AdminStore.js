import { defineStore } from 'pinia'
import { createResourceStore } from './ResourceStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

const resourceStore = createResourceStore('users') // <-- first create it

export const useAdminStore = defineStore('AdminAdminStore', {
    state: () => ({
        ...resourceStore.state(), // merge the base state
        config: null,
        is_loading: 0,
        api_response: null,
        show_navigation_drawer: true,
        user_roles: [],
        seletced_school: null,
        selected_schoolyear: null,
        selected_register: null,
        selected_school_id: null,
        selected_active_register: null,
        action: '',
        data: {},
        roles: [],
        health: null,
    }),

    actions: {
        ...resourceStore.actions(),

        async loadConfig() {
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null
            try {
                await axios.get('/sanctum/csrf-cookie')
                this.api_response = await axios.get('/api/admin/config', {})
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

        async loginStepEmail(data) {
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null
            try {
                await axios.get('/sanctum/csrf-cookie')
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
                await axios.get('/sanctum/csrf-cookie')
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
                await axios.get('/sanctum/csrf-cookie')
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

        async executeLogout() {
            await axios.get('/sanctum/csrf-cookie')
            const notification = useNotificationStore()
            this.is_loading++
            this.api_response = null
            try {
                this.api_response = await axios.post('/api/admin/execute_logout', {})
                await axios.get('/sanctum/csrf-cookie')

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
