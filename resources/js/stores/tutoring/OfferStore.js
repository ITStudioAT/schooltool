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
            offer_config: null,
            is_offer_dialog: false,
            send_request_status: null,
            offer_request: null,
            actual_offer: null,
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

        async loadOffers(school_name, page = null) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            const search_string = this.search_string
            try {
                const response = await axios.get(`/api/homepage/tutoring/load_offers`, { params: { school_name, search_string, page } })
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

        async loadOfferConfig(school_name) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                const response = await axios.get(`/api/homepage/tutoring/load_offer_config`, { params: { school_name } })
                this.offer_config = response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                this.error = error
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

        async sendRequest(offer_id, request_message) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post(`/api/homepage/tutoring/send_request`, { offer_id: offer_id, request_message: request_message })
                this.send_request_status = response.data.status
                this.offer_request = response.data.offer_request
                this.actual_offer = response.data.offer
                // Ersetzen der Offer in d er Liste der Offers
                const index = this.offers.findIndex((o) => o.id === this.actual_offer.id)
                if (index !== -1) {
                    this.offers[index] = this.actual_offer
                }
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

        async clickCount(offer_id) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post(`/api/homepage/tutoring/click_count`, { offer_id: offer_id })
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

        async setUserSearchCriteria(data) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post(`/api/homepage/tutoring/set_user_search_criteria`, data)
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

        async toggleActive(id) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post(`/api/homepage/tutoring/toggle_offer`, { id })
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
    },
})
