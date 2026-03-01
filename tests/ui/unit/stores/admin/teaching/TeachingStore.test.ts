import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('TeachingStore', () => {
    const notifyMock = vi.fn()
    const adminStoreMock = {
        is_loading: 0,
        config: { user: { teaching_active_semester: 1, teaching_count_for_semester_2_date: null } },
    }
    const notificationStoreMock = { notify: notifyMock }
    const axiosMock = {
        get: vi.fn(),
        post: vi.fn(),
    }

    beforeEach(() => {
        setActivePinia(createPinia())

        notifyMock.mockReset()
        adminStoreMock.is_loading = 0
        adminStoreMock.config.user.teaching_active_semester = 1
        adminStoreMock.config.user.teaching_count_for_semester_2_date = null
        axiosMock.get.mockReset()
        axiosMock.post.mockReset()

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue(notificationStoreMock as never)
        globalThis.axios = axiosMock as never
    })

    it('loadSettings stores settings and exposes schema getters', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                settings: {
                    teaching_schemas: [{ id: 'schema-1', works: [{ short_name: 'MA' }], grading: { semester_count: 2 } }],
                    teaching_behaviour: [{ short_name: 'BZ' }],
                    teaching_notifications: [{ short_name: 'INF' }],
                },
            },
        })

        const store = useTeachingStore()
        const result = await store.loadSettings()

        expect(result).toBe(true)
        expect(store.schemas).toHaveLength(1)
        expect(store.schemaById('schema-1')?.id).toBe('schema-1')
        expect(store.worksForSchema('schema-1')).toEqual([{ short_name: 'MA' }])
        expect(store.gradingForSchema('schema-1')).toEqual({ semester_count: 2 })
        expect(store.hasTwoSemesters).toBe(true)
    })

    it('saveActiveSemester posts payload and updates admin config user', async () => {
        axiosMock.post.mockResolvedValue({ data: {} })

        const store = useTeachingStore()
        await store.saveActiveSemester(3)

        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/teaching/save_active_semester', {
            teaching_active_semester: 3,
        })
        expect(adminStoreMock.config.user.teaching_active_semester).toBe(3)
    })
})
