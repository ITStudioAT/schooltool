import { defineStore } from 'pinia'
import { useCourseStudentEntryStore } from '@/stores/admin/teaching/CourseStudentEntryStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { createWorkCheckStream } from '@/pages/admin/teaching/overview/components/workCheckProgress'

export const useCourseWorkStore = defineStore('AdminCourseWorkStore', {
    state: () => {
        return {
            courseWorks: [],
            loadedCourseId: null,
            selected_courseWork: null,
        }
    },

    actions: {
        async index(courseId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const selectedId = this.selected_courseWork?.id
                const response = await axios.get(`/api/admin/teaching/course_works`, {
                    params: { course_id: courseId },
                })
                this.courseWorks = response.data.data
                this.loadedCourseId = Number(courseId)
                if (selectedId) {
                    this.selected_courseWork = this.courseWorks.find((w) => w.id === selectedId) || null
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

        async store(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/teaching/course_works`, data)
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
                    message: 'Arbeit-ID fehlt. Bitte Seite neu laden.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            }
            adminStore.is_loading++
            try {
                const response = await axios.put(`/api/admin/teaching/course_works/${data.id}`, data)
                const saved = response.data?.data
                if (saved?.id && String(saved.id) === String(data.id)) {
                    const index = this.courseWorks.findIndex(work => String(work.id) === String(saved.id))
                    if (index !== -1) this.courseWorks.splice(index, 1, saved)
                    if (String(this.selected_courseWork?.id) === String(saved.id)) this.selected_courseWork = saved
                }
                if (response.data?.deadline_sync_warning) {
                    notification.notify({ type: 'warning', message: response.data.deadline_sync_warning, timeout: 8000 })
                }
                return response.data
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: Object.values(error.response?.data?.errors || {}).flat()[0] || error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async destroy(workId, onProgress = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                let result = null
                if (onProgress) {
                    const stream = createWorkCheckStream(onProgress)
                    const response = await axios.delete(`/api/admin/teaching/course_works/${workId}`,
                        { params: { stream_progress: 1 }, ...stream.options })
                    result = stream.result(response)
                    if (result?.deleted !== true) throw new Error('Das Löschen wurde nicht bestätigt.')
                } else {
                    const response = await axios.delete(`/api/admin/teaching/course_works/${workId}`)
                    result = response.data
                }
                this.courseWorks = this.courseWorks.filter((work) => String(work.id) !== String(workId))
                if (String(this.selected_courseWork?.id) === String(workId)) this.selected_courseWork = null
                const entryStore = useCourseStudentEntryStore()
                const keepEntry = (entry) => entry.source !== 'course_work' || String(entry.teaching_course_work_id) !== String(workId)
                entryStore.entries = entryStore.entries.filter(keepEntry)
                entryStore.courseEntries = entryStore.courseEntries.filter(keepEntry)
                const detail = result?.skipped_unverified
                    ? (result.message || 'Nicht eindeutig zugeordnete Prüfungsordner oder MC-Tests blieben erhalten.') : ''
                notification.notify({
                    message: detail && /^Arbeit gelöscht/i.test(detail) ? detail : `Arbeit gelöscht.${detail ? ` ${detail}` : ''}`,
                    type: result?.skipped_unverified ? 'warning' : 'success',
                    timeout: result?.skipped_unverified ? 15000 : 4000,
                })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || error.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 12000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        clearWorks() {
            this.courseWorks = []
            this.loadedCourseId = null
            this.selected_courseWork = null
        },
    },
})
