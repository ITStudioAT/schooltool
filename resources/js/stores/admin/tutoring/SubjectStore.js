import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useSubjectStore = defineStore('AdminTutoringSubjectStore', {
    state: () => ({
        subjects: [],
        subject: null,
        error: null,
        data: {},
    }),

    actions: {
        async index() {
            this.error = null
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get('/api/admin/tutoring/subjects')

                this.subjects = response.data
                return true
            } catch (error) {
                this.error = error
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Laden der Fächer.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async createSubjects(data) {
            this.error = null
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post('/api/admin/tutoring/create_subjects', { data })

                notification.notify({
                    message: 'Fach/Fächer erfolgreich erstellt.',
                    type: 'success',
                    timeout: 3000,
                })

                return true
            } catch (error) {
                this.error = error
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Erstellen der Fächer.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async updateSubject(data) {
            this.error = null
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                /*
                const payload = {
                    id: data.id,
                    short_name: data.short_name,
                    long_name: data.long_name,
                    email_mentors: data.email_mentors ? [...data.email_mentors] : [], // ✅ Array kopieren
                }

                console.log(payload)
                */

                const response = await axios.put(`/api/admin/tutoring/subjects/${data.id}`, { data })
                this.subject = response.data

                notification.notify({
                    message: 'Fach erfolgreich aktualisiert.',
                    type: 'success',
                    timeout: 3000,
                })

                return true
            } catch (error) {
                this.error = error
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Aktualisieren des Fachs.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async deleteSubject(data) {
            this.error = null
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.delete('/api/admin/tutoring/subjects/' + data.id, {})

                notification.notify({
                    message: 'Fach/Fächer erfolgreich gelöscht.',
                    type: 'success',
                    timeout: 3000,
                })

                return true
            } catch (error) {
                this.error = error
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Löschen der Fächer.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },
    },
})
