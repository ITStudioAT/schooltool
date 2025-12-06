import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'

export const useOfferStore = defineStore('TutoringOfferStore', {
    state: () => {
        return {
            offers: null,
            selected_offer: [],
            search_string: '',
            saved_offer: null,
            meta: null,
            error: null,
            data: {},
            my_offers: null,
        }
    },

    actions: {
        async index(page = null) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            const search_string = this.search_string
            try {
                const response = await axios.get(`/api/homepage/tutoring/offers`, { params: { search_string, page } })
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
                homepageStore.is_loading--
            }
        },

        async loadMyOffers() {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                const response = await axios.get(`/api/homepage/tutoring/load_my_offers`, {})
                this.my_offers = response.data
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

        async update(data) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                const response = await axios.put(`/api/homepage/tutoring/offers/${data.id}`, data)
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
                homepageStore.is_loading--
            }
        },

        async store(data) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post(`/api/homepage/tutoring/offers`, data)
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
                homepageStore.is_loading--
            }
        },

        async delete(data) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                this.answer = await axios.delete(`/api/homepage/tutoring/offers/${data.id}`, {})

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
                homepageStore.is_loading--
            }
        },
    },
})
