import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useRegisterStore = defineStore('AdminRegisterStore', {
    state: () => ({
        registers: [],
        selected_register: null,
        active_registers: null,
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
            try {
                const response = await axios.post(`/api/admin/registers/get_active`, {})
                this.active_registers = response.data
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

                this.selected_register = response.data
                const index = this.registers.findIndex((s) => s.id === this.selected_register.id)

                if (index !== -1) {
                    // Replace the old element with the new one
                    this.registers.splice(index, 1, this.selected_register)
                }
                this.registers.sort((a, b) => a.name.localeCompare(b.name))
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

                this.selected_register = response.data
                this.registers.push(this.selected_register)
                this.registers.sort((a, b) => a.name.localeCompare(b.name))

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

        async destroy(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++

            try {
                const response = await axios.delete(`/api/admin/registers/${data.id}`)
                notification.notify({
                    message: 'Das Anmeldesystem wurde erfolgreich gelöscht.',
                    type: 'success',
                    timeout: 3000,
                })
                this.registers = this.registers.filter((sy) => sy.id !== this.selected_register.id)
                this.selected_register = null
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
                notification.notify({
                    message: 'Das Anmeldesystem wurde erfolgreich ausgewählt.',
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

        async toggleRegister(register) {
            const register_id = register.id
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/registers/toggle`, { register_id })
                notification.notify({
                    message: 'Das Anmeldesystem wurde erfolgreich umgeschaltet.',
                    type: 'success',
                    timeout: 3000,
                })

                const found = this.registers.find((r) => r.id === register.id)
                if (found) {
                    found.is_active = !found.is_active
                }

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
