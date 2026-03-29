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

export const useFoodStore = defineStore('AdminRestaurantFoodStore', {
    state: () => ({
        foods: [],
    }),

    actions: {
        async index() {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()
            adminStore.is_loading++

            try {
                const response = await axios.get('/api/admin/restaurant/foods')
                this.foods = response.data?.data || []
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
                const response = await axios.post('/api/admin/restaurant/foods', buildFormData(payload))
                this.foods = [...this.foods, response.data.data].sort((left, right) => {
                    return String(left.title || '').localeCompare(String(right.title || ''), 'de')
                })
                notification.notify({
                    message: 'Speise wurde gespeichert.',
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
                const response = await axios.post(`/api/admin/restaurant/foods/${id}`, buildFormData(payload, 'PUT'))
                this.foods = this.foods
                    .map((food) => (Number(food.id) === Number(id) ? response.data.data : food))
                    .sort((left, right) => String(left.title || '').localeCompare(String(right.title || ''), 'de'))
                notification.notify({
                    message: 'Speise wurde aktualisiert.',
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
                await axios.delete(`/api/admin/restaurant/foods/${id}`)
                this.foods = this.foods.filter((food) => Number(food.id) !== Number(id))
                notification.notify({
                    message: 'Speise wurde geloescht.',
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
