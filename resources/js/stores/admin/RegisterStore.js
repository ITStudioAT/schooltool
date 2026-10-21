import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { destroy as destroyRegister } from '@/actions/App/Http/Controllers/Admin/RegisterController'

export const useRegisterStore = defineStore('AdminRegisterStore', {
    state: () => ({
        registers: [],
        selected_register: null,
        active_registers: [],
        active_registers_status: 'idle',
        register_dates: [],
    }),

    actions: {
        async index() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get(`/api/admin/registers`, {})
                this.registers = response.data
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async loadActiveRegisters() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            this.active_registers_status = 'loading'
            try {
                const response = await axios.post(`/api/admin/registers/get_active`, {})
                this.active_registers = response.data
                this.active_registers_status = 'ready'
                return true
            } catch (error) {
                this.active_registers_status = 'error'
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
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
                const response = await axios.put(`/api/admin/registers/${data.id}`, data)
                notification.notify({
                    message: 'Das Anmeldesystem wurde erfolgreich geändert.',
                    type: 'success',
                    timeout: 3000,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
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
                const response = await axios.post(`/api/admin/registers`, data)

                notification.notify({
                    message: 'Das Anmeldesystem wurde erfolgreich erstellt.',
                    type: 'success',
                    timeout: 3000,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async destroy(data, confirmation) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++

            try {
                await axios.delete(destroyRegister.url(data.id), { data: { confirmation } })
                if (this.selected_register?.id === data.id) this.selected_register = null
                if (adminStore.selected_register?.id === data.id) {
                    adminStore.selected_register = null
                    this.register_dates = []
                }
                if (adminStore.selected_active_register?.id === data.id) adminStore.selected_active_register = null
                notification.notify({
                    message: 'Das Anmeldetool wurde mit allen zugehörigen Anmeldungen und Terminen gelöscht.',
                    type: 'success',
                    timeout: 3000,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async setSelectedRegister(register_id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/registers/set_active`, { register_id })
                this.selected_register = response.data
                /*
                notification.notify({
                    message: 'Das Anmeldesystem wurde erfolgreich ausgewählt.',
                    type: 'success',
                    timeout: 3000,
                })
                    */

                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async toggleRegister(register) {
            const register_id = register.id
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/registers/toggle`, { register_id })
                notification.notify({
                    message: 'Das Anmeldesystem wurde erfolgreich ' + (register.is_active ? 'ausgeschaltet.' : 'eingeschaltet.'),
                    type: 'success',
                    timeout: 3000,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
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
