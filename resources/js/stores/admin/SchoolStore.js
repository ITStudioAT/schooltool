import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useSchoolStore = defineStore('AdminSchoolStore', {
    state: () => ({
        schools: [],
        selected_schools: [],
        search_string: '',
        expired_only: false,
        meta: [],
        data: {},
        saved_school: null,
        answer: null,
        switchable_schools: [],
        switch_user_matches: [],
        hopper_accounts: [],
        hopper_switchable_schools: [],
        hopper_user_matches: [],
        school_licences: [],
        school_licence_users: [],
        school_licence_users_meta: [],
        school_licence_users_roles: [],
        school_licence_users_active_role_filters: [],
        school_licence_users_role_statuses: {},
        school_licence_user_role_details: [],
        school_licence_user_role_details_valid_until: null,
        school_admins: [],
        teachers: [],
    }),

    actions: {
        applySchoolInfos(data = {}) {
            this.school_licences = data.licences || []
            this.school_admins = data.admins || []
            this.teachers = data.teachers || []
        },

        async index(page = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            const search_string = this.search_string
            const expired_only = this.expired_only ? 1 : 0
            try {
                const response = await axios.get(`/api/admin/schools`, { params: { search_string, page, expired_only } })
                this.schools = response.data.data
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

        async loadSwitchableSchools(email = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const payload = {}
                if (typeof email === 'string' && email.trim() !== '') payload.email = email.trim()
                const response = await axios.post(`/api/admin/schools/load_switchable_schools`, payload)
                this.switchable_schools = response.data
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

        async searchSwitchUsers(last_name = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const payload = {}
                if (typeof last_name === 'string' && last_name.trim() !== '') payload.last_name = last_name.trim()
                const response = await axios.post(`/api/admin/schools/search_switch_users`, payload)
                this.switch_user_matches = Array.isArray(response.data) ? response.data : []
                return this.switch_user_matches
            } catch (error) {
                this.switch_user_matches = []
                notification.notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async loadHopperAccounts() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get('/api/admin/hopper_accounts')
                this.hopper_accounts = response.data?.data || []
                return this.hopper_accounts
            } catch (error) {
                this.hopper_accounts = []
                notification.notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async loadHopperSwitchableSchools(email = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const payload = {}
                if (typeof email === 'string' && email.trim() !== '') payload.email = email.trim()
                const response = await axios.post('/api/admin/hopper_accounts/load_switchable_schools', payload)
                this.hopper_switchable_schools = Array.isArray(response.data) ? response.data : []
                return this.hopper_switchable_schools
            } catch (error) {
                this.hopper_switchable_schools = []
                notification.notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async searchHopperUsers(last_name = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const payload = {}
                if (typeof last_name === 'string' && last_name.trim() !== '') payload.last_name = last_name.trim()
                const response = await axios.post('/api/admin/hopper_accounts/search_users', payload)
                this.hopper_user_matches = Array.isArray(response.data) ? response.data : []
                return this.hopper_user_matches
            } catch (error) {
                this.hopper_user_matches = []
                notification.notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async storeHopperAccount(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post('/api/admin/hopper_accounts', data)
                this.hopper_accounts = response.data?.data || []
                notification.notify({
                    message: 'Hopper-Konto gespeichert.',
                    type: 'success',
                    timeout: 3000,
                })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async deleteHopperAccount(target_user_id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.delete('/api/admin/hopper_accounts', {
                    data: { target_user_id },
                })
                this.hopper_accounts = response.data?.data || []
                notification.notify({
                    message: 'Hopper-Konto entfernt.',
                    type: 'success',
                    timeout: 3000,
                })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async switchHopperAccount(target_user_id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                await axios.post('/api/admin/hopper_accounts/switch', { target_user_id })
                notification.notify({
                    message: 'Konto gewechselt.',
                    type: 'success',
                    timeout: 3000,
                })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async switchSchool(school_id, email = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const payload = { school_id }
                if (typeof email === 'string' && email.trim() !== '') payload.email = email.trim()
                const response = await axios.post(`/api/admin/schools/switch_school`, payload)
                notification.notify({
                    message: 'Schule gewechselt.',
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

        async update(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.put(`/api/admin/schools/${data.id}`, data)
                this.saved_school = response.data

                const index = this.schools.findIndex((s) => s.id === this.saved_school.id)

                if (index !== -1) {
                    // Replace the old element with the new one
                    this.schools.splice(index, 1, this.saved_school)
                }

                this.schools.sort((a, b) => a.long_name.localeCompare(b.long_name))
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
                const response = await axios.post(`/api/admin/schools`, data)
                this.saved_school = response.data
                this.schools.push(this.saved_school)
                this.schools.sort((a, b) => a.long_name.localeCompare(b.long_name))
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

        async deleteSchools(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                this.answer = await axios.post(`/api/admin/schools/delete_schools`, data)

                notification.notify({
                    message: 'Die Schulen wurden gelöscht.',
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

        async loadSchoolInfos(school_id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/schools/load_school_infos`, { school_id })
                this.applySchoolInfos(response.data)

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

        async addLicence(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/schools/add_licence`, { data })
                this.school_licences = response.data

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

        async addAdmin(data, roles) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/schools/add_admin`, { data, roles })
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

        async deleteLicence(school_licence_id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/schools/delete_licence`, { school_licence_id })
                this.school_licences = response.data
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

        async saveSchoolLicenceSchool(school_licence_id, data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                await axios.put(`/api/admin/school_licences/${school_licence_id}/save_school`, data)
                notification.notify({ message: 'Schul-Lizenz gespeichert.', type: 'success', timeout: 3000 })
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

        async saveSchoolLicenceModel(school_licence_id, licence_model) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.put(`/api/admin/school_licences/${school_licence_id}/save_licence_model`, { licence_model })
                this.school_licences = response.data

                notification.notify({
                    message: 'Schul-Lizenzmodell wurde gespeichert.',
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

        async loadSchoolLicenceUsers(school_licence_id, page = null, search_string = null, role_names = [], expired_only = false) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get(`/api/admin/school_licences/${school_licence_id}/users`, {
                    params: { page, search_string, role_names, expired_only: expired_only ? 1 : 0 },
                })
                this.school_licence_users = response.data.data || []
                this.school_licence_users_meta = response.data.meta || []
                this.school_licence_users_roles = response.data.roles || []
                this.school_licence_users_active_role_filters = response.data.active_role_filters || []
                this.school_licence_users_role_statuses = response.data.role_statuses_by_user || {}

                return true
            } catch (error) {
                this.school_licence_users = []
                this.school_licence_users_meta = []
                this.school_licence_users_roles = []
                this.school_licence_users_active_role_filters = []
                this.school_licence_users_role_statuses = {}
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

        async loadSchoolLicenceUserRoles(school_licence_id, user_id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get(`/api/admin/school_licences/${school_licence_id}/users/${user_id}/roles`)
                this.school_licence_user_role_details = response.data.roles || []
                this.school_licence_user_role_details_valid_until = response.data.school_licence_valid_until || null
                return response.data
            } catch (error) {
                this.school_licence_user_role_details = []
                this.school_licence_user_role_details_valid_until = null
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

        async saveSchoolLicenceUserRoles(school_licence_id, user_id, roles) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.put(`/api/admin/school_licences/${school_licence_id}/users/${user_id}/roles`, { roles })
                this.school_licence_user_role_details = response.data.roles || []
                this.school_licence_user_role_details_valid_until = response.data.school_licence_valid_until || null
                notification.notify({
                    message: 'Benutzerlizenzen wurden gespeichert.',
                    type: 'success',
                    timeout: 3000,
                })
                return response.data
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

        async deleteAdmin(admin_id, is_delete_complete) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/schools/delete_admin`, { admin_id, is_delete_complete })
                this.school_licences = response.data
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
    },
})
