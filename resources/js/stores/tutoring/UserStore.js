import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'

export const useUserStore = defineStore('TutoringUserStore', {
    state: () => {
        return {
            error: null,
            data: {},
        }
    },

    actions: {
        async update(data) {
            this.error = null
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                const response = await axios.put(`/api/homepage/tutoring/users/${data.id}`, { data })
                this.data = response.data
                if (this.data.status == 'OK') {
                    notification.notify({
                        message: 'Das Profil wurde erfolreich gespeichert.',
                        type: 'success',
                        timeout: 3000,
                    })
                }

                return true
            } catch (error) {
                this.error = error

                return false
            } finally {
                homepageStore.is_loading--
            }
        },

        async updatePassword(data) {
            this.error = null
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post(`/api/homepage/tutoring/update_password`, { data })
                this.data = response.data
                if (this.data.status == 'OK') {
                    notification.notify({
                        message: 'Das Kennword wurde erfolreich geändert.',
                        type: 'success',
                        timeout: 3000,
                    })
                }

                return true
            } catch (error) {
                this.error = error

                return false
            } finally {
                homepageStore.is_loading--
            }
        },

        async logout() {
            this.error = null
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                const response = await axios.post(`/api/homepage/tutoring/logout`, {})
                return true
            } catch (error) {
                this.error = error

                return false
            } finally {
                homepageStore.is_loading--
            }
        },
    },
})
