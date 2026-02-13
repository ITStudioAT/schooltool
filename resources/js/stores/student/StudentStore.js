import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'

export const useStudentStore = defineStore('StudentStudentStore', {
    state: () => {
        return {
            config: null,
            schools: [],
            selected_school_id: null,
            school: null,
            data: {},
            user: null,
        }
    },

    actions: {
        async loadConfig() {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                this.response = await axios.get('/api/homepage/student/config', {})
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

        async loginStepEmail(data) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                this.response = await axios.post('/api/homepage/student/login_step_email', data)
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

        async loginStepCode(data) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                this.response = await axios.post('/api/homepage/student/login_step_code', data)
                this.data = this.response.data
                this.user = this.response.data?.user ?? null
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

        async loginStepPassword(data) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                this.response = await axios.post('/api/homepage/student/login_step_password', data)
                this.data = this.response.data
                this.user = this.response.data?.user ?? null
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

        async getCurrentUser() {
            try {
                const response = await axios.get('/api/homepage/student/user')
                this.user = response.data?.user ?? null
                return this.user !== null
            } catch (error) {
                this.user = null
                return false
            }
        },

        async logout() {
            try {
                await axios.post('/api/homepage/logout')
                this.user = null
                this.data = {}
                this.school = null
                this.selected_school_id = null
                return true
            } catch (error) {
                return false
            }
        },

        async changePassword(newPassword, confirmPassword) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                await axios.post('/api/homepage/student/change_password', {
                    new_password: newPassword,
                    confirm_password: confirmPassword,
                })
                notification.notify({
                    message: 'Passwort erfolgreich geändert.',
                    type: 'success',
                    timeout: 3000,
                })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler beim Ändern des Passworts.',
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
