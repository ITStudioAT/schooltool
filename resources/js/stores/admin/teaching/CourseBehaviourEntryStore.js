import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useCourseBehaviourEntryStore = defineStore('AdminCourseBehaviourEntryStore', {
    state: () => {
        return {
            entries: [],
            courseEntries: [],
        }
    },

    actions: {
        async index(courseId, userId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get(`/api/admin/teaching/course_behaviour_entries`, {
                    params: { course_id: courseId, user_id: userId },
                })
                this.entries = response.data.data
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
                adminStore.is_loading--
            }
        },

        async store(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/teaching/course_behaviour_entries`, data)
                const entry = response?.data?.data
                if (entry) {
                    this.entries = [entry, ...(this.entries || []).filter((e) => e.id !== entry.id)]
                    this.courseEntries = [entry, ...(this.courseEntries || []).filter((e) => e.id !== entry.id)]
                }
                return response.data
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
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
            if (!data?.id) {
                notification.notify({
                    status: 422,
                    message: 'Eintrag-ID fehlt. Bitte Seite neu laden.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            }
            adminStore.is_loading++
            try {
                const response = await axios.put(`/api/admin/teaching/course_behaviour_entries/${data.id}`, data)
                const entry = response?.data?.data
                if (entry) {
                    this.entries = (this.entries || []).map((e) => (e.id === entry.id ? entry : e))
                    this.courseEntries = (this.courseEntries || []).map((e) => (e.id === entry.id ? entry : e))
                }
                return response.data
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async destroy(entryId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                await axios.delete(`/api/admin/teaching/course_behaviour_entries/${entryId}`)
                this.entries = (this.entries || []).filter((e) => e.id !== entryId)
                this.courseEntries = (this.courseEntries || []).filter((e) => e.id !== entryId)
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
                adminStore.is_loading--
            }
        },

        async indexByCourse(courseId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get(`/api/admin/teaching/course_behaviour_entries`, {
                    params: { course_id: courseId },
                })
                this.courseEntries = response.data.data
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
                adminStore.is_loading--
            }
        },

        clear() {
            this.entries = []
        },
    },
})
