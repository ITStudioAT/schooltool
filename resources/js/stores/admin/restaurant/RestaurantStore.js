import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

function sortByOrderAndTitle(items = []) {
    return [...items].sort((left, right) => {
        const sortOrderDiff = Number(left?.sort_order || 0) - Number(right?.sort_order || 0)
        if (sortOrderDiff !== 0) {
            return sortOrderDiff
        }

        return String(left?.title || '').localeCompare(String(right?.title || ''), 'de')
    })
}

function sortByTitle(items = []) {
    return [...items].sort((left, right) => {
        return String(left?.title || '').localeCompare(String(right?.title || ''), 'de')
    })
}

function buildFormData(payload, method = null) {
    const formData = new FormData()

    if (method) {
        formData.append('_method', method)
    }

    Object.entries(payload).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
            return
        }

        if (Array.isArray(value)) {
            value.forEach((entry) => {
                formData.append(`${key}[]`, entry)
            })
            return
        }

        formData.append(key, value)
    })

    return formData
}

export const useRestaurantStore = defineStore('AdminRestaurantStore', {
    state: () => ({
        settings: null,
    }),

    getters: {
        categories: (state) => state.settings?.categories || [],
        ingredientIcons: (state) => state.settings?.ingredient_icons || [],
        allergenOptions: (state) => state.settings?.allergen_options || [],
        allergenSuggestions: (state) => state.settings?.allergen_suggestions || [],
        generalSettings: (state) => state.settings?.general_settings || {},
        canManageGeneralSettings: (state) => state.settings?.can_manage_general_settings === true,
        userSettings: (state) => state.settings?.user_settings || {},
        canManageUserSettings: (state) => state.settings?.can_manage_user_settings === true,
        sepaSettings: (state) => state.settings?.sepa_settings || {},
        canManageSepaSettings: (state) => state.settings?.can_manage_sepa_settings === true,
        onlineSettings: (state) => state.settings?.online_settings || {},
        canManageOnlineSettings: (state) => state.settings?.can_manage_online_settings === true,
        stats: (state) => state.settings?.stats || {},
    },

    actions: {
        async loadSettings() {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++

            try {
                const response = await axios.get('/api/admin/restaurant/settings')
                this.settings = response.data
                this.settings.categories = sortByOrderAndTitle(this.settings.categories || [])
                this.settings.ingredient_icons = sortByTitle(this.settings.ingredient_icons || [])
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

        async updateUserSettings(restaurantFoodsPaginationNumber) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const value = Number(restaurantFoodsPaginationNumber)

            if (! Number.isFinite(value) || value <= 0) {
                notification.notify({
                    message: 'Bitte eine gültige Zahl für Speisen pro Seite eingeben.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++

            try {
                const response = await axios.put('/api/admin/restaurant/user-settings', {
                    data: {
                        restaurant_foods_pagination_number: Math.max(1, Math.min(200, Math.round(value))),
                    },
                })

                this.settings = {
                    ...(this.settings || {}),
                    user_settings: response?.data?.data || {},
                    can_manage_user_settings: true,
                }

                notification.notify({
                    message: 'Benutzereinstellungen gespeichert.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Speichern der Benutzereinstellungen.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async updateGeneralSettings(settings) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()

            adminStore.is_loading++

            try {
                const response = await axios.put('/api/admin/restaurant/general-settings', {
                    data: settings,
                })

                this.settings = {
                    ...(this.settings || {}),
                    general_settings: response?.data?.data || {},
                    can_manage_general_settings: true,
                }

                notification.notify({
                    message: 'Allgemeine Restaurant-Einstellungen gespeichert.',
                    type: 'success',
                    timeout: 2200,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Speichern der allgemeinen Restaurant-Einstellungen.',
                    type: 'error',
                    timeout: 3000,
                })

                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async updateSepaSettings(settings) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()

            adminStore.is_loading++

            try {
                const response = await axios.put('/api/admin/restaurant/sepa-settings', {
                    data: settings,
                })

                this.settings = {
                    ...(this.settings || {}),
                    sepa_settings: response?.data?.data || {},
                    can_manage_sepa_settings: true,
                }

                notification.notify({
                    message: 'SEPA-Einstellungen gespeichert.',
                    type: 'success',
                    timeout: 2200,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Speichern der SEPA-Einstellungen.',
                    type: 'error',
                    timeout: 3000,
                })

                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async updateOnlineSettings(settings) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()

            adminStore.is_loading++

            try {
                const response = await axios.put('/api/admin/restaurant/online-settings', {
                    data: settings,
                })

                this.settings = {
                    ...(this.settings || {}),
                    online_settings: response?.data?.data || {},
                    can_manage_online_settings: true,
                }

                notification.notify({
                    message: 'Online-Einstellungen gespeichert.',
                    type: 'success',
                    timeout: 2200,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Speichern der Online-Einstellungen.',
                    type: 'error',
                    timeout: 3000,
                })

                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async storeCategory(payload) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++

            try {
                const response = await axios.post('/api/admin/restaurant/categories', payload)
                const categories = [...this.categories, response.data.data]
                this.settings = {
                    ...(this.settings || {}),
                    categories: sortByOrderAndTitle(categories),
                }
                notification.notify({
                    message: 'Kategorie wurde gespeichert.',
                    type: 'success',
                    timeout: 2200,
                })
                return response.data.data
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

        async updateCategory(id, payload) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++

            try {
                const response = await axios.put(`/api/admin/restaurant/categories/${id}`, payload)
                const categories = this.categories.map((category) => {
                    return Number(category.id) === Number(id) ? response.data.data : category
                })
                this.settings = {
                    ...(this.settings || {}),
                    categories: sortByOrderAndTitle(categories),
                }
                notification.notify({
                    message: 'Kategorie wurde aktualisiert.',
                    type: 'success',
                    timeout: 2200,
                })
                return response.data.data
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

        async destroyCategory(id) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++

            try {
                await axios.delete(`/api/admin/restaurant/categories/${id}`)
                this.settings = {
                    ...(this.settings || {}),
                    categories: this.categories.filter((category) => Number(category.id) !== Number(id)),
                }
                notification.notify({
                    message: 'Kategorie wurde geloescht.',
                    type: 'success',
                    timeout: 2200,
                })
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

        async storeIngredientIcon(payload) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++

            try {
                const response = await axios.post(
                    '/api/admin/restaurant/ingredient_icons',
                    buildFormData(payload)
                )
                const ingredientIcons = [...this.ingredientIcons, response.data.data]
                this.settings = {
                    ...(this.settings || {}),
                    ingredient_icons: sortByTitle(ingredientIcons),
                }
                notification.notify({
                    message: 'Zutaten-Symbol wurde gespeichert.',
                    type: 'success',
                    timeout: 2200,
                })
                return response.data.data
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

        async updateIngredientIcon(id, payload) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++

            try {
                const response = await axios.post(
                    `/api/admin/restaurant/ingredient_icons/${id}`,
                    buildFormData(payload, 'PUT')
                )
                const ingredientIcons = this.ingredientIcons.map((icon) => {
                    return Number(icon.id) === Number(id) ? response.data.data : icon
                })
                this.settings = {
                    ...(this.settings || {}),
                    ingredient_icons: sortByTitle(ingredientIcons),
                }
                notification.notify({
                    message: 'Zutaten-Symbol wurde aktualisiert.',
                    type: 'success',
                    timeout: 2200,
                })
                return response.data.data
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

        async destroyIngredientIcon(id) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++

            try {
                await axios.delete(`/api/admin/restaurant/ingredient_icons/${id}`)
                this.settings = {
                    ...(this.settings || {}),
                    ingredient_icons: this.ingredientIcons.filter((icon) => Number(icon.id) !== Number(id)),
                }
                notification.notify({
                    message: 'Zutaten-Symbol wurde geloescht.',
                    type: 'success',
                    timeout: 2200,
                })
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

        async loadAvailableIngredientIconsFromPrivateDirectory() {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++

            try {
                const response = await axios.get('/api/admin/restaurant/ingredient_icons/private-directory')

                return {
                    source_directory: response?.data?.source_directory || '',
                    icons: response?.data?.data || [],
                }
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Verzeichnis konnte nicht geladen werden.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async syncIngredientIconsFromPrivateDirectory(paths = []) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++

            try {
                const response = await axios.post('/api/admin/restaurant/ingredient_icons/sync-private', {
                    paths,
                })
                const ingredientIcons = sortByTitle(response?.data?.data || [])

                this.settings = {
                    ...(this.settings || {}),
                    ingredient_icons: ingredientIcons,
                    stats: {
                        ...(this.settings?.stats || {}),
                        ingredient_icons_count: ingredientIcons.length,
                    },
                }

                notification.notify({
                    message: response?.data?.message || 'Zutaten-Symbole wurden übernommen.',
                    type: 'success',
                    timeout: 2600,
                })

                return ingredientIcons
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Zutaten-Symbole konnten nicht übernommen werden.',
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
