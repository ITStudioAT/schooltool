import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useSchoolyearStore = defineStore('AdminSchoolyearStore', {
    state: () => ({
        schoolyears: [],
        selected_schoolyear: null,
        search_string: '',
        meta: [],
    }),

    actions: {
        async index() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get(`/api/admin/schoolyears`, {})
                this.schoolyears = response.data
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

        async indexPaginate(page = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            const search_string = this.search_string
            try {
                const response = await axios.get(`/api/admin/schoolyears_paginate`, { params: { search_string, page } })
                this.schoolyears = response.data.data
                this.meta = response.data.meta
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
                const response = await axios.put(`/api/admin/schoolyears/${data.id}`, data)

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
                const response = await axios.post(`/api/admin/schoolyears`, data)
                this.selected_schoolyear = response.data
                this.schoolyears.push(this.selected_schoolyear)
                this.schoolyears.sort((a, b) => b.name.localeCompare(a.name))

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
            const registerStore = useRegisterStore()
            adminStore.is_loading++

            try {
                const response = await axios.delete(`/api/admin/schoolyears/${data.id}`)
                this.schoolyears = this.schoolyears.filter((sy) => sy.id !== this.selected_schoolyear.id)
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

        async setActiveSchoolyear(schoolyear_id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/schoolyears/set_active`, { schoolyear_id })
                this.selected_schoolyear = response.data
                /*
                notification.notify({
                    message: 'Das Schuljahr wurde erfolgreich ausgewählt.',
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

        async setActiveSchoolyearInSchoolTool(schoolyear_id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/school_tools/set_active_schoolyear`, { schoolyear_id })
                notification.notify({
                    message: 'Das aktive Schuljahr wurde erfolgreich gesetzt.',
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
