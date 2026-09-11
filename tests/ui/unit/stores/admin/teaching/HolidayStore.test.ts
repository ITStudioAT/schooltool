import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useHolidayStore } from '@/stores/admin/teaching/HolidayStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('HolidayStore', () => {
    const adminStoreMock = { is_loading: 0 }
    const notifyMock = vi.fn()
    const notificationStoreMock = { notify: notifyMock }
    const axiosMock = {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    }

    beforeEach(() => {
        setActivePinia(createPinia())

        adminStoreMock.is_loading = 0
        notifyMock.mockReset()
        axiosMock.get.mockReset()
        axiosMock.post.mockReset()
        axiosMock.delete.mockReset()

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue(notificationStoreMock as never)

        globalThis.axios = axiosMock as never
    })

    it('index loads only school scoped holidays', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [
                    { id: 1, scope: 'school', reason: 'Holiday' },
                    { id: 2, scope: 'teacher', reason: 'Sick' },
                ],
            },
        })

        const store = useHolidayStore()
        const result = await store.index()

        expect(result).toBe(true)
        expect(store.holidays).toEqual([{ id: 1, scope: 'school', reason: 'Holiday' }])
        expect(adminStoreMock.is_loading).toBe(0)
    })

    it('destroyMany deduplicates ids and reports partial failures', async () => {
        axiosMock.delete.mockResolvedValueOnce({}).mockRejectedValueOnce(new Error('delete failed'))

        const store = useHolidayStore()
        const result = await store.destroyMany([1, '1', 2, null, undefined])

        expect(axiosMock.delete).toHaveBeenCalledTimes(2)
        expect(result).toEqual({ success: false, deleted: 1, failed: 1 })
        expect(notifyMock).toHaveBeenCalledWith(
            expect.objectContaining({
                type: 'error',
                message: expect.stringContaining('1 freie Tage gelöscht'),
            }),
        )
        expect(adminStoreMock.is_loading).toBe(0)
    })

    it('destroyMany returns early for empty ids', async () => {
        const store = useHolidayStore()
        const result = await store.destroyMany([null, undefined])

        expect(result).toEqual({ success: false, deleted: 0, failed: 0 })
        expect(axiosMock.delete).not.toHaveBeenCalled()
    })

    it('indexMine stores teacher and common holidays from api', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [{ id: 10, scope: 'teacher' }, { id: 11, scope: 'school' }],
            },
        })

        const store = useHolidayStore()
        const result = await store.indexMine()

        expect(result).toBe(true)
        expect(store.my_holidays).toEqual([{ id: 10, scope: 'teacher' }, { id: 11, scope: 'school' }])
        expect(adminStoreMock.is_loading).toBe(0)
    })

    it('exports a JSON blob preserving dates, Unicode reasons and null values', async () => {
        const payload = {
            export_type: 'teaching_holidays',
            schema_version: 1,
            holidays: [{ date: '2026-10-26', reason: 'Österreich – Feiertag' }, { date: '2026-10-27', reason: null }],
        }
        axiosMock.get.mockResolvedValue({ data: payload })

        const result = await useHolidayStore().exportHolidays()

        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/teaching/holidays/export')
        expect(result).toBeInstanceOf(Blob)
        expect(result.type).toBe('application/json;charset=utf-8')
        expect(JSON.parse(await result.text())).toEqual(payload)
    })

    it('reports an export failure without producing a download', async () => {
        axiosMock.get.mockRejectedValue({ response: { status: 403, data: { message: 'Sie haben keine Berechtigung' } } })

        expect(await useHolidayStore().exportHolidays()).toBe(false)
        expect(notifyMock).toHaveBeenCalledWith(expect.objectContaining({
            status: 403,
            type: 'error',
            message: 'Sie haben keine Berechtigung',
        }))
    })

    it('uploads the selected file as multipart data and returns all import counters', async () => {
        const file = new File(['{}'], 'ferien.json', { type: 'application/json' })
        const result = { created: 2, updated: 1, unchanged: 3 }
        axiosMock.post.mockResolvedValue({ data: result })
        const store = useHolidayStore()
        store.import_errors = ['Alter Fehler']

        expect(await store.importHolidays(file)).toEqual(result)
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/teaching/holidays/import', expect.any(FormData))
        expect(axiosMock.post.mock.calls[0][1].get('file')).toBe(file)
        expect(store.import_errors).toEqual([])
    })

    it('retains every field validation error for display beside the import', async () => {
        axiosMock.post.mockRejectedValue({
            response: {
                status: 422,
                data: { errors: { 'holidays.0.date': ['Termin 1: Das Datum ist ungültig.'], 'holidays.1.reason': ['Termin 2: Der Grund ist zu lang.'] } },
            },
        })
        const store = useHolidayStore()

        expect(await store.importHolidays(new File(['{}'], 'ferien.json'))).toBe(false)
        expect(store.import_errors).toEqual(['Termin 1: Das Datum ist ungültig.', 'Termin 2: Der Grund ist zu lang.'])
        expect(notifyMock).not.toHaveBeenCalled()
    })

    it('shows an understandable fallback when an import request has no server response', async () => {
        axiosMock.post.mockRejectedValue(new Error('Network Error'))
        const store = useHolidayStore()

        expect(await store.importHolidays(new File(['{}'], 'ferien.json'))).toBe(false)
        expect(store.import_errors).toEqual(['Die Ferien konnten nicht importiert werden.'])
    })
})
