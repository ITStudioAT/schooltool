import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useOfferStore = defineStore('AdminTutoringOfferStore', {
    state: () => {
        return {
            offers: null,
            selected_offers: [],
            search_string: '',
            saved_offer: null,
            meta: null,
            error: null,
            data: {},
            select_accepted: 'all',
            select_online: 'all',
        }
    },

    actions: {
        async index(page = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            const search_string = this.search_string
            const select_accepted = this.select_accepted
            const select_online = this.select_online
            try {
                const response = await axios.get(`/api/admin/tutoring/offers`, { params: { search_string, page, select_accepted, select_online } })
                this.offers = response.data.data
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
                adminStore.is_loading--
            }
        },

        async update(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.put(`/api/admin/tutoring/offers/${data.id}`, data)
                this.saved_offer = response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                this.error = error
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async store(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/tutoring/offers`, data)
                this.saved_offer = response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                this.error = error
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async delete(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                this.answer = await axios.delete(`/api/admin/tutoring/offers/${data.id}`, {})

                notification.notify({
                    message: 'Das Angebot wurden gelöscht.',
                    type: 'success',
                    timeout: 3000,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async toggleAccepted(id) {
            console.log('toggleAccepted')
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/tutoring/toggle_accepted_offer`, { id })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                this.error = error
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async toggleActive(id) {
            console.log('toggleActive')
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/tutoring/toggle_active_offer`, { id })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                this.error = error
                return false
            } finally {
                adminStore.is_loading--
            }
        },
    },
})
