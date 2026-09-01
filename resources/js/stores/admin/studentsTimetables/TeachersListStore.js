import { defineStore } from 'pinia'
import {
    activate as activateTeacherAccount,
    index as teacherAccountsIndex,
    setActiveState as setTeacherAccountsActiveState,
    setRole as setTeacherAccountRole,
    store as storeTeacherAccount,
    toggleActive as toggleTeacherAccountActive,
    update as updateImportedTeacherAccount,
    updateUser as updateRegisteredTeacherAccount,
} from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/TeacherAccountController'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useStudentsTimetablesTeachersListStore = defineStore('AdminStudentsTimetablesTeachersListStore', {
    state: () => ({
        teachers: [],
        selected_teachers: [],
        search_string: '',
        pending_action: null,
        action_errors: {},
        meta: {
            current_page: 1,
            per_page: 0,
            total: 0,
            last_page: 1,
            from: 0,
            to: 0,
        },
    }),

    actions: {
        async index(page = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++

            try {
                const response = await axios.get(teacherAccountsIndex.url(), {
                    params: {
                        search_string: this.search_string,
                        page,
                    },
                })
                this.teachers = response.data.data ?? []
                this.meta = response.data.meta ?? this.meta

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status ?? 500,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })

                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async activateTeacher(teacherId, rowId) {
            return this.runTeacherAction(
                `status:${rowId}`,
                () => axios.post(activateTeacherAccount.url(teacherId)),
                'Lehrerkonto wurde aktiviert.',
            )
        },

        async toggleActive(userId, rowId) {
            return this.runTeacherAction(
                `status:${rowId}`,
                () => axios.post(toggleTeacherAccountActive.url(userId)),
                'Aktivstatus wurde geändert.',
            )
        },

        async setAllActive(isActive) {
            return this.runTeacherAction(
                `bulk:${isActive ? 'active' : 'inactive'}`,
                () => axios.put(setTeacherAccountsActiveState.url(), { is_active: isActive }),
                isActive ? 'Alle Lehrkräfte wurden aktiviert.' : 'Alle deaktivierbaren Lehrkräfte wurden deaktiviert.',
            )
        },

        async createTeacher(values) {
            return this.runTeacherAction(
                'create',
                () => axios.post(storeTeacherAccount.url(), values),
                'Lehrkraft wurde hinzugefügt.',
            )
        },

        async setRole(userId, role, rowId, pendingRole = role) {
            return this.runTeacherAction(
                `tt-role:${rowId}:${pendingRole}`,
                () => axios.put(setTeacherAccountRole.url(userId), { role }),
                'TT-Rolle wurde geändert.',
            )
        },

        async updateTeacher(teacher, values) {
            const request = teacher.user_id === null
                ? () => axios.put(updateImportedTeacherAccount.url(teacher.teacher_id), values)
                : () => axios.put(updateRegisteredTeacherAccount.url(teacher.user_id), values)

            return this.runTeacherAction(
                `edit:${teacher.id}`,
                request,
                'Lehrerdaten wurden geändert.',
            )
        },

        async runTeacherAction(actionKey, request, successMessage) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            this.pending_action = actionKey
            this.action_errors = {}
            adminStore.is_loading++

            try {
                const response = await request()
                notification.notify({
                    status: 200,
                    message: successMessage,
                    type: 'success',
                    timeout: 3000,
                })
                await this.index(this.meta.current_page)

                return response.data ?? true
            } catch (error) {
                this.action_errors = error.response?.data?.errors ?? {}
                notification.notify({
                    status: error.response?.status ?? 500,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })

                return false
            } finally {
                this.pending_action = null
                adminStore.is_loading--
            }
        },
    },
})
