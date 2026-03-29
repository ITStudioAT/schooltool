import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useSchoolToolStore } from '@/stores/admin/SchoolToolStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('SchoolToolStore', () => {
    const adminStoreMock = {
        is_loading: 0,
        loadConfig: vi.fn(),
    }
    const notificationStoreMock = {
        notify: vi.fn(),
    }
    const axiosMock = {
        get: vi.fn(),
        post: vi.fn(),
    }

    beforeEach(() => {
        setActivePinia(createPinia())

        adminStoreMock.is_loading = 0
        adminStoreMock.loadConfig.mockReset()
        notificationStoreMock.notify.mockReset()
        axiosMock.get.mockReset()
        axiosMock.post.mockReset()

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue(notificationStoreMock as never)

        globalThis.axios = axiosMock as never
    })

    it('reloads the admin config after saving module statuses', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                id: 1,
                register_visible_admin: false,
            },
        })
        adminStoreMock.loadConfig.mockResolvedValue(true)

        const store = useSchoolToolStore()
        const payload = {
            id: 1,
            register_visible_admin: false,
        }

        const result = await store.saveModuleStatuses(payload)

        expect(result).toBe(true)
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/school_tools/save_module_statuses', { data: payload })
        expect(store.data).toEqual({
            id: 1,
            register_visible_admin: false,
        })
        expect(adminStoreMock.loadConfig).toHaveBeenCalledTimes(1)
    })
})
