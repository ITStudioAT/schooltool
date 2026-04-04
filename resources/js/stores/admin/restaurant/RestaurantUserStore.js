import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useRestaurantUserStore = defineStore('AdminRestaurantUserStore', {
    state: () => ({
        users: [],
        meta: {},
        search_string: '',
        only_pending_confirmation: false,
        only_without_sepa: false,
        error: null,
    }),

    actions: {
        async index(page = 1) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++
            this.error = null

            try {
                const response = await axios.get('/api/admin/restaurant/users', {
                    params: {
                        search_string: this.search_string || '',
                        only_pending_confirmation: this.only_pending_confirmation ? 1 : 0,
                        only_without_sepa: this.only_without_sepa ? 1 : 0,
                        page,
                    },
                })

                this.users = response?.data?.data || []
                this.meta = response?.data?.meta || {}

                return true
            } catch (error) {
                this.error = error
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Laden der Restaurant-Benutzer.',
                    type: 'error',
                    timeout: 3000,
                })

                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async destroyCandidate(userId) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++
            this.error = null

            try {
                await axios.delete(`/api/admin/restaurant/users/${userId}`)
                this.users = this.users.filter((user) => {
                    return Number(user.id) !== Number(userId)
                })

                notification.notify({
                    message: 'Kandidat wurde geloescht.',
                    type: 'success',
                    timeout: 2200,
                })

                return true
            } catch (error) {
                this.error = error
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Loeschen des Kandidaten.',
                    type: 'error',
                    timeout: 3000,
                })

                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async updateSepa(userId, hasSepa) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++
            this.error = null

            try {
                const response = await axios.put(`/api/admin/restaurant/users/${userId}/sepa`, {
                    data: {
                        has_sepa: hasSepa,
                    },
                })

                const updatedUser = response?.data?.data || null

                if (updatedUser) {
                    this.users = this.users.map((user) => {
                        return Number(user.id) === Number(userId) ? updatedUser : user
                    })
                }

                notification.notify({
                    message: hasSepa ? 'SEPA bestätigt.' : 'SEPA abgelehnt.',
                    type: 'success',
                    timeout: 2200,
                })

                return updatedUser
            } catch (error) {
                this.error = error
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Speichern des SEPA-Status.',
                    type: 'error',
                    timeout: 3000,
                })

                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async confirmUser(userId) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++
            this.error = null

            try {
                const response = await axios.put(`/api/admin/restaurant/users/${userId}/confirm`)
                const updatedUser = response?.data?.data || null

                if (updatedUser) {
                    this.users = this.users.map((user) => {
                        return Number(user.id) === Number(userId) ? updatedUser : user
                    })
                }

                notification.notify({
                    message: 'Restaurant-Benutzer bestätigt.',
                    type: 'success',
                    timeout: 2200,
                })

                return updatedUser
            } catch (error) {
                this.error = error
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Bestätigen des Restaurant-Benutzers.',
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
