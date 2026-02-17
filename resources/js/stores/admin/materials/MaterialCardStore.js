import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useMaterialCardStore = defineStore('AdminMaterialCardStore', {
    state: () => ({
        config: null,
        cards: [],
        meta: null,
        selected_card: null,
        filters: {
            search: '',
            status: '',
            subject: '',
            area: '',
            unit: '',
            type: '',
        },
    }),

    actions: {
        async loadConfig() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get('/api/admin/materials/config')
                this.config = response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Laden der Materialkarten-Konfiguration.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async index(page = 1) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const params = { page }
                for (const [key, value] of Object.entries(this.filters || {})) {
                    if (value !== null && value !== undefined && String(value).trim() !== '') {
                        params[key] = value
                    }
                }

                const response = await axios.get('/api/admin/materials/cards', { params })
                this.cards = response.data.data || []
                this.meta = response.data.meta || null
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Laden der Materialkarten.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async show(id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get('/api/admin/materials/cards/' + id)
                this.selected_card = response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Laden der Materialkarte.',
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
                const response = await axios.post('/api/admin/materials/cards', { data })
                this.selected_card = response.data
                notification.notify({
                    message: 'Materialkarte gespeichert.',
                    type: 'success',
                    timeout: 2000,
                })
                return response.data
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Speichern der Materialkarte.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async quickStore(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post('/api/admin/materials/cards/quick_store', { data })
                this.selected_card = response.data
                notification.notify({
                    message: 'Materialkarte schnell gemerkt.',
                    type: 'success',
                    timeout: 2000,
                })
                return response.data
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Schnell-Merken.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async update(id, data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.put('/api/admin/materials/cards/' + id, { data })
                this.selected_card = response.data
                notification.notify({
                    message: 'Materialkarte aktualisiert.',
                    type: 'success',
                    timeout: 2000,
                })
                return response.data
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Aktualisieren der Materialkarte.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async destroy(id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                await axios.delete('/api/admin/materials/cards/' + id)
                this.selected_card = null
                notification.notify({
                    message: 'Materialkarte gelöscht.',
                    type: 'success',
                    timeout: 2000,
                })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Löschen der Materialkarte.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async addLinkAttachment(cardId, data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                await axios.post('/api/admin/materials/cards/' + cardId + '/attachments/link', { data })
                notification.notify({
                    message: 'Link-Anhang hinzugefügt.',
                    type: 'success',
                    timeout: 2000,
                })
                await this.show(cardId)
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Hinzufügen des Link-Anhangs.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async addFileAttachment(cardId, file, name = '') {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const formData = new FormData()
                formData.append('file', file)
                if (name) {
                    formData.append('name', name)
                }

                await axios.post('/api/admin/materials/cards/' + cardId + '/attachments/file', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                })

                notification.notify({
                    message: 'Datei-Anhang hinzugefügt.',
                    type: 'success',
                    timeout: 2000,
                })
                await this.show(cardId)
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Datei-Upload.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async deleteAttachment(attachmentId, cardId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                await axios.delete('/api/admin/materials/attachments/' + attachmentId)
                notification.notify({
                    message: 'Anhang gelöscht.',
                    type: 'success',
                    timeout: 2000,
                })
                await this.show(cardId)
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Löschen des Anhangs.',
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
