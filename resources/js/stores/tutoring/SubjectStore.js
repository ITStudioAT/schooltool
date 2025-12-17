import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'

export const useSubjectStore = defineStore('TutoringSubjectStore', {
    state: () => {
        return {
            subjects: null,
            selected_subject: [],
            saved_subject: null,
            error: null,
            data: {},
        }
    },

    actions: {
        async index() {
            const notification = useNotificationStore()
            const homepageStore = useHomepageStore()
            homepageStore.is_loading++
            try {
                const response = await axios.get(`/api/homepage/tutoring/subjects`, {})
                this.subjects = response.data
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
