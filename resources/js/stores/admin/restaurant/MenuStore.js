import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

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

function sortMenusByTitle(menus = []) {
    return [...menus].sort((left, right) => {
        return String(left.title || '').localeCompare(String(right.title || ''), 'de')
    })
}

export const useMenuStore = defineStore('AdminRestaurantMenuStore', {
    state: () => ({
        menus: [],
    }),

    actions: {
        async index() {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++

            try {
                const response = await axios.get('/api/admin/restaurant/menus')
                this.menus = response.data?.data || []
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

        async store(payload) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++

            try {
                const response = await axios.post('/api/admin/restaurant/menus', buildFormData(payload))
                this.menus = sortMenusByTitle([...this.menus, response.data.data])
                notification.notify({
                    message: 'Menü wurde gespeichert.',
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

        async update(id, payload) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++

            try {
                const response = await axios.post(`/api/admin/restaurant/menus/${id}`, buildFormData(payload, 'PUT'))
                this.menus = sortMenusByTitle(this.menus.map((menu) => {
                    return Number(menu.id) === Number(id) ? response.data.data : menu
                }))
                notification.notify({
                    message: 'Menü wurde aktualisiert.',
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

        async destroy(id) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++

            try {
                await axios.delete(`/api/admin/restaurant/menus/${id}`)
                this.menus = this.menus.filter((menu) => Number(menu.id) !== Number(id))
                notification.notify({
                    message: 'Menü wurde gelöscht.',
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
    },
})
