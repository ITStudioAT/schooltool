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
})
