import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'

export const useTutoringStore = defineStore('TutoringTutoringStore', {
    state: () => {
        return {
            config: null,
            schools: [],
            selected_school_id: null,
            school: null,
            data: {},
            response: null,
            auth: null,
            action: '',
            error: null,
        }
    },

    actions: {
        async loadConfig() {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                this.response = await axios.get('/api/homepage/tutoring/config', {})
                this.config = this.response.data
                this.schools = this.config?.schools
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

        async updateProfile(data) {
            this.error = null
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                const response = await axios.put(`/api/homepage/tututoring/users/${data.id}`, { data })
                notification.notify({
                    message: 'Das Profil wurde erfolreich gespeichert.',
                    type: 'success',
                    timeout: 3000,
                })

                return true
            } catch (error) {
                this.error = error

                return false
            } finally {
                homepageStore.is_loading--
            }
        },

        async loadAuth() {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                this.response = await axios.get('/api/homepage/tutoring/load_auth', {})
                this.auth = this.response.data
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

        async checkEmail(data) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                this.response = await axios.post('/api/homepage/tutoring/check_email', { data })
                this.data = this.response.data
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

        async confirmEmail(data) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                this.response = await axios.post('/api/homepage/tutoring/confirm_email', { data })
                this.data = this.response.data
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

        async createUser(data) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                this.response = await axios.post('/api/homepage/tutoring/create_user', { data })
                this.data = this.response.data
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

        async unknownPassword(data) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                this.response = await axios.post('/api/homepage/tutoring/unknown_password', { data })
                this.data = this.response.data
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

        async loginWithToken(data) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                this.response = await axios.post('/api/homepage/tutoring/login_with_token', { data })
                this.data = this.response.data
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
