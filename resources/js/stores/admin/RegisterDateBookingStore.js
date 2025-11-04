import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useRegisterDateBookingStore = defineStore('AdminRegisterDateBookingStore', {
    state: () => ({
        person: { is_notify: false },
        user: null,
        booking: null,
        bookings: [],
        selected_bookings: [],
    }),

    actions: {
        async deleteBookings(bookings, notify) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/register_date_bookings/delete_bookings`, {
                    bookings,
                    notify,
                })

                // Bookings aus bookings löschen
                const count = this.selected_bookings.length

                this.bookings = this.bookings.filter((booking) => !this.selected_bookings.includes(booking.id))

                // Select_Bookings löschen
                this.selected_bookings = []

                // Anzahl Bookings in register_dates anpassen

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

        async loadBookings(dates) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get(`/api/admin/register_date_bookings`, { params: { dates } })
                this.bookings = response.data

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

        async getUserWithEmail(person) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/register_date_bookings/get_user_with_email`, person)
                this.user = response.data
                if (!this.user || Object.keys(this.user).length === 0) this.user = null
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

        async updateOrCreateUser(person) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/register_date_bookings/update_or_create_user`, person)
                this.user = response.data
                if (!this.user || Object.keys(this.user).length === 0) this.user = null
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

        async createBooking(person) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/register_date_bookings`, person)
                this.booking = response.data

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
