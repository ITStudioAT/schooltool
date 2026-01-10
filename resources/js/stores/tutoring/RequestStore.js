import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'

export const useRequestStore = defineStore('TutoringRequestStore', {
    state: () => {
        return {
            requests: null,
            selected_request: [],
            search_string: '',
            saved_request: null,
            meta: null,
            error: null,
            data: {},
            my_requests: null,
            request_config: null,
            is_request_dialog: false,
            send_request_status: null,
            request_request: null,
            show_archived: false,
            show_to_user_archived: false,
        }
    },

    actions: {
        async index(page = null) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            const show_archived = this.show_archived
            homepageStore.is_loading++
            try {
                const response = await axios.get(`/api/homepage/tutoring/offer_requests`, { params: { page, show_archived } })
                this.requests = response.data.data
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

        async receivedRequests(page = null) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                const response = await axios.get(`/api/homepage/tutoring/received_offer_requests`, { params: { page } })
                this.requests = response.data.data
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

        async requestMailClicked(request_id) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++

            try {
                const response = await axios.post(`/api/homepage/tutoring/request_mail_clicked`, { request_id })
                const index = this.requests.findIndex((r) => r.id === response.data.id)
                if (index !== -1) {
                    this.requests[index] = response.data
                }

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

        async delete(request_id) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                await axios.delete(`/api/homepage/tutoring/offer_requests/${request_id}`)
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                homepageStore.is_loading--
            }
        },

        async toArchive(request_id) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                await axios.post(`/api/homepage/tutoring/to_archive/`, { request_id })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                homepageStore.is_loading--
            }
        },

        async toActive(request_id) {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                await axios.post(`/api/homepage/tutoring/to_active/`, { request_id })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
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
