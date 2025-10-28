import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useSchoolStore = defineStore('AdminSchoolStore', {
    state: () => ({
        schools: [],
        selected_schools: [],
        search_string: '',
        meta: [],
        data: {},
        saved_school: null,
    }),

    actions: {
        async index(page = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            const search_string = this.search_string
            try {
                const response = await axios.get(`/api/admin/schools`, { params: { search_string, page } })
                this.schools = response.data.data
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
                const response = await axios.put(`/api/admin/schools/${data.id}`, data)
                this.saved_school = response.data

                const index = this.schools.findIndex((s) => s.id === this.saved_school.id)

                if (index !== -1) {
                    // Replace the old element with the new one
                    this.schools.splice(index, 1, this.saved_school)
                }

                this.schools.sort((a, b) => a.long_name.localeCompare(b.long_name))
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

        async store(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/schools`, data)
                this.saved_school = response.data
                this.schools.push(this.saved_school)
                this.schools.sort((a, b) => a.long_name.localeCompare(b.long_name))
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
    },
})
