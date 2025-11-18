import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'

export const useTutoringStore = defineStore('HomepageTutoringStore', {
    state: () => {
        return {
            config: null,
            schools: [],
            selected_school_id: null,
            school: null,
        }
    },

    actions: {
        async config() {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                this.response = await axios.get('/api/homepage/tutoring/config', {})
                this.config = this.response.data
                this.schools = this.config?.schools
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
