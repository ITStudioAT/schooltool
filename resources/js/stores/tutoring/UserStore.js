import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'

export const useUserStore = defineStore('TutoringUserStore', {
    state: () => {
        return {
            error: null,
        }
    },

    actions: {
        async update(data) {
            this.error = null
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                const response = await axios.put(`/api/homepage/tututoring/users/${data.id}`, { data })
                notification.notify({
                    message: 'Das Profil wurde erfolreich gespeichert.',
                    type: 'success',
                    timeout: 3000,
                })

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
