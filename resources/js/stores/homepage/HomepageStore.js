import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
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
            schools: [],
            selected_school: null,
        }
    },

    actions: {
        async loadConfig(school = null, app = null) {
            const notification = useNotificationStore()
            this.is_loading++

            try {
                this.response = await axios.get('/api/homepage/config', {
                    params: { school, app },
                })
                this.config = this.response.data
                this.school = this.config?.school
                this.licence = this.config?.licence
                this.selected_licence_id ??= this.licence?.id ?? null
                this.selected_school_id ??= this.school?.id ?? null
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async loadSchoolsForTool(tool) {
            const notification = useNotificationStore()
            this.is_loading++

            try {
                this.response = await axios.get('/api/homepage/load_schools_for_tool', {
                    params: { tool },
                })
                this.schools = this.response.data.schools
                this.selected_school = null
                if (this.schools.length == 1) this.selected_school = this.schools[0]
                this.licence = this.response.data.licence
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                this.is_loading--
            }
        },

        async logout() {
            const notification = useNotificationStore()
            this.is_loading++
            try {
                this.response = await axios.post('/api/homepage/logout', {})
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                this.is_loading--
            }
        },
    },
})
