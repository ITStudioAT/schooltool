import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useCourseBehaviourEntryStore } from '@/stores/admin/teaching/CourseBehaviourEntryStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('CourseBehaviourEntryStore', () => {
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

    it('indexByCourse loads course behaviour entries', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [{ id: 3, type: 'BZ' }],
            },
        })

        const store = useCourseBehaviourEntryStore()
        const result = await store.indexByCourse(77)

        expect(result).toBe(true)
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/teaching/course_behaviour_entries', {
            params: { course_id: 77 },
        })
        expect(store.courseEntries).toEqual([{ id: 3, type: 'BZ' }])
    })

    it('store prepends newly created behaviour entry', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                data: { id: 9, type: 'INF' },
            },
        })

        const store = useCourseBehaviourEntryStore()
        store.entries = [{ id: 1 }] as never
        store.courseEntries = [{ id: 2 }] as never

        await store.store({ type: 'INF' } as never)

        expect(store.entries.map((entry: any) => entry.id)).toEqual([9, 1])
        expect(store.courseEntries.map((entry: any) => entry.id)).toEqual([9, 2])
    })
})
