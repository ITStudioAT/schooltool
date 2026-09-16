import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useCourseStudentEntryStore } from '@/stores/admin/teaching/CourseStudentEntryStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('CourseStudentEntryStore', () => {
    const adminStoreMock = { is_loading: 0 }
    const notifyMock = vi.fn()
    const notificationStoreMock = { notify: notifyMock }
    const axiosMock = {
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    }

    beforeEach(() => {
        setActivePinia(createPinia())

        adminStoreMock.is_loading = 0
        notifyMock.mockReset()
        axiosMock.get.mockReset()
        axiosMock.post.mockReset()
        axiosMock.patch.mockReset()
        axiosMock.delete.mockReset()

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue(notificationStoreMock as never)
        globalThis.axios = axiosMock as never
    })

    it('store prepends created entry to entries and courseEntries', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                data: { id: 5, type: 'MA' },
            },
        })

        const store = useCourseStudentEntryStore()
        store.entries = [{ id: 1, type: 'SA' }] as never
        store.courseEntries = [{ id: 2, type: 'MA' }] as never

        const result = await store.store({ type: 'MA' } as never)

        expect(result).toEqual({ data: { id: 5, type: 'MA' } })
        expect(store.entries.map((e: any) => e.id)).toEqual([5, 1])
        expect(store.courseEntries.map((e: any) => e.id)).toEqual([5, 2])
    })

    it.each(['assessment', 'behaviour', 'notification'])('transfers %s through its generated endpoint without replacing cached entries', async (kind) => {
        const copies = [{ id: 21, user_id: 12 }]
        axiosMock.post.mockResolvedValue({ data: { data: copies } })
        const store = useCourseStudentEntryStore()
        store.courseEntries = [{ id: 20 }] as never
        const payload = { course_date_id: 3, user_ids: [12] }

        expect(await store.transfer(20, payload, kind)).toEqual({ data: copies })
        expect(axiosMock.post).toHaveBeenCalledWith(
            `/api/admin/teaching/${kind === 'assessment' ? 'course_student_entries' : 'course_behaviour_entries'}/20/transfer`, payload,
        )
        expect(store.courseEntries).toEqual([{ id: 20 }])
        expect(adminStoreMock.is_loading).toBe(0)
    })

    it('reports transfer failure and restores the loading state', async () => {
        axiosMock.post.mockRejectedValue({ response: { status: 422, data: { message: 'Ungültige Auswahl' } } })
        const store = useCourseStudentEntryStore()
        expect(await store.transfer(20, { course_date_id: 3, user_ids: [12] })).toBe(false)
        expect(notifyMock).toHaveBeenCalledWith(expect.objectContaining({ message: 'Ungültige Auswahl', type: 'error' }))
        expect(adminStoreMock.is_loading).toBe(0)
    })

    it('destroy removes entry from both collections', async () => {
        axiosMock.delete.mockResolvedValue({})

        const store = useCourseStudentEntryStore()
        store.entries = [{ id: 1 }, { id: 2 }] as never
        store.courseEntries = [{ id: 2 }, { id: 3 }] as never

        const result = await store.destroy(2)

        expect(result).toBe(true)
        expect(store.entries).toEqual([{ id: 1 }])
        expect(store.courseEntries).toEqual([{ id: 3 }])
    })

    it('loads all entries table data in one request', async () => {
        const tableData = {
            data: [{ id: 1, type: 'MA' }],
            behaviour_entries: [{ id: 2, type: 'OK' }],
            course_works: [{ id: 3, type: 'SA' }],
        }
        axiosMock.get.mockResolvedValue({ data: tableData })

        const store = useCourseStudentEntryStore()
        const result = await store.indexTableData(16)

        expect(result).toEqual(tableData)
        expect(axiosMock.get).toHaveBeenCalledWith(
            '/api/admin/teaching/course_student_entries',
            { params: { course_id: 16, include_table_data: 1 } },
        )
        expect(adminStoreMock.is_loading).toBe(0)
    })

    it('loads notification recipients through the generated route', async () => {
        const recipients = [{ key: 'recipient-key', available: true }]
        axiosMock.get.mockResolvedValue({ data: { data: recipients } })

        const store = useCourseStudentEntryStore()
        const result = await store.notificationRecipients(27)

        expect(result).toEqual(recipients)
        expect(axiosMock.get).toHaveBeenCalledWith(
            '/api/admin/teaching/course_student_entries/27/notifications',
        )
    })

    it('previews notification recipients before an entry is created', async () => {
        const recipients = [{ key: 'recipient-key', available: true }]
        axiosMock.get.mockResolvedValue({ data: { data: recipients } })

        const store = useCourseStudentEntryStore()
        const result = await store.previewNotificationRecipients(16, 27, 'V')

        expect(result).toEqual(recipients)
        expect(axiosMock.get).toHaveBeenCalledWith(
            '/api/admin/teaching/course_student_entry_notification_recipients',
            {
                params: {
                    course_id: 16,
                    user_id: 27,
                    type: 'V',
                },
            },
        )
    })

    it('sends selected notification recipients and returns their stored status', async () => {
        const recipients = [{
            key: 'recipient-key',
            informed_at: '2026-10-20T14:35:00+00:00',
            confirmed_at: null,
        }]
        axiosMock.post.mockResolvedValue({
            data: {
                data: recipients,
                message: 'Die ausgewählten Personen wurden per E-Mail informiert.',
            },
        })

        const store = useCourseStudentEntryStore()
        store.entries = [{ id: 27, has_pending_notification_confirmation: false }] as never
        store.courseEntries = [{ id: 27, has_pending_notification_confirmation: false }] as never
        const result = await store.sendNotifications(27, ['recipient-key'])

        expect(result).toEqual(recipients)
        expect(store.entries[0].has_pending_notification_confirmation).toBe(true)
        expect(store.courseEntries[0].has_pending_notification_confirmation).toBe(true)
        expect(axiosMock.post).toHaveBeenCalledWith(
            '/api/admin/teaching/course_student_entries/27/notifications',
            { recipients: ['recipient-key'] },
        )
        expect(notifyMock).toHaveBeenCalledWith(expect.objectContaining({ type: 'success' }))
    })

    it('confirms a notification manually and clears the pending entry state', async () => {
        const recipients = [{
            key: 'recipient-key',
            notification_id: 91,
            informed_at: '2026-10-20T14:35:00+00:00',
            confirmed_at: '2026-10-20T15:05:00+00:00',
        }]
        axiosMock.patch.mockResolvedValue({
            data: {
                data: recipients,
                message: 'Die Bestätigung wurde manuell erfasst.',
            },
        })

        const store = useCourseStudentEntryStore()
        store.entries = [{ id: 27, has_pending_notification_confirmation: true }] as never
        store.courseEntries = [{ id: 27, has_pending_notification_confirmation: true }] as never
        const result = await store.confirmNotification(27, 91)

        expect(result).toEqual(recipients)
        expect(store.entries[0].has_pending_notification_confirmation).toBe(false)
        expect(store.courseEntries[0].has_pending_notification_confirmation).toBe(false)
        expect(axiosMock.patch).toHaveBeenCalledWith(
            '/api/admin/teaching/course_student_entries/27/notifications/91',
        )
        expect(notifyMock).toHaveBeenCalledWith(expect.objectContaining({ type: 'success' }))
    })
})
