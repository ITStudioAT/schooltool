import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useCourseWorkStore } from '@/stores/admin/teaching/CourseWorkStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useCourseStudentEntryStore } from '@/stores/admin/teaching/CourseStudentEntryStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('CourseWorkStore', () => {
    const adminStoreMock = { is_loading: 0 }
    const notifyMock = vi.fn()
    const notificationStoreMock = { notify: notifyMock }
    const axiosMock = {
        delete: vi.fn(),
        get: vi.fn(),
        put: vi.fn(),
    }

    beforeEach(() => {
        setActivePinia(createPinia())

        adminStoreMock.is_loading = 0
        notifyMock.mockReset()
        axiosMock.get.mockReset()
        axiosMock.delete.mockReset()
        axiosMock.put.mockReset()

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue(notificationStoreMock as never)
        globalThis.axios = axiosMock as never
    })

    it.each([true, false])('updates all student caches only after a successful work deletion: %s', async (success) => {
        const store = useCourseWorkStore()
        const entries = useCourseStudentEntryStore()
        store.courseWorks = [{ id: 10 }, { id: 11 }] as never
        store.selected_courseWork = { id: 10 } as never
        const rows = [
            { id: 1, user_id: 20, source: 'course_work', teaching_course_work_id: 10 },
            { id: 2, user_id: 21, source: 'course_work', teaching_course_work_id: '10' },
            { id: 3, user_id: 20, source: 'manual', teaching_course_work_id: null },
            { id: 4, user_id: 21, source: 'course_work', teaching_course_work_id: 11 },
        ]
        entries.courseEntries = [...rows] as never
        entries.entries = [...rows] as never
        if (success) axiosMock.delete.mockResolvedValue({ status: 204 })
        else axiosMock.delete.mockRejectedValue(new Error('Failed'))
        expect(await store.destroy(10)).toBe(success)
        expect(entries.courseEntries).toEqual(success ? rows.slice(2) : rows)
        expect(entries.entries).toEqual(success ? rows.slice(2) : rows)
        expect(store.courseWorks).toEqual(success ? [{ id: 11 }] : [{ id: 10 }, { id: 11 }])
        expect(store.selected_courseWork).toEqual(success ? null : { id: 10 })
    })

    it('index loads works by course id', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [{ id: 10, title: 'Testarbeit' }],
            },
        })

        const store = useCourseWorkStore()
        const result = await store.index(55)

        expect(result).toBe(true)
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/teaching/course_works', {
            params: { course_id: 55 },
        })
        expect(store.courseWorks).toEqual([{ id: 10, title: 'Testarbeit' }])
        expect(store.loadedCourseId).toBe(55)
        store.clearWorks()
        expect(store.loadedCourseId).toBeNull()
        expect(store.courseWorks).toEqual([])
    })

    it('shows the server explanation when work deletion is stopped', async () => {
        axiosMock.delete.mockRejectedValue({ response: { status: 409, data: { message: 'Die Zuordnung ist nicht eindeutig.' } } })
        const store = useCourseWorkStore()

        expect(await store.destroy(10)).toBe(false)
        expect(notifyMock).toHaveBeenCalledWith(expect.objectContaining({
            status: 409,
            message: 'Die Zuordnung ist nicht eindeutig.',
            timeout: 12000,
        }))
    })

    it.each([true, false])('reports actual deletion progress and changes local data only on confirmed success: %s', async success => {
        const progress = { state: 'running', active: 'delete_folders', completed: ['delete_check'], skipped: [], steps: ['delete_check', 'delete_folders', 'delete_mc_files', 'delete_records'] }
        const first = `${JSON.stringify({ type: 'progress', data: progress })}\n`
        const last = `${JSON.stringify(success ? { type: 'result', data: { deleted: true } }
            : { type: 'error', data: { status: 409, body: { message: 'Löschung nicht bestätigt.' } } })}\n`
        axiosMock.delete.mockImplementation(async (_url, options) => {
            options.onDownloadProgress({ event: { target: { responseText: first } } })
            return { data: first + last }
        })
        const store = useCourseWorkStore()
        store.courseWorks = [{ id: 10 }] as never
        const report = vi.fn()
        expect(await store.destroy(10, report)).toBe(success)
        expect(report).toHaveBeenCalledExactlyOnceWith(progress)
        expect(store.courseWorks).toEqual(success ? [] : [{ id: 10 }])
        if (!success) expect(notifyMock).toHaveBeenCalledWith(expect.objectContaining({ message: 'Löschung nicht bestätigt.' }))
    })

    it('reports successful deletion with retained uncertain links from the streamed result', async () => {
        const store = useCourseWorkStore()
        store.courseWorks = [{ id: 10 }] as never
        axiosMock.delete.mockResolvedValue({ data: `${JSON.stringify({ type: 'result', data: {
            deleted: true, skipped_unverified: true, message: 'Unklare Ordner und Tests bleiben erhalten.',
        } })}\n` })
        expect(await store.destroy(10, vi.fn())).toBe(true)
        expect(store.courseWorks).toEqual([])
        expect(notifyMock).toHaveBeenCalledWith(expect.objectContaining({
            message: 'Arbeit gelöscht. Unklare Ordner und Tests bleiben erhalten.', type: 'warning',
        }))
        expect(axiosMock.delete).toHaveBeenCalledTimes(1)
    })

    it('uses the saved deadline in the work list and selected work without waiting for a reload', async () => {
        const store = useCourseWorkStore()
        const previous = { id: 10, finish_until_date: '2026-11-05', finish_until_time: '12:00' }
        const saved = { ...previous, finish_until_date: '2026-11-06', finish_until_time: '17:30' }
        store.courseWorks = [previous, { id: 11 }] as never
        store.selected_courseWork = previous as never
        axiosMock.put.mockResolvedValue({ data: { data: saved } })

        expect(await store.update(saved)).toEqual({ data: saved })
        expect(store.courseWorks).toEqual([saved, { id: 11 }])
        expect(store.selected_courseWork).toEqual(saved)
        expect(axiosMock.get).not.toHaveBeenCalled()
    })

    it('update returns false if id is missing', async () => {
        const store = useCourseWorkStore()
        const result = await store.update({ title: 'No id' } as never)

        expect(result).toBe(false)
        expect(axiosMock.put).not.toHaveBeenCalled()
        expect(notifyMock).toHaveBeenCalledWith(expect.objectContaining({ status: 422 }))
    })
})
