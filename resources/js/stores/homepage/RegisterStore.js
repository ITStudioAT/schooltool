import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'
export const useRegisterStore = defineStore('HomepageRegisterStore', {
    state: () => {
        return {
            config: null,
            registers: [],
            active_register: null,
            selected_register_id: null,
            data: {},
            register_dates: [],
            dates: [],
            bookings: [],
        }
    },

    actions: {
        async loadConfig(school = null) {
            const homepageStore = useHomepageStore()
            const notification = useNotificationStore()
            homepageStore.is_loading++
            try {
                this.response = await axios.get('/api/homepage/register/config', {
                    params: { school },
                })
                this.config = this.response.data
                this.registers = this.response.data.registers

                this.active_register = null
                if (this.registers.length == 1) this.active_register = this.registers[0]
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

        async loadRegisterAndUser() {
            const homepageStore = useHomepageStore()
            const notification = useNotificationStore()
            homepageStore.is_loading++
            try {
                this.response = await axios.get('/api/homepage/register/load_register_and_user', {})
                this.data = this.response.data
                this.config = this.response.data.config
                this.active_register = this.response.data.register
                this.register_dates = this.response.data.register_dates
                this.dates = this.response.data.dates
                this.bookings = this.response.data.bookings

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
            const homepageStore = useHomepageStore()
            const notification = useNotificationStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post('/api/homepage/register/check_email', { data })
                this.data = response.data
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

        async book(data) {
            const homepageStore = useHomepageStore()
            const notification = useNotificationStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post('/api/homepage/register/book', { data })
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

        async deleteBooking(booking_id) {
            const homepageStore = useHomepageStore()
            const notification = useNotificationStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post('/api/homepage/register/delete_booking', { booking_id })
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
            const homepageStore = useHomepageStore()
            const notification = useNotificationStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post('/api/homepage/register/confirm_email', { data })
                this.data = response.data
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

        async saveUserData(data) {
            const homepageStore = useHomepageStore()
            const notification = useNotificationStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post('/api/homepage/register/save_user_data', { data })
                this.data = response.data
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

        async loginToken(data) {
            const homepageStore = useHomepageStore()
            const notification = useNotificationStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post('/api/homepage/register/login_token', { data })
                this.data = response.data
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
