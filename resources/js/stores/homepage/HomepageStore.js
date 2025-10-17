import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
export const useHomepageStore = defineStore('HomepageStore', {
    state: () => {
        return {
            router: null,
            config: null,
            is_loading: 0,
            error: {
                is_error: false,
                status: null,
                message: null,
                timeout: 3000,
            },
            response: null,
            school: null,
            licence: null,
            selected_school_id: null,
            selected_licence_id: null,
        }
    },

    actions: {
        async loadConfig(school = null, app = null) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++
            try {
                this.response = await axios.get('/api/homepage/config', {
                    params: { school, app },
                })
                this.config = this.response.data
                this.school = this.config?.school
                this.licence = this.config?.licence
                this.selected_licence_id = this.licence?.id || null
                this.selected_school_id = this.school?.id || null
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },
    },
})
