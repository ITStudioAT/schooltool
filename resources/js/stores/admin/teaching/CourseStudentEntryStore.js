import { defineStore } from 'pinia'
import {
    index as notificationRecipientsIndex,
    preview as previewNotificationRecipients,
    store as sendEntryNotifications,
    update as confirmEntryNotification,
} from '@/actions/App/Http/Controllers/Admin/Teaching/CourseStudentEntryNotificationController'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useCourseStudentEntryStore = defineStore('AdminCourseStudentEntryStore', {
    state: () => {
        return {
            entries: [],
            courseEntries: [],
        }
    },

    actions: {
        async index(courseId, userId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get(`/api/admin/teaching/course_student_entries`, {
                    params: { course_id: courseId, user_id: userId },
                })
                this.entries = response.data.data
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
                const response = await axios.post(`/api/admin/teaching/course_student_entries`, data)
                const entry = response?.data?.data
                if (entry) {
                    this.entries = [entry, ...(this.entries || []).filter((e) => e.id !== entry.id)]
                    this.courseEntries = [entry, ...(this.courseEntries || []).filter((e) => e.id !== entry.id)]
                }
                return response.data
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
            if (!data?.id) {
                notification.notify({
                    status: 422,
                    message: 'Eintrag-ID fehlt. Bitte Seite neu laden.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            }
            adminStore.is_loading++
            try {
                const response = await axios.put(`/api/admin/teaching/course_student_entries/${data.id}`, data)
                const entry = response?.data?.data
                if (entry) {
                    this.entries = (this.entries || []).map((e) => (e.id === entry.id ? entry : e))
                    this.courseEntries = (this.courseEntries || []).map((e) => (e.id === entry.id ? entry : e))
                }
                return response.data
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

        async destroy(entryId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                await axios.delete(`/api/admin/teaching/course_student_entries/${entryId}`)
                this.entries = (this.entries || []).filter((e) => e.id !== entryId)
                this.courseEntries = (this.courseEntries || []).filter((e) => e.id !== entryId)
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

        async indexByCourse(courseId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get(`/api/admin/teaching/course_student_entries`, {
                    params: { course_id: courseId },
                })
                this.courseEntries = response.data.data
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

        async indexTableData(courseId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get('/api/admin/teaching/course_student_entries', {
                    params: { course_id: courseId, include_table_data: 1 },
                })

                return response.data
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

        async notificationRecipients(entryId) {
            const notification = useNotificationStore()

            try {
                const response = await axios.get(notificationRecipientsIndex.url(entryId))

                return response.data.data
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Verständigungen konnten nicht geladen werden.',
                    type: 'error',
                    timeout: 3000,
                })

                return false
            }
        },

        async previewNotificationRecipients(courseId, userId, type) {
            const notification = useNotificationStore()

            try {
                const response = await axios.get(previewNotificationRecipients.url(), {
                    params: {
                        course_id: courseId,
                        user_id: userId,
                        type,
                    },
                })

                return response.data.data
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Empfänger:innen konnten nicht geladen werden.',
                    type: 'error',
                    timeout: 3000,
                })

                return false
            }
        },

        async sendNotifications(entryId, recipients) {
            const notification = useNotificationStore()

            try {
                const response = await axios.post(sendEntryNotifications.url(entryId), { recipients })
                this.syncPendingNotificationConfirmation(entryId, response.data.data)
                notification.notify({
                    message: response.data.message,
                    type: 'success',
                    timeout: 3000,
                })

                return response.data.data
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Die E-Mails konnten nicht gesendet werden.',
                    type: 'error',
                    timeout: 3000,
                })

                return false
            }
        },

        async confirmNotification(entryId, notificationId) {
            const notification = useNotificationStore()

            try {
                const response = await axios.patch(confirmEntryNotification.url({
                    courseStudentEntry: entryId,
                    notification: notificationId,
                }))
                this.syncPendingNotificationConfirmation(entryId, response.data.data)
                notification.notify({
                    message: response.data.message,
                    type: 'success',
                    timeout: 3000,
                })

                return response.data.data
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Die Bestätigung konnte nicht gespeichert werden.',
                    type: 'error',
                    timeout: 3000,
                })

                return false
            }
        },

        syncPendingNotificationConfirmation(entryId, recipients) {
            const hasPendingNotificationConfirmation = recipients.some((recipient) => (
                Boolean(recipient.informed_at) && !recipient.confirmed_at
            ))
            const updateEntry = (entry) => (
                entry.id === entryId
                    ? { ...entry, has_pending_notification_confirmation: hasPendingNotificationConfirmation }
                    : entry
            )

            this.entries = (this.entries || []).map(updateEntry)
            this.courseEntries = (this.courseEntries || []).map(updateEntry)
        },

        clear() {
            this.entries = []
        },
    },
})
