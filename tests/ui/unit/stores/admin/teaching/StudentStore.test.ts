import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useStudentStore } from '@/stores/admin/teaching/StudentStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('StudentStore', () => {
    const adminStoreMock = { is_loading: 0 }
    const notifyMock = vi.fn()
    const notificationStoreMock = { notify: notifyMock }
    const axiosMock = {
        get: vi.fn(),
    }

    beforeEach(() => {
        setActivePinia(createPinia())

        adminStoreMock.is_loading = 0
        notifyMock.mockReset()
        axiosMock.get.mockReset()

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue(notificationStoreMock as never)
        globalThis.axios = axiosMock as never
    })

    it('loadClassStudents loads students and classes with query params', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [{ id: 1, last_name: 'A' }],
                classes: ['1A', '1B'],
            },
        })

        const store = useStudentStore()
        const result = await store.loadClassStudents(['1A'])

        expect(result).toBe(true)
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/teaching/load_class_students', {
            params: { schoolclasses: ['1A'] },
        })
        expect(store.students).toEqual([{ id: 1, last_name: 'A' }])
        expect((store as any).classes).toEqual(['1A', '1B'])
    })
})
