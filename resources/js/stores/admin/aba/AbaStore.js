import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useAbaStore = defineStore('AdminAbaStore', {
    state: () => ({
        abas: [],
        aba: null,
        meta: null,
        error: null,
    }),

    actions: {
        async index() {
            this.error = null
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get('/api/admin/abas')
                this.abas = response.data.data
                this.meta = response.data.meta
                return true
            } catch (error) {
                this.error = error
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Laden der ABAs.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async show(id) {
            this.error = null
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get(`/api/admin/abas/${id}`)
                this.aba = response.data
                return true
            } catch (error) {
                this.error = error
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Laden der ABA.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async store(data) {
            this.error = null
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post('/api/admin/abas', { data })
                this.aba = response.data
                notification.notify({
                    message: 'ABA erfolgreich erstellt.',
                    type: 'success',
                    timeout: 3000,
                })
                return true
            } catch (error) {
                this.error = error
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Erstellen der ABA.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async update(data) {
            this.error = null
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.put(`/api/admin/abas/${data.id}`, { data })
                this.aba = response.data
                notification.notify({
                    message: 'ABA erfolgreich aktualisiert.',
                    type: 'success',
                    timeout: 3000,
                })
                return true
            } catch (error) {
                this.error = error
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Aktualisieren der ABA.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async destroy(id) {
            this.error = null
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                await axios.delete(`/api/admin/abas/${id}`)
                this.abas = this.abas.filter((a) => a.id !== id)
                notification.notify({
                    message: 'ABA erfolgreich gelöscht.',
                    type: 'success',
                    timeout: 3000,
                })
                return true
            } catch (error) {
                this.error = error
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Löschen der ABA.',
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
