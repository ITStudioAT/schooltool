import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useMenuStore } from '@/stores/admin/restaurant/MenuStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('MenuStore', () => {
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

    it('loads menu list', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [{ id: 1, title: 'Mittagsmenü', foods: [] }],
            },
        })

        const store = useMenuStore()
        const result = await store.index()

        expect(result).toBe(true)
        expect(store.menus).toHaveLength(1)
        expect(store.menus[0].title).toBe('Mittagsmenü')
    })

    it('stores new menu and keeps menus sorted', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                data: { id: 5, title: 'Abendmenü', foods: [] },
            },
        })

        const store = useMenuStore()
        store.menus = [{ id: 1, title: 'Tagesmenü' }] as never

        const result = await store.store({ title: 'Abendmenü', food_ids: [1], price: '8.5' })

        expect(result?.title).toBe('Abendmenü')
        expect(store.menus.map((menu) => menu.title)).toEqual(['Abendmenü', 'Tagesmenü'])
    })

    it('removes deleted menu from state', async () => {
        axiosMock.delete.mockResolvedValue({})

        const store = useMenuStore()
        store.menus = [
            { id: 1, title: 'Tagesmenü' },
            { id: 2, title: 'Abendmenü' },
        ] as never

        const result = await store.destroy(1)

        expect(result).toBe(true)
        expect(store.menus.map((menu) => menu.id)).toEqual([2])
    })
})
