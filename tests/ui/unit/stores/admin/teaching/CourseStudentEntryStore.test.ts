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
})
