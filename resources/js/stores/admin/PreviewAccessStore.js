import { defineStore } from 'pinia'
import { index, updateSettings, updateUser } from '@/actions/App/Http/Controllers/Admin/FeaturePreviewController'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const usePreviewAccessStore = defineStore('AdminPreviewAccessStore', {
    state: () => ({
        data: null,
        is_loading: false,
        is_saving: false,
        error: '',
    }),

    actions: {
        async load() {
            if (this.is_loading || this.is_saving) return false

            this.is_loading = true
            this.error = ''

            try {
                const response = await axios.get(index.url())
                this.data = response.data.data
                return true
            } catch (error) {
                this.error = error.response?.data?.message || 'Die Vorschau-Einstellungen konnten nicht geladen werden.'
                return false
            } finally {
                this.is_loading = false
            }
        },

        async saveEnabled(enabled) {
            return this.save(updateSettings.url(), { enabled })
        },

        async saveUser(userId, allowed) {
            return this.save(updateUser.url({ user: userId }), { allowed })
        },

        async save(url, payload) {
            if (this.is_loading || this.is_saving) return false

            this.is_saving = true
            this.error = ''

            try {
                const response = await axios.put(url, payload)
                this.data = response.data.data

                useNotificationStore().notify({
                    message: 'Vorschau-Einstellungen gespeichert.',
                    type: 'success',
                    timeout: 3000,
                })

                try {
                    const config = await useAdminStore().loadConfig()
                    if (config === false) {
                        this.error = 'Die Freigabe ist gespeichert. Bitte laden Sie die Seite neu, um die Navigation zu aktualisieren.'
                    }
                } catch {
                    this.error = 'Die Freigabe ist gespeichert. Bitte laden Sie die Seite neu, um die Navigation zu aktualisieren.'
                }

                return true
            } catch (error) {
                this.error = error.response?.data?.message || 'Die Vorschau-Einstellungen konnten nicht gespeichert werden.'
                return false
            } finally {
                this.is_saving = false
            }
        },
    },
})
