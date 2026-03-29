import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useMenuPlanStore = defineStore('AdminRestaurantMenuPlanStore', {
    state: () => ({
        plans: [],
        isLoaded: false,
    }),

    getters: {
        plansByDate: (state) => {
            const map = {}

            state.plans.forEach((plan) => {
                map[`${plan.start_date}__${plan.end_date}`] = plan
            })

            return map
        },
    },

    actions: {
        async load() {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()

            adminStore.is_loading++

            try {
                const response = await axios.get('/api/admin/restaurant/menu-plans')
                this.plans = response?.data?.data || []
                this.isLoaded = true

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Menüpläne konnten nicht geladen werden.',
                    type: 'error',
                    timeout: 3000,
                })

                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async show(id) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()

            adminStore.is_loading++

            try {
                const response = await axios.get(`/api/admin/restaurant/menu-plans/${id}`)

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Menüplan konnte nicht geladen werden.',
                    type: 'error',
                    timeout: 3000,
                })

                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async store(payload) {
            const adminStore = useAdminStore()
            const notification = useNotificationStore()

            adminStore.is_loading++

            try {
                const response = await axios.post('/api/admin/restaurant/menu-plans', payload)
                const newPlan = response?.data?.data

                if (newPlan) {
                    this.plans.push(newPlan)
                    this.plans.sort((a, b) => String(a.start_date).localeCompare(String(b.start_date)))
                }

                notification.notify({
                    message: response?.data?.message || 'Menüplan wurde gespeichert.',
                    type: 'success',
                    timeout: 2200,
                })

                return newPlan || true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Menüplan konnte nicht gespeichert werden.',
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
                const response = await axios.put(`/api/admin/restaurant/menu-plans/${id}`, payload)
                const updatedPlan = response?.data?.data

                if (updatedPlan) {
                    const index = this.plans.findIndex((p) => p.id === id)

                    if (index !== -1) {
                        this.plans[index] = updatedPlan
                    }
                }

                notification.notify({
                    message: response?.data?.message || 'Menüplan wurde aktualisiert.',
                    type: 'success',
                    timeout: 2200,
                })

                return updatedPlan || true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Menüplan konnte nicht aktualisiert werden.',
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
                await axios.delete(`/api/admin/restaurant/menu-plans/${id}`)
                this.plans = this.plans.filter((p) => p.id !== id)

                notification.notify({
                    message: 'Menüplan wurde gelöscht.',
                    type: 'success',
                    timeout: 2200,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Menüplan konnte nicht gelöscht werden.',
                    type: 'error',
                    timeout: 3000,
                })

                return false
            } finally {
                adminStore.is_loading--
            }
        },

        findPlanForDay(isoDate) {
            return this.plans.find((plan) => isoDate >= plan.start_date && isoDate <= plan.end_date) || null
        },

        planCountForDay(isoDate) {
            return this.plans.filter((plan) => isoDate >= plan.start_date && isoDate <= plan.end_date).length
        },
    },
})
