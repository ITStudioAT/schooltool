import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'

export const useStudentTimetablesUserStore = defineStore('StudentTimetablesUserStore', {
    state: () => {
        return {
            config: null,
            schools: [],
            selected_school_id: null,
            school: null,
            data: {},
            overview: null,
            user: null,
        }
    },

    actions: {
        async loadConfig() {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++

            try {
                const response = await axios.get('/api/homepage/students-timetables/config')
                this.config = response.data
                this.schools = this.config?.schools || []

                return true
            } catch (error) {
                this.notifyError(notification, error)

                return false
            } finally {
                homepageStore.is_loading--
            }
        },

        async loginStepEmail(data) {
            return this.postLoginStep('/api/homepage/students-timetables/login_step_email', data)
        },

        async loginStepCode(data) {
            return this.postLoginStep('/api/homepage/students-timetables/login_step_code', data)
        },

        async loginStepPassword(data) {
            return this.postLoginStep('/api/homepage/students-timetables/login_step_password', data)
        },

        async postLoginStep(url, data) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++

            try {
                const response = await axios.post(url, data)
                this.data = response.data
                this.user = response.data?.user ?? this.user

                return true
            } catch (error) {
                this.notifyError(notification, error)

                return false
            } finally {
                homepageStore.is_loading--
            }
        },

        async getCurrentUser() {
            try {
                const response = await axios.get('/api/homepage/students-timetables/user')
                this.user = response.data?.user ?? null

                return this.user !== null
            } catch (error) {
                this.user = null

                return false
            }
        },

        async loadOverview(selection = null) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++

            try {
                const response = await axios.get('/api/homepage/students-timetables/overview', {
                    params: selection ? { selection } : {},
                })
                this.overview = response.data?.data ?? null

                return true
            } catch (error) {
                this.notifyError(notification, error)

                return false
            } finally {
                homepageStore.is_loading--
            }
        },

        async updateProfileSelection(selection) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++

            try {
                const response = await axios.put('/api/homepage/students-timetables/profile-selection', {
                    selection,
                })
                this.overview = response.data?.data ?? null
                notification.notify({
                    message: response.data?.message || 'Auswahl wurde gespeichert.',
                    type: 'success',
                    timeout: 2500,
                })

                return true
            } catch (error) {
                this.notifyError(notification, error)

                return false
            } finally {
                homepageStore.is_loading--
            }
        },

        async restoreProfileSelection() {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++

            try {
                const response = await axios.delete('/api/homepage/students-timetables/profile-selection')
                this.overview = response.data?.data ?? null
                notification.notify({
                    message: response.data?.message || 'Auswahl wurde wiederhergestellt.',
                    type: 'success',
                    timeout: 2500,
                })

                return true
            } catch (error) {
                this.notifyError(notification, error)

                return false
            } finally {
                homepageStore.is_loading--
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
                await axios.post('/api/homepage/students-timetables/change_password', {
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
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Ändern des Passworts.',
                    type: 'error',
                    timeout: 3000,
                })

                return false
            } finally {
                homepageStore.is_loading--
            }
        },

        notifyError(notification, error) {
            notification.notify({
                status: error.response?.status,
                message: error.response?.data?.message || 'Fehler passiert.',
                type: 'error',
                timeout: 3000,
            })
        },
    },
})
