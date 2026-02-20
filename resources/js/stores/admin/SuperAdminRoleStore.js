import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useSuperAdminRoleStore = defineStore('SuperAdminRoleStore', {
    state: () => ({
        roles: [],
        selected_roles: [],
        search_string: '',
        meta: {
            current_page: 1,
            per_page: 0,
            total: 0,
            last_page: 1,
            from: 0,
            to: 0,
        },
        data: {},
        saved_role: null,
    }),

    actions: {
        async index(page = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++

            try {
                const response = await axios.get('/api/admin/roles', {
                    params: {
                        search_model: { search_string: this.search_string },
                        page,
                    },
                })

                this.roles = response.data.items ?? []
                const pagination = response.data.pagination ?? {}
                const currentPage = Number(pagination.current_page || 1)
                const perPage = Number(pagination.per_page || 0)
                const total = Number(pagination.total || 0)

                this.meta = {
                    current_page: currentPage,
                    per_page: perPage,
                    total,
                    last_page: Number(pagination.last_page || 1),
                    from: total === 0 ? 0 : (currentPage - 1) * perPage + 1,
                    to: total === 0 ? 0 : Math.min(currentPage * perPage, total),
                }

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

        async update(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++

            try {
                const response = await axios.put(`/api/admin/roles/${data.id}`, data)
                this.saved_role = response.data

                const index = this.roles.findIndex((role) => role.id === this.saved_role.id)
                if (index !== -1) {
                    this.roles.splice(index, 1, this.saved_role)
                }

                this.roles.sort((a, b) => a.name.localeCompare(b.name))
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
                const response = await axios.post('/api/admin/roles', data)
                this.saved_role = response.data
                this.roles.push(this.saved_role)
                this.roles.sort((a, b) => a.name.localeCompare(b.name))
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

        async deleteRoles(ids) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++

            try {
                await axios.post('/api/admin/roles/destroy_multiple', ids)
                notification.notify({
                    message: 'Die Rollen wurden gelöscht.',
                    type: 'success',
                    timeout: 3000,
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
